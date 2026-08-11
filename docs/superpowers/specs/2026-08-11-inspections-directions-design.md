# Refonte de la table `inspections` + ajout de la table `directions`

## Contexte

La table `inspections` a été créée lors du chantier précédent (`2026-08-11-ecran-gestion-inspections-design.md`) comme simple table de référence : `code` (PK string 10), `libelle` (string 100), écran CRUD SaaS-only (`Livewire\Inspections\Index`).

Ce chantier enrichit `inspections` avec des champs d'identité complets (nom, adresse, contact, localisation) et l'intègre au système de synchronisation `uid_local`/`uid_serveur` (chantier `2026-08-10-uid-local-serveur-prefixe-design.md`), en prévision des usages offline (desktop/mobile). Il introduit en parallèle une nouvelle table `directions` (directions régionales), qui représente le niveau hiérarchique auquel une inspection est rattachée dans le système éducatif ivoirien.

## 1. Table `inspections` (refonte)

Colonnes actuelles (`code` PK string 10, `libelle` string 100) remplacées par :

| Colonne | Type | Règle |
|---|---|---|
| `id` | bigint auto-increment | Nouvelle clé primaire |
| `uid_local` | string(20) | Généré à la saisie (`Syncable`) |
| `uid_serveur` | char(12) | Généré par le serveur, préfixe **217** |
| `codeiep` | string(6) | Unique, **modifiable** (remplace `code`) |
| `inspection_name` | string(50) | Remplace `libelle` |
| `address` | string(50) | Nullable |
| `phone_number` | string(20) | Nullable |
| `email` | string(50) | Nullable |
| `location` | string(50) | Nullable, texte libre |
| `uid_direction` | char(12) | Nullable — stocke le `uid_serveur` de la direction de rattachement |

Le modèle `Inspection` devient `Syncable` (comme `Establishment`, `Teacher`, etc.) : `uidPrefix()` retourne `'217'`. `uid_direction` n'est pas une contrainte SQL `foreign()` (ce n'est pas une colonne PK/unique classique côté `directions`, c'est un `uid_serveur` — même logique que les autres liens du système uid) ; la validation applicative vérifie qu'une direction avec ce `uid_serveur` existe.

`codeiep` reste unique mais n'est plus la clé primaire : il devient librement modifiable après création (contrairement à l'ancien `code`, verrouillé en édition).

## 2. Nouvelle table `directions`

Même gabarit que `inspections`, préfixe uid **218** :

| Colonne | Type | Règle |
|---|---|---|
| `id` | bigint auto-increment | PK |
| `uid_local` | string(20) | `Syncable` |
| `uid_serveur` | char(12) | Préfixe **218** |
| `code` | string(6) | Unique, modifiable |
| `direction_name` | string(50) | |
| `address` | string(50) | Nullable |
| `phone_number` | string(20) | Nullable |
| `email` | string(50) | Nullable |
| `location` | string(50) | Nullable |

- `app/Domain/Establishments/Models/Direction.php` (nouveau) : `Syncable`, `$fillable` = les 6 champs métier.
- `app/Policies/DirectionPolicy.php` (nouveau) : les 5 méthodes retournent `false` (seul le bypass SaaS admin dans `Gate::before` donne accès — même gabarit que `InspectionPolicy`).
- `app/Livewire/Directions/Index.php` + `resources/views/livewire/directions/index.blade.php` (nouveau) : CRUD complet, copie conforme de l'écran `Inspections\Index` refondu (§4), sans le select de rattachement (pas de hiérarchie au-dessus de `directions`).
- `routes/directions.php` (nouveau, même gabarit que `routes/inspections.php`) : `Route::prefix('directions')->name('directions.')->group(...)`, requis dans `routes/web.php` après `inspections.php`.
- Nav (`resources/views/layouts/app.blade.php`) : lien "Directions" ajouté dans le bloc SaaS-only existant, à la suite de "Inspections", même garde `isSaasAdmin()` (pas de clé `ability`/`model`).

## 3. Table `establishments` (impact indirect)

`inspection_code` (FK vers `inspections.code`, ajouté au chantier précédent) devient **`inspection_id`** (bigint unsigned nullable, FK vers `inspections.id`, `nullOnDelete`) — nécessaire car `codeiep` n'est plus stable dans le temps (librement modifiable), donc impropre à servir de cible de clé étrangère durable.

