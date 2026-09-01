# Sauvegarde et restauration des données via fichiers Excel — spec

*(Design validé par l'utilisateur le 2026-09-01.)*

## Contexte et objectif

Aucun outil de sauvegarde/restauration applicatif n'existe aujourd'hui : en cas de compromission des données sur le serveur (notamment lors d'une mise à jour de l'application), le seul recours serait un accès direct à MySQL (mysqldump/phpMyAdmin). Ce chantier ajoute, pour les administrateurs SaaS, la capacité d'exporter la totalité des données "métier" de la plateforme vers une archive de fichiers Excel, de vider ces tables, et de les restaurer à l'identique — sans dépendre d'un accès direct à la base de données.

Ce n'est **pas** un outil de saisie en masse pour le personnel d'établissement : les fichiers produits sont des sauvegardes techniques fidèles (IDs et clés étrangères préservés), pas destinés à être édités à la main.

## Périmètre des tables

Toutes les tables de la base sont incluses, sauf une liste d'exclusion fixe de tables techniques Laravel :

```
cache, cache_locks, jobs, failed_jobs, job_batches,
sessions, password_reset_tokens, personal_access_tokens, migrations
```

La liste des tables à traiter est calculée **dynamiquement** depuis `information_schema.tables` de la base courante, moins cette liste d'exclusion codée en dur dans un fichier de config (`config/backup.php`, clé `excluded_tables`). Une table créée par une future migration est donc automatiquement incluse sans qu'il soit nécessaire de mettre à jour cet outil à chaque nouveau chantier.

**Hors périmètre pour ce chantier** : les fichiers uploadés (`storage/app/public` — photos d'élèves, logos, armoiries...). Leur sauvegarde sera traitée séparément (sauvegarde disque côté serveur). Les colonnes qui référencent ces fichiers (ex. `logo_path`) sont exportées telles quelles (le chemin, pas le fichier).

## Architecture technique

- Package **`maatwebsite/laravel-excel`** (construit sur PhpSpreadsheet) — à installer via composer, aucun package Excel n'existe encore dans le projet.
- Moteur **générique**, sans code spécifique par table :
  - Export : une classe `App\Domain\Backup\Exports\TableExport` paramétrée par un nom de table, implémentant `FromQuery`, `WithHeadings` et `WithChunkReading` ; les colonnes exportées sont lues dynamiquement via `Schema::getColumnListing($table)`.
  - Import : une classe `App\Domain\Backup\Imports\TableImport` paramétrée par un nom de table, lecture brute (`ToArray` / `OnEachRow`, pas de mapping Eloquent) avec insertion par lots via `DB::table($table)->insert(...)`.
- Un service `App\Domain\Backup\Services\BackupTableRegistry` centralise le calcul de la liste des tables du périmètre (utilisé par les commandes CLI et l'écran Livewire).
- Vidage et import désactivent `FOREIGN_KEY_CHECKS` le temps de l'opération (MySQL) — aucun calcul d'ordre de dépendance entre tables n'est nécessaire. Après import, l'auto-increment de chaque table restaurée est repositionné sur `MAX(id) + 1` (les tables sans colonne `id` — tables pivot — n'ont pas besoin de ce repositionnement).
- Une archive est un fichier **`.zip`** nommé `backup-AAAA-MM-JJ-HHMMSS.zip`, contenant un fichier **`<table>.xlsx`** par table du périmètre (assemblage via `ZipArchive`, natif PHP).
- Emplacement par défaut des archives générées : `storage/app/backups/`.

## Commandes CLI (usage déploiement, exécutées en SSH)

- `php artisan backup:export [--path=]`
  Génère l'archive (chemin par défaut `storage/app/backups/backup-<horodatage>.zip`, ou `--path=` pour un chemin explicite). Affiche le chemin final en sortie.

- `php artisan backup:wipe [--table=<nom>] [--force]`
  Vide toutes les tables du périmètre, ou une seule table avec `--table=`. Demande une confirmation interactive si `--force` est absent.

- `php artisan backup:import <archive> [--table=<nom>] [--force] [--dry-run]`
  Restaure depuis une archive `.zip` : toutes les tables présentes dans l'archive et dans le périmètre, ou une seule avec `--table=`.
  - **Refuse d'écrire dans une table qui contient déjà des lignes** (message invitant à lancer `backup:wipe` sur cette table au préalable — vidage et import restent deux actions séparées et volontaires, jamais enchaînées automatiquement).
  - `--dry-run` : affiche un résumé (nombre de lignes par table dans l'archive, colonnes qui ne correspondent plus au schéma actuel) sans rien modifier.
  - Demande une confirmation interactive si `--force` est absent.
  - Un fichier de l'archive qui ne correspond à aucune table du périmètre actuel est ignoré avec un avertissement (pas d'erreur bloquante).

## Écran Livewire (SaaS admin, sans accès SSH)

Nouvel écran `App\Livewire\Backup\Index`, sous un point d'entrée "Sauvegarde" visible uniquement dans le contexte SaaS admin (pas dans la navigation d'établissement).

- **Exporter** : accessible à tout `SaasAdmin` actif (type `Main` ou `Second`). Génère l'archive et la propose en téléchargement direct.
- **Vider** et **Restaurer** (upload d'une archive `.zip`) : réservés au `SaasAdmin` actif de type **`Main`**. Sélecteur "toutes les tables du périmètre" ou une table précise. Le bouton ne s'active qu'après saisie exacte d'un mot de confirmation en majuscules dans un champ dédié : `VIDER` pour le vidage, `RESTAURER` pour la restauration.
- Génération et restauration **synchrones** (pas de file d'attente) avec une limite de temps d'exécution (`set_time_limit`) relevée spécifiquement pour cette action — le volume de données actuel reste modeste, une exécution en tâche de fond n'est pas nécessaire pour ce premier chantier.
- Une policy `BackupPolicy` porte trois abilities (`export`, `wipe`, `import`), vérifiées via `$this->authorize()` dans le composant Livewire, ciblant un marqueur non-Eloquent `App\Domain\Backup\Support\BackupOperation` (pattern déjà utilisé dans l'app pour autoriser sur une classe plutôt qu'une instance).

  **Point d'attention découvert en relisant l'existant** : `AppServiceProvider::boot()` définit un `Gate::before` qui accorde automatiquement **toute** ability à **tout** SaaS admin actif (Main ou Second), avec une seule exception déjà en place pour le roster des admins SaaS eux-mêmes (`SaasAdminPolicy`, actions mutantes réservées à Main). Sans ajustement, ce bypass global accorderait aussi `wipe`/`import` à un Second, contournant silencieusement la restriction ci-dessus. Ce chantier étend donc ce `Gate::before` existant avec la même logique de carve-out déjà utilisée pour `SaasAdmin::class` : quand la cible est `BackupOperation::class` et que l'ability est `wipe` ou `import`, le bypass ne s'applique pas et on laisse `BackupPolicy` trancher (`export` reste couvert par le bypass global, comme les abilities de lecture du roster SaaS admin).

## Gestion des erreurs

- **Dérive de schéma** : avant toute écriture, le header du fichier `.xlsx` de chaque table est comparé strictement à `Schema::getColumnListing()` de la table cible. En cas d'écart (colonne en trop ou manquante), l'opération échoue explicitement pour cette table, liste les colonnes en cause, et n'insère aucune ligne. Aucune tentative de "deviner" ou de mapper partiellement — la correction (édition manuelle du fichier, ou choix d'une archive plus récente) reste à la charge de l'opérateur.
- **Fichier orphelin dans l'archive** (nom sans table correspondante dans le périmètre actuel) : ignoré avec avertissement, pas d'erreur bloquante.
- **Table non vide à l'import** : échec explicite pour cette table avec message invitant à `backup:wipe` au préalable ; les autres tables de l'archive continuent d'être traitées.
- Toute opération (export, vidage, import — CLI comme écran) écrit une ligne dans les logs applicatifs : table(s) concernée(s), nombre de lignes, auteur (utilisateur CLI système ou `SaasAdmin` authentifié), horodatage.

## Tests

- Tests Feature sur le moteur générique : export puis import "round-trip" sur un échantillon de tables représentatif (au moins une table simple, une table avec clé étrangère, une table pivot sans colonne `id`), vérification que les IDs sont préservés à l'identique et que l'auto-increment repart au bon endroit après restauration.
- Test du refus d'import sur une table non vide.
- Test de la détection de dérive de schéma (colonne ajoutée/retirée entre export et import).
- Test du fichier orphelin ignoré sans échec bloquant.
- Tests de policy : `Main` peut vider/restaurer, `Second` ne peut qu'exporter, un utilisateur non-`SaasAdmin` n'a accès à aucune des trois actions.
- Pas de test dédié à chacune des ~55 tables individuellement : le moteur étant générique, un échantillon représentatif suffit à le valider ; la liste effective des tables du périmètre est, elle, testée via `BackupTableRegistry` (vérifie que les tables techniques exclues sont bien absentes du résultat).

## Hors périmètre (rappel)

- Sauvegarde des fichiers uploadés (`storage/app/public`).
- Restauration partielle avec fusion/upsert (l'import ne touche jamais une table non vide).
- Exécution en tâche de fond / file d'attente pour l'export ou l'import.
- Sauvegarde automatique planifiée (cette spec couvre le déclenchement manuel ; une planification récurrente pourrait faire l'objet d'un chantier ultérieur).
