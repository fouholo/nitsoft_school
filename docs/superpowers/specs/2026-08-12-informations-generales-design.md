# Table des informations générales

## Contexte

Demande explicite de l'utilisateur : "On va créer une table des informations générales." Après clarification (AskUserQuestion), il s'agit d'une nouvelle table de référence globale — comme `inspections`/`directions`/`nationalites` — mais à **une seule ligne** (singleton), contenant deux réglages plateforme : le nom du ministère de tutelle et l'année scolaire en cours. Contrairement à `inspections`/`directions` (chantiers précédents), ce n'est pas une liste à gérer en CRUD complet (créer/modifier/supprimer plusieurs entrées), mais un formulaire d'édition unique.

Décisions actées avec l'utilisateur en clarification :
1. **Portée** : nouvelle table de référence globale (pas un paramètre par établissement, pas une extension de `establishments`).
2. **Structure** : une seule ligne en base (singleton), pas une liste.
3. **`annee_scolaire_courante`** : simple champ texte libre (ex. `"2025-2026"`), **sans lien** avec la table `school_years` existante (celle-ci reste scopée par établissement, TenantScoped — un concept différent).
4. **Champs** : `nom_ministere` et `annee_scolaire_courante` suffisent, rien d'autre pour ce chantier.
5. **Gouvernance** : réservé au Super Admin SaaS, même principe que `inspections`/`directions` (`Gate::before` bypass SaaS admin, Policy tout-`false` sinon).

## 1. Migration

Nouvelle table `general_information` : `id` (PK auto-increment, Eloquent standard), `nom_ministere` (string, nullable), `annee_scolaire_courante` (string, nullable), `timestamps()`. Pas de colonnes `Syncable` (`uid_local`/`uid_serveur`/`device_id`/`client_updated_at`) : ce n'est pas une entité métier multipliée et synchronisée depuis des devices offline, mais un réglage plateforme unique — le système uid n'a pas de sens ici.

## 2. Modèle `GeneralInformation`

`app/Domain/Establishments/Models/GeneralInformation.php` (même dossier que `Inspection`/`Direction`, même famille conceptuelle de données de référence globales) : `$fillable = ['nom_ministere', 'annee_scolaire_courante']`. Méthode statique `current(): self` qui fait `static::firstOrCreate([], ['nom_ministere' => null, 'annee_scolaire_courante' => null])` — garantit qu'une ligne existe toujours sans dépendre d'un seeder, et que la table ne contient jamais qu'une seule ligne (aucun autre point d'entrée de création dans l'application).

## 3. `app/Policies/GeneralInformationPolicy.php`

Deux méthodes seulement (`view`, `update`), toutes deux `false` — pas de `viewAny`/`create`/`delete` (singleton, jamais listé/créé/supprimé). Même principe que `InspectionPolicy`/`DirectionPolicy` : seul le bypass Super Admin SaaS (`Gate::before`, `AppServiceProvider`) donne accès.

## 4. Écran `Livewire\GeneralInformation\Edit`

Composant Livewire unique (pas de pattern liste+formulaire comme `Inspections\Index`) :
- `mount()` : `$this->record = GeneralInformation::current(); $this->authorize('view', $this->record);` puis pré-remplit `nom_ministere`/`annee_scolaire_courante`.
- `save()` : valide (`nullable|string|max:255` pour les deux champs), `$this->authorize('update', $this->record)`, `$this->record->update($data)`.
- Vue `resources/views/livewire/general-information/edit.blade.php` : formulaire à deux champs, un bouton "Enregistrer", pas de liste/tableau.

## 5. Route et navigation

`routes/general-information.php` (nouveau) : `Route::get('/informations-generales', Edit::class)->name('general-information.edit');`, requis dans `routes/web.php`. Lien "Informations générales" ajouté dans le bloc nav SaaS-only existant (`resources/views/layouts/app.blade.php`), après "Directions", même garde `isSaasAdmin()`.

## 6. Tests

`tests/Feature/Livewire/GeneralInformation/EditTest.php` :
- Un Super Admin modifie `nom_ministere`/`annee_scolaire_courante` et les retrouve en base.
- Un directeur d'établissement reçoit `assertForbidden()` sur l'écran.

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : accès `/informations-generales` en Super Admin, modification des deux champs, persistance après rechargement ; accès refusé pour un directeur.
5. Commit puis mise à jour de la mémoire projet.
