# Recherche UID sur le tableau de bord (élèves)

*(Validé par l'utilisateur le 2026-08-22.)*

## Contexte

Chaque enregistrement synchronisable porte un `uid_serveur` (12 caractères : préfixe à 3 chiffres par type d'entité + 9 chiffres de séquence — voir `docs/superpowers/specs/2026-08-10-uid-local-serveur-prefixe-design.md`), imprimable sous forme de code-barres. L'utilisateur veut une zone de recherche sur le tableau de bord permettant de saisir ou scanner cet UID pour être redirigé directement vers la fiche correspondante — sans passer par les écrans de liste.

## Périmètre (validé avec l'utilisateur)

Élèves uniquement pour ce chantier (préfixe `221` → `students.show`). Le personnel (`220`) et les tuteurs (`223`) sont explicitement hors périmètre : le personnel n'a pas de résolution triviale (une fiche `staff.show` exige un établissement + une ligne `establishment_user`, pas juste l'utilisateur), et les tuteurs n'ont aujourd'hui aucune page de détail (juste une liste). La logique de dispatch reste néanmoins écrite de façon extensible (`match` sur le préfixe) pour accueillir ces types plus tard sans réécriture.

## Composant

Nouveau `App\Livewire\Dashboard\UidSearchWidget`, intégré au tableau de bord via `<livewire:dashboard.uid-search-widget />`, juste sous la ligne d'en-tête (« Connecté en tant que... ») et au-dessus de la grille de cartes de statistiques — uniquement dans la branche où un établissement courant est lié (même condition que le reste du contenu du tableau de bord).

```php
class UidSearchWidget extends Component
{
    public string $uid = '';
    public ?string $errorMessage = null;

    public function search(): void
    {
        $uid = trim($this->uid);

        if ($uid === '') {
            return;
        }

        $prefix = substr($uid, 0, 3);

        if (! preg_match('/^\d{12}$/', $uid)) {
            $this->fail(__('Code non reconnu.'));
            return;
        }

        match ($prefix) {
            '221' => $this->searchStudent($uid),
            default => $this->fail(__("Ce type de code n'est pas encore pris en charge.")),
        };
    }

    private function searchStudent(string $uid): void
    {
        $student = Student::where('uid_serveur', $uid)->first();

        if ($student === null) {
            $this->fail(__('Aucun élève trouvé avec ce code.'));
            return;
        }

        $this->redirectRoute('students.show', $student, navigate: true);
    }

    private function fail(string $message): void
    {
        $this->errorMessage = $message;
        $this->reset('uid');
        $this->dispatch('uid-search-failed');
    }
}
```

`Student` porte déjà le trait `TenantScoped` (scope global sur `establishment_id` courant) : un élève d'un autre établissement n'est simplement pas trouvé, sans code de portée supplémentaire à écrire.

## Interface

Un seul champ texte dans un formulaire, focus automatique à l'ouverture du tableau de bord (attribut HTML `autofocus`) :

```blade
<div x-data class="mt-6 rounded-2xl border border-stone-200 bg-white p-5" x-on:uid-search-failed.window="$nextTick(() => $refs.uidInput.focus())">
    <label class="text-sm font-medium text-stone-700">{{ __('Rechercher un élève (UID / code-barres)') }}</label>
    <form wire:submit="search" class="mt-2">
        <input
            type="text"
            wire:model="uid"
            x-ref="uidInput"
            autofocus
            autocomplete="off"
            placeholder="{{ __('Scannez ou saisissez un code...') }}"
            class="block w-full rounded-lg border-stone-300 text-sm"
        >
    </form>
    @if ($errorMessage)
        <p class="mt-2 text-sm text-red-600">{{ $errorMessage }}</p>
    @endif
</div>
```

Une douchette code-barres se comporte comme un clavier rapide suivi d'un `Entrée` — la soumission du `<form>` sur Entrée couvre ce cas nativement, aucune intégration JS spécifique au matériel n'est nécessaire.

Après une recherche infructueuse : le champ est vidé (`$this->reset('uid')`) et un événement navigateur (`uid-search-failed`) redonne le focus au champ via Alpine, pour enchaîner un nouveau scan sans clic manuel. En cas de succès, redirection immédiate — pas de refocus nécessaire.

## Validation du format

Regex stricte `^\d{12}$` (12 chiffres) avant même de regarder le préfixe — un format invalide donne directement « Code non reconnu. » sans toucher la base de données.

## i18n

Suit la stratégie établie : `__('Phrase française exacte')`, clés ajoutées à `lang/en.json`/`lang/ar.json`.

## Tests

`tests/Feature/Livewire/Dashboard/UidSearchWidgetTest.php` :
- UID élève valide et existant → redirection vers `students.show` avec le bon élève.
- UID élève valide mais inexistant → message d'erreur, pas de redirection, champ vidé.
- UID d'un élève d'un **autre** établissement → traité comme introuvable (prouve le scope tenant automatique).
- UID mal formé (mauvaise longueur, caractères non numériques) → message « Code non reconnu. », pas de requête base de données déclenchée par erreur.
- UID avec un préfixe valide mais non pris en charge (ex. `222...`, personnel) → message dédié, pas de redirection.
- Soumission avec champ vide → aucun effet (pas d'erreur affichée).

## Vérification

1. `php artisan migrate:fresh --seed` (aucune migration requise pour ce chantier — pas de nouvelle colonne).
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : recherche d'un élève seedé par son `uid_serveur`, vérification du focus automatique et du comportement d'erreur.
5. Commit puis mise à jour de la mémoire projet si un piège notable est rencontré.
