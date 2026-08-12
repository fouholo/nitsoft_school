# Logo sur la table `directions`

## Contexte

La table `directions` (créée au chantier précédent, `docs/superpowers/specs/2026-08-11-inspections-directions-design.md`) n'a pas de logo. Demande explicite de l'utilisateur d'en ajouter un. Précédent direct dans ce projet : `Establishment.logo_path` (chantier `2026-08-11-champs-etablissements-design.md`) — même mécanisme d'upload à reprendre à l'identique.

Portée confirmée avec l'utilisateur (clarification) : le logo est affiché **uniquement** sur l'écran de gestion `/directions`. Aucun autre écran (inspections, établissements) ni document PDF (bulletins, reçus) n'est concerné par ce chantier.

## 1. Migration

Nouvelle migration (aucune migration existante modifiée, convention constante du projet) : ajoute `logo_path` (string, nullable) à `directions`.

## 2. Modèle `Direction`

`app/Domain/Establishments/Models/Direction.php` : `logo_path` ajouté à `$fillable`.

## 3. `Livewire\Directions\Index`

Même mécanisme exact que `Livewire\Establishments\Index` (gabarit de référence, lui-même repris de `Students\Index`) :
- Trait `WithFileUploads`.
- Propriété `public ?TemporaryUploadedFile $logo = null;` et `public string $existingLogoPath = '';` (aperçu en édition).
- `edit()` : `$this->existingLogoPath = (string) $direction->logo_path;`.
- `save()` : validation `'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024']` ; si un nouveau fichier est fourni, supprime l'ancien (`Storage::disk('public')->delete($direction->logo_path)` si présent) puis stocke le nouveau (`$this->logo->store('directions-logos', 'public')`) dans `logo_path`.
- `resetForm()` : `logo`/`existingLogoPath` réinitialisés.

## 4. Vue

`resources/views/livewire/directions/index.blade.php` : champ fichier dans le formulaire + aperçu de l'image existante (`<img src="{{ Storage::url($existingLogoPath) }}">` si présent) — même gabarit visuel que le champ logo de `livewire/establishments/index.blade.php`.

## 5. Tests

`tests/Feature/Livewire/Directions/IndexTest.php` : deux nouveaux tests, gabarit repris de `Establishments/IndexTest` —
- upload du logo à la création (`Storage::fake('public')`, `UploadedFile::fake()->image(...)->size(...)`, assert `logo_path` non nul et fichier existant sur le disque).
- remplacement du logo en édition supprime l'ancien fichier (assert `Storage::disk('public')->assertMissing(...)` sur l'ancien chemin, `assertExists(...)` sur le nouveau).

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : upload d'un logo à la création d'une direction, remplacement en édition, aperçu affiché.
5. Commit puis mise à jour de la mémoire projet.
