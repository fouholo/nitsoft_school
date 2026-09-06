# Restriction d'accès aux coefficients par matière (secondaire)

*(Validé par l'utilisateur le 2026-09-06.)*

## Contexte

L'écran « Coefficients par matière » (`App\Livewire\Academics\SubjectCoefficients\Index`, secondaire uniquement) est aujourd'hui visible par **tout membre actif de l'établissement** : `SubjectCoefficientPolicy::viewAny()` ne vérifie que l'appartenance à l'établissement et le cycle secondaire, sans filtre de rôle. La modification (`create`/`update`/`delete`) est en revanche déjà correctement restreinte à fondateur/directeur/gestionnaire (`isAdminOfCurrentEstablishment()`) ou éducateur (`RolePermissions::can($role, 'subject_coefficients.write')`).

Demande explicite de l'utilisateur : la saisie des coefficients ne doit apparaître que pour ces 4 rôles (fondateur, directeur, éducateur, gestionnaire) — caissier et enseignant ne doivent plus voir l'écran du tout, pas seulement en être empêchés de modifier.

## Périmètre

- Un seul fichier métier touché : `App\Policies\SubjectCoefficientPolicy`.
- Aucun changement de modèle de données, aucun changement de vue Blade — le lien de navigation « Coefficients par matière » (`resources/views/layouts/app.blade.php`) est déjà gated sur `ability: 'viewAny'`, il se masque donc automatiquement pour les rôles exclus une fois la Policy corrigée.
- `view()` (autorisation sur une instance unique de `SubjectCoefficient`) n'est appelée nulle part dans le code actuel (vérifié) — laissée inchangée, hors périmètre de cette demande.
- Le module `Arabic\SubjectCoefficients` (filière arabe, policy distincte `ArabicSubjectCoefficientPolicy`) n'est pas concerné par cette demande.

## Changement

```php
class SubjectCoefficientPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        if (! $this->canManage($user)) {
            return false;
        }

        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return $establishment?->isSecondaire() ?? false;
    }

    public function view(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->belongsToSameEstablishment($user, $subjectCoefficient->establishment_id)
            && $this->canManage($user);
    }

    public function delete(User $user, SubjectCoefficient $subjectCoefficient): bool
    {
        return $this->update($user, $subjectCoefficient);
    }

    private function canManage(User $user): bool
    {
        return $this->isAdminOfCurrentEstablishment($user)
            || RolePermissions::can($user->currentRole(), 'subject_coefficients.write');
    }
}
```

`canManage()` factorise la règle de rôle déjà appliquée à `create()`/`update()` (fondateur/directeur/gestionnaire via `isAdminOfCurrentEstablishment()`, éducateur via la matrice) et l'applique désormais aussi à `viewAny()`. `create()` perd sa vérification d'appartenance à l'établissement courant (elle n'existait pas avant non plus — `isAdminOfCurrentEstablishment`/`currentRole()` opèrent déjà sur le tenant courant, pas de régression). `view()` reste inchangée (hors périmètre).

## Tests

Nouveau fichier `tests/Feature/Policies/SubjectCoefficientPolicyTest.php` :
- `viewAny` autorisé pour fondateur, directeur, gestionnaire, éducateur (établissement secondaire).
- `viewAny` refusé pour caissier et enseignant.
- `viewAny` refusé même pour un directeur si l'établissement courant est préscolaire/primaire (règle de cycle déjà existante, non-régression).
- Cloisonnement multi-établissement : un directeur d'un autre établissement ne peut pas consulter/gérer les coefficients de celui-ci.

## Vérification

1. Suite Pest complète, PHPStan, Pint scopés au fichier touché et au nouveau test.
2. Vérification manuelle Playwright (comptes de test dédiés, jamais de compte réel) : connexion caissier puis enseignant sur un établissement secondaire → lien « Coefficients par matière » absent du menu et route directe renvoie 403 ; connexion éducateur → lien présent, écran accessible et modifiable comme avant.
3. Aucune migration de données — changement de Policy uniquement.
