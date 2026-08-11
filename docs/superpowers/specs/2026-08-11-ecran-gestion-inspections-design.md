# Écran de gestion des inspections

**Date** : 2026-08-11
**Statut** : Approuvé

## Contexte

La table `inspections` (code + libellé, référence globale des circonscriptions d'inspection pédagogique) a été créée dans le chantier précédent (`docs/superpowers/specs/2026-08-11-champs-etablissements-design.md`), avec le même statut que `nationalites`/`levels`/`series` : peuplée uniquement par seeder, pas d'écran de gestion. L'utilisateur demande maintenant cet écran.

## Décisions validées avec l'utilisateur

1. **Réservé au Super Admin SaaS**, même gouvernance que `Foundations`/`Establishments` — cohérent avec la décision déjà actée pour `nationalites`/`levels` : une liste de référence partagée par toute la plateforme n'est pas une ressource d'établissement.
2. **`code` verrouillé en modification** une fois l'inspection créée (c'est la clé primaire, non auto-incrémentée) — même précaution que `Discounts\Index` sur son champ élève, pour éviter qu'un changement de clé naturelle ne crée un enregistrement orphelin ailleurs (`establishments.inspection_code` y fait référence).

## Design

### 1. `InspectionPolicy`

Nouvelle Policy, même gabarit que `FoundationPolicy`/`EstablishmentPolicy` : `viewAny`, `create`, `update`, `delete` retournent tous `false` — seul le bypass `Gate::before` (SaaS admin) passe.

### 2. `Livewire\Inspections\Index`

Même gabarit que `Livewire\Foundations\Index` :
- Propriétés : `showForm` (bool), `editingCode` (`?string`, null en création), `code` (string), `libelle` (string).
- `mount()` : `authorize('viewAny', Inspection::class)`.
- `create()` : `authorize('create', Inspection::class)`, reset du formulaire, ouvre le formulaire.
- `edit(string $code)` : charge `code`/`libelle`, `authorize('update', $inspection)`.
- `save()` : validation `code` (requis, max 10, unique sauf en édition — ignoré via la clé primaire courante) et `libelle` (requis, max 100). En édition, seul `libelle` est mis à jour (`Inspection::where('code', $this->editingCode)->update(['libelle' => ...])`) — `code` n'est jamais réécrit, même si le champ était modifiable côté formulaire (défense en profondeur : le champ est de toute façon désactivé côté vue). En création, `Inspection::create([...])`.
- `delete(string $code)` : `authorize('delete', $inspection)`, suppression directe (pas de soft delete sur ce modèle, cohérent avec l'absence de trait particulier).
- `render()` : liste triée par `libelle`.

Vue `resources/views/livewire/inspections/index.blade.php` — tableau (code/libellé/actions) + formulaire (champ `code` avec `@disabled($editingCode !== null)`, champ `libelle`), même structure visuelle que `livewire/foundations/index.blade.php`.

### 3. Route et navigation

`routes/inspections.php` (nouveau fichier, gabarit `routes/establishments.php`) :
```php
Route::prefix('inspections')->name('inspections.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
});
```
Enregistré dans `routes/web.php` à côté des autres `require`. Nav (`resources/views/layouts/app.blade.php`) : lien "Inspections" ajouté au même bloc `if (auth()->user()->isSaasAdmin())` que "Groupes scolaires"/"Établissements", avec `ability`/`model` (`viewAny`, `Inspection::class`) pour le filtre de menu déjà en place.

### 4. Tests

`tests/Feature/Livewire/Inspections/IndexTest.php`, gabarit `Establishments\IndexTest` : un Super Admin crée/modifie (le libellé)/supprime une inspection ; le champ `code` reste inchangé après une tentative de modification en édition ; un directeur d'établissement reçoit `assertForbidden()`.

## Hors périmètre (explicitement écarté)

- Aucune modification du champ `code` après création (contournement délibérément impossible, cf. décision §2) — si un renommage de code s'avère nécessaire un jour, ce sera une opération manuelle (tinker/migration), pas un flux applicatif.
