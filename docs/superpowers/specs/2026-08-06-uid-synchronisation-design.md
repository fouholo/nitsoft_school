# Uid de synchronisation (compteur global, compatible code-barres)

**Date** : 2026-08-06
**Statut** : Approuvé

## Contexte

Le projet est un SaaS multi-établissements destiné à fonctionner online et offline (Desktop/Mobile NativePHP, pas encore construits — Phase 3 du plan d'architecture) avec synchronisation ultérieure. Depuis la Phase 0, un trait `Syncable` génère un UUID aléatoire (`Str::uuid()`) à la création, sur 8 tables (`students`, `enrollments`, `grade_sheets`, `grades`, `attendance_sessions`, `attendance_records`, `invoices`, `payments`). Un audit du code a montré que toutes les tables métier ajoutées depuis (Foundations, `classrooms`, etc.) n'ont pas suivi cette convention — incohérence à corriger avant que le schéma ne grossisse davantage.

En creusant le besoin avec l'utilisateur, deux choses ont changé par rapport à la convention Phase 0 :
1. L'identifiant ne doit plus être un UUID aléatoire à 36 caractères, mais un **uid numérique de 12 caractères, incrémenté**, pensé pour pouvoir servir de **code-barres** (cartes d'élèves, factures, etc.).
2. La portée est **globale à toute la plateforme** (un seul compteur partagé par toutes les tables et tous les établissements), pas un compteur par table ni par établissement.

## Décisions validées avec l'utilisateur

1. **Toutes les tables métier**, pas seulement celles réellement éditables hors-ligne — uniformité voulue plutôt qu'un tri au cas par cas.
2. **Exclusions** : tables calculées côté serveur (`report_cards`, `receipts`, `sms_messages`), tables pivot, tables techniques (`devices`, `sync_cursors`, `sync_conflicts_log`, etc.).
3. **Format** : purement numérique, 12 caractères, zéro-paddé (ex. `000000001046`) — compatible EAN-13/UPC-A/Code 39-128 numérique. Pas d'alphanumérique.
4. **Portée du compteur** : globale à toute la plateforme, partagée par toutes les tables — un uid identifie un enregistrement sans ambiguïté nulle part besoin de connaître la table ou l'établissement d'origine.
5. **Moment d'affectation** : le uid est affecté **par le serveur**, jamais généré côté client. Comme l'application web est aujourd'hui le serveur lui-même (pas encore de client offline séparé), l'affectation a lieu **dès la création** de l'enregistrement. Le même mécanisme sera réutilisé tel quel par le futur moteur de sync (Phase 3) au moment où un enregistrement créé hors-ligne atteindra le serveur pour la première fois — pas de logique différente à écrire à ce moment-là.
6. **Renommage** : la colonne `uuid` (8 tables existantes) devient `uid` — le format change fondamentalement (numérique 12 caractères vs UUID aléatoire 36 caractères), garder le nom `uuid` induirait en erreur.
7. **Données existantes** : projet greenfield, données de démo uniquement. Pas de backfill complexe — `migrate:fresh --seed` suffit.

## Design

### 1. Génération du uid

Une table technique dédiée `uid_counters` (une seule colonne auto-increment, aucun tenant scope — c'est un compteur global). Chaque affectation fait un `insertGetId()` dessus : MySQL garantit l'atomicité même en cas de créations concurrentes, sans verrou explicite à gérer côté application. Le nombre obtenu est formaté en chaîne de 12 caractères zéro-paddée à gauche.

### 2. Modèle de données

- Nouvelle table `uid_counters` (technique, pas de `TenantScoped`).
- Sur les 18 tables métier concernées (8 existantes renommées + 10 nouvelles), colonne `uid` : `char(12)`, nullable, unique. Nullable car un enregistrement créé par une future Phase 3 côté client offline n'aura pas encore de uid tant qu'il n'a pas atteint le serveur.
- `device_id` et `client_updated_at` inchangés sur les 8 tables existantes ; ajoutés (nullables) sur les 10 nouvelles tables, pour rester cohérent avec le reste du mécanisme de sync même si leur usage actif reste à concevoir en Phase 3.
- Tables concernées : `students`, `enrollments`, `grade_sheets`, `grades`, `attendance_sessions`, `attendance_records`, `invoices`, `payments` (existantes) + `establishments`, `foundations`, `school_years`, `terms`, `classrooms`, `subjects`, `guardians`, `fee_schedules`, `sms_templates`, `users` (nouvelles).
- Exclues explicitement : `report_cards`, `receipts`, `sms_messages` (calculées côté serveur à partir d'entités déjà synchronisables), tables pivot (`establishment_user`, `foundation_user`, `teacher_classroom_subject`, `guardian_student`), tables techniques (`devices`, `sync_cursors`, `sync_conflicts_log`, `personal_access_tokens`, `cache`, `jobs`, `sessions`).

### 3. Service d'affectation

`App\Domain\Sync\Services\UidAssigner::assign(): string` encapsule l'incrémentation atomique et le formatage (12 caractères zéro-paddés). Le trait `Syncable` (`app/Domain/Sync/Concerns/Syncable.php`) garde son hook `creating`, mais appelle ce service au lieu de `Str::uuid()`. Le trait est appliqué aux 18 modèles concernés.

### 4. Erreurs / cas limites

- Table `uid_counters` vide au départ : `insertGetId()` démarre naturellement à 1, formaté `000000000001`.
- Dépassement de 12 chiffres (999 999 999 999 enregistrements) : hors de portée réaliste pour ce projet, non traité.
- Renommage `uuid` → `uid` et changement de type (`char(36)` → `char(12)`) sur les 8 tables existantes : migration `->change()`, disponible nativement en Laravel 12 sans `doctrine/dbal`.

### 5. Tests

- Test unitaire : `UidAssigner::assign()` retourne bien une chaîne de 12 caractères zéro-paddée, incrémente à chaque appel, reste unique sur des appels concurrents (test avec plusieurs assignations successives).
- Test feature : créer un enregistrement sur un modèle `Syncable` (ex. `Classroom`) déclenche l'affectation automatique d'un `uid` non nul.
- Vérifier qu'aucune table exclue (`ReportCard`, `Receipt`, `SmsMessage`) n'a de colonne `uid`.

## Hors périmètre (explicitement écarté)

- Toute logique de résolution de conflit ou de protocole push/pull réel (Phase 3, pas encore commencée).
- Peuplement de `packages/sync-contracts` — dépend de décisions de Phase 3 non encore prises.
- Génération/affichage effectif de codes-barres (impression de cartes, etc.) — seul le champ `uid` est posé, son usage visuel n'est pas demandé pour l'instant.