- Migration : `dropForeign`/`dropColumn('inspection_code')` puis `foreignId('inspection_id')->nullable()->constrained('inspections')->nullOnDelete()`.
- `Establishment::inspection()` : `belongsTo(Inspection::class, 'inspection_code', 'code')` → `belongsTo(Inspection::class)` (convention standard, `inspection_id`).
- `Livewire\Establishments\Index` et `Livewire\Staff\ManageOrganization` : la propriété/le select `inspection_code` devient `inspection_id` ; `value` = `$inspection->id`, libellé affiché = `"{$inspection->codeiep} — {$inspection->inspection_name}"` ; validation `exists:inspections,code` → `exists:inspections,id`.

## 4. Écran `Livewire\Inspections\Index` (mise à jour)

Le pattern "clé verrouillée en édition" (hérité de l'ancien `code` = PK) disparaît :

- `editingCode`/`Inspection::findOrFail($code)` → `editingId`/`Inspection::findOrFail($id)`.
- Formulaire étendu : `codeiep`, `inspection_name`, `address`, `phone_number`, `email`, `location`, et un select "Direction de rattachement" (`directions` triées par `direction_name`, valeur = `uid_serveur`, optionnel).
- `codeiep` n'est plus `@disabled` en édition (librement modifiable, cf. §1).
- Validation `save()` :
  - `codeiep` : `required|string|max:6`, `Rule::unique('inspections','codeiep')->ignore($this->editingId)`
  - `inspection_name` : `required|string|max:50`
  - `address` : `nullable|string|max:50`
  - `phone_number` : `nullable|string|max:20`
  - `email` : `nullable|email|max:50`
  - `location` : `nullable|string|max:50`
  - `uid_direction` : `nullable|exists:directions,uid_serveur`
- `save()` fait un `update($data)`/`create($data)` classique sur l'ensemble des champs métier (plus de restriction "ne jamais réécrire la clé", puisque aucun champ n'est verrouillé).

## 5. Seeder

`database/seeders/DatabaseSeeder.php` : création de 2-3 `directions` d'exemple avant les `inspections`, puis les 3 inspections d'exemple recréées avec les nouveaux champs (`codeiep`, `inspection_name`, `address`, `phone_number`, `email`, `location`, `uid_direction` pointant vers une direction d'exemple pour au moins une inspection).

## 6. Migrations (aucune existante modifiée, toutes nouvelles)

1. `..._add_inspection_direction_prefixes_to_uid_server_counters_table.php` — insère les lignes `217`/`218` dans `uid_server_counters`.
2. `..._rebuild_inspections_table.php` — drop `code`/`libelle`, add `id` + colonnes Syncable + nouveaux champs métier (`codeiep` unique, `inspection_name`, `address`, `phone_number`, `email`, `location`, `uid_direction` nullable).
3. `..._create_directions_table.php` — création complète.
4. `..._replace_inspection_code_with_inspection_id_on_establishments_table.php` — drop FK/colonne `inspection_code`, add `inspection_id`.

## 7. Tests

- `tests/Feature/Domain/Establishments/DirectionTest.php` ou extension de `InspectionTest.php` existant si présent : `Syncable` (uid_local/uid_serveur générés, préfixes 217/218).
- `tests/Feature/Livewire/Inspections/IndexTest.php` (mise à jour) : création avec tous les champs, modification **y compris `codeiep`** (plus de verrouillage — remplace le test "code reste verrouillé"), suppression, rattachement à une direction, accès refusé pour un directeur.
- `tests/Feature/Livewire/Directions/IndexTest.php` (nouveau) : même structure de tests que `Inspections/IndexTest.php`.
- `tests/Feature/Livewire/Establishments/IndexTest.php` et `ManageOrganizationTest.php` : adaptation des assertions `inspection_code` → `inspection_id`.

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : écrans Inspections et Directions (CRUD complet, rattachement, `codeiep` modifiable), écran Establishments (select d'inspection fonctionnel avec les nouveaux libellés).
5. Commit puis mise à jour de la mémoire projet.
