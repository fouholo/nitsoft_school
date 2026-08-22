# Fiche personnel enrichie — conception

*(Validé par l'utilisateur le 2026-08-22.)*

## Contexte

Aujourd'hui, le module Staff (`app/Livewire/Staff/Index.php` + `resources/views/livewire/staff/index.blade.php`) se limite à un tableau (nom, rôle, statut) et un formulaire de création à 3 champs (nom, e-mail, rôle applicatif). Il n'existe aucune page de fiche détaillée par membre du personnel — l'utilisateur a signalé que les informations enregistrées sur le personnel sont incomplètes.

Le modèle `User` (`app/Models/User.php`) est partagé par tous les rôles (personnel d'établissement, tuteurs via le portail parent, admins SaaS) et peut être rattaché à plusieurs établissements via la table pivot `establishment_user` (`App\Domain\Establishments\Models\EstablishmentUserPivot`) — un enseignant peut par exemple travailler dans deux écoles avec un rôle différent dans chacune.

## Périmètre

Personnel d'établissement uniquement (directeur, gestionnaire, enseignant, caissier, éducateur, fondateur). Les tuteurs (portail parent) et admins SaaS restent hors périmètre de ce chantier.

## Décision d'architecture : identité vs emploi

Les nouveaux champs se répartissent selon qu'ils décrivent **la personne** (valable partout où elle travaille) ou **la relation d'emploi** (spécifique à un établissement) :

- **Sur `users`** (identité civile + coordonnées + photo, globales à la personne) :
  - `gender` (string, nullable) — enum `homme`/`femme`
  - `birth_date` (date, nullable)
  - `birth_place` (string, nullable)
  - `nationality` (string, nullable)
  - `city` (string, nullable) — ville/commune de résidence
  - `photo_path` (string, nullable) — chemin de stockage, disque `public`, dossier `staff-photos`

- **Sur `establishment_user`** (données professionnelles, propres à chaque affectation) :
  - `matricule` (string, nullable)
  - `job_title` (string, nullable) — intitulé de poste libre, distinct du rôle applicatif (`role`)
  - `hired_at` (date, nullable)
  - `education_level` (string, nullable) — diplôme/niveau d'étude, texte libre (pas d'enum : trop de valeurs possibles, BEPC/BAC/Licence/Master/etc.)

Ce découpage a été explicitement validé par l'utilisateur : si la même personne travaille dans deux écoles, elle peut avoir un matricule et une date d'embauche différents dans chacune, mais une seule date de naissance.

Tous les nouveaux champs sont `nullable` (les comptes existants n'ont pas ces informations — pas de backfill).

## Modèle `Gender`

Nouvel enum `App\Domain\Establishments\Enums\Gender` (même dossier que `SaasAdminType`, `EstablishmentType` — concepts liés au personnel/établissement) :

```php
enum Gender: string
{
    case Homme = 'homme';
    case Femme = 'femme';

    public function label(): string
    {
        return match ($this) {
            self::Homme => __('Homme'),
            self::Femme => __('Femme'),
        };
    }
}
```

`User::casts()` ajoute `'gender' => Gender::class, 'birth_date' => 'date'`. `EstablishmentUserPivot::$casts` ajoute `'hired_at' => 'date'`.

## Nouvelle page : Fiche personnel

Nouveau composant `App\Livewire\Staff\Show`, route `GET /etablissements/{establishment}/staff/{pivot}` → `staff.show` (implicit binding sur `EstablishmentUserPivot`, cohérent avec `staff.index` déjà scopé par établissement). Le tableau de `staff/index.blade.php` : le nom du membre devient un lien `wire:navigate` vers cette page.

**Sections de la fiche** :
1. En-tête : photo (ou avatar par défaut), nom, rôle applicatif (`User::roleLabel()`), statut actif/inactif.
2. Identité civile : genre, date de naissance, lieu de naissance, nationalité.
3. Coordonnées : ville/commune.
4. Données professionnelles (pour cet établissement) : matricule, fonction/poste, date d'embauche, diplôme.

Upload photo : même gabarit que le logo `Direction` (`app/Livewire/Directions/Index.php`, chantier `56a9db1`) — `WithFileUploads`, `?TemporaryUploadedFile $photo`, `existingPhotoPath`, suppression de l'ancien fichier avant `store()` sur `staff-photos`.

## Droits d'édition

Nouvelle méthode `view()` sur `EstablishmentUserPivotPolicy` (les méthodes `update`/`delete` existent déjà pour activer/désactiver) :

```php
public function view(User $user, EstablishmentUserPivot $target): bool
{
    return $target->user_id === $user->id
        || ($user->isLocalAdminOf($target->establishment)
            && RolePermissions::can($user->roleFor($target->establishment_id), 'staff.update'));
}
```

Dans le composant `Show` :
- **Accès à la page** : le membre lui-même (`$pivot->user_id === Auth::id()`) OU un admin avec les droits `staff.update` (réutilise `Gate::allows('update', $pivot)`, déjà utilisé par `Index::activate/deactivate`).
- **Identité civile + coordonnées + photo** : modifiables par le membre lui-même OU par l'admin.
- **Données professionnelles** (matricule, poste, embauche, diplôme) : modifiables par l'admin uniquement — un membre qui n'est pas admin voit ces champs en lecture seule, même sur sa propre fiche.
- Un tiers (ni admin, ni le membre concerné) reçoit un 403 sur la route `staff.show`.

## Validation

```php
'gender' => ['nullable', Rule::enum(Gender::class)],
'birth_date' => ['nullable', 'date', 'before:today'],
'birth_place' => ['nullable', 'string', 'max:255'],
'nationality' => ['nullable', 'string', 'max:255'],
'city' => ['nullable', 'string', 'max:255'],
'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
// admin uniquement :
'matricule' => ['nullable', 'string', 'max:50'],
'job_title' => ['nullable', 'string', 'max:255'],
'hired_at' => ['nullable', 'date'],
'education_level' => ['nullable', 'string', 'max:255'],
```

Pas de contrainte d'unicité sur `matricule` (pas demandé, YAGNI — pourra être ajoutée si un besoin de numérotation RH stricte apparaît).

## i18n

Ce chantier suit la stratégie établie (`[[i18n_json_key_translation_pattern]]` en mémoire projet) : `__('Phrase française exacte')`, clés ajoutées à `lang/en.json`/`lang/ar.json`, classes directionnelles RTL (`ms-`/`me-`, `text-start`/`text-end`) sur les nouvelles vues.

## Tests

`tests/Feature/Livewire/Staff/ShowTest.php` :
- Un admin (directeur/gestionnaire) voit et modifie identité + pro d'un membre de son établissement.
- Le membre lui-même (rôle non-admin, ex. enseignant) voit sa fiche et modifie identité/coordonnées/photo ; une tentative de modifier un champ pro est ignorée/rejetée.
- Un tiers non-admin, non concerné → 403.
- Upload/remplacement de photo (gabarit `Directions/IndexTest.php`).
- Validation : `birth_date` future rejetée, `gender` hors enum rejeté.

`tests/Feature/Livewire/Staff/IndexTest.php` : ajout d'une assertion que le nom du membre est un lien vers `staff.show`.

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : ouverture de la fiche en tant qu'admin (édition complète), en tant que membre concerné (édition partielle), upload photo.
5. Commit puis mise à jour de la mémoire projet.
