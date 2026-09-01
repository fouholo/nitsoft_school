# Prénom et pseudo pour le personnel et les administrateurs SaaS

*Design approuvé le 2026-09-01.*

## Contexte

La table `users` (partagée par tout le monde : personnel d'établissement, administrateurs SaaS, parents via le portail) ne contient aujourd'hui qu'un champ `name` unique — un nom complet non structuré. On ajoute deux nouveaux champs, `first_name` (prénom) et `pseudo` (identifiant alternatif de connexion), pour le personnel et les administrateurs SaaS uniquement. Le portail parents (modèle `Guardian`, qui a déjà son propre `first_name`/`last_name` séparé de `users`) n'est pas concerné par ce chantier.

**Fait déjà vérifié dans le code, à ne pas redériver :**
- Quatre écrans créent des `User` dans le périmètre concerné :
  - `App\Livewire\Staff\Index::create()` (création rapide par un directeur/gestionnaire)
  - `App\Livewire\Staff\Register::register()` (auto-inscription fondateur/directeur/gestionnaire via UID établissement)
  - `App\Livewire\SaasAdmins\Register::register()` (bootstrap du premier admin SaaS Principal, page qui se ferme d'elle-même une fois un Principal créé)
  - `App\Livewire\SaasAdmins\Index::create()` (création d'un admin SaaS Second)
- `App\Livewire\Auth\Login.php` est l'écran de connexion unique et partagé par tous les types de comptes (personnel, admins SaaS, parents).
- `App\Livewire\Auth\Register.php` (portail parents) construit déjà `users.name` par concaténation (`trim("{$first_name} {$last_name}")`) à partir des champs du modèle `Guardian` — ce flux n'est pas modifié par ce chantier.
- 9 points d'affichage lisent directement `$user->name` : `resources/views/layouts/app.blade.php`, `resources/views/livewire/staff/{index,show,manage-organization}.blade.php`, `resources/views/livewire/saas-admins/index.blade.php`, `resources/views/livewire/foundations/show.blade.php`, `App\Livewire\Staff\Show.php` (titre de page), `App\Domain\Billing\Services\FinancialSummaryService.php`, `App\Http\Controllers\Academics\TimetableTeacherPdfController.php`.
- Convention de nommage déjà établie ailleurs dans le code (`Student`, `Guardian`) : `first_name` / `last_name`, jamais `prenom`/`nom`.

## 1. Migration `users`

Nouvelle migration (style `2026_08_22_010000_add_identity_fields_to_users_table.php`) :
```php
Schema::table('users', function (Blueprint $table): void {
    $table->string('first_name')->nullable()->after('name');
    $table->string('pseudo')->nullable()->unique()->after('first_name');
});
```

**Sémantique de `name`** : à partir de ce chantier, `name` est interprété comme "nom de famille" pour tout compte créé via les 4 écrans du périmètre. Les comptes existants ne sont **pas** migrés : `first_name` reste vide, `name` garde sa valeur actuelle (souvent un nom complet non structuré) — pas de tentative de split automatique (peu fiable sur noms composés/particules). L'affichage retombe naturellement sur `name` seul tant que `first_name` est vide (cf. §4).

**`pseudo`** : nullable au niveau base (les comptes existants et les parents n'en ont pas), mais rendu obligatoire au niveau validation dans les 4 formulaires du périmètre (cf. §2). Contrainte unique en base. Format : `[a-z0-9._-]`, 3 à 30 caractères, comparaison insensible à la casse.

`App\Models\User::$fillable` : ajouter `first_name` et `pseudo`.

## 2. Formulaires de création

Dans chacun des 4 écrans listés en contexte, ajouter deux champs au formulaire et à la validation :

```php
'first_name' => ['required', 'string', 'max:255'],
'pseudo' => [
    'required', 'string', 'min:3', 'max:30',
    'regex:/^[a-z0-9._-]+$/i',
    'unique:users,pseudo',
],
```

Le pseudo est stocké tel que saisi (préservation de la casse pour l'affichage), la comparaison à la connexion se fait en case-insensitive (cf. §3). Chaque `User::create([...])` du périmètre inclut désormais `first_name` et `pseudo` dans le tableau de données.

Aucun changement de comportement pour les autres créations de `User` du code (ex. portail parents, création de compte parent via `Students\Index`) — elles restent hors périmètre, `first_name`/`pseudo` non renseignés.

## 3. Connexion

`App\Livewire\Auth\Login.php` : le champ `email` devient `identifiant` (`string`, requis, sans règle `email` stricte).

Résolution en deux temps — d'abord retrouver l'utilisateur par email ou pseudo (insensible à la casse pour le pseudo, via une clause `whereRaw('LOWER(pseudo) = ?', [mb_strtolower($identifiant)])` plutôt qu'une égalité directe, pour ne pas dépendre de la collation MySQL réelle du serveur), puis tenter `Auth::attempt` avec l'email résolu (`Auth::attempt` a toujours besoin d'une colonne pour identifier l'utilisateur ; on utilise systématiquement `email` en interne une fois l'utilisateur retrouvé) :

```php
public function login(): void
{
    $data = $this->validate([
        'identifiant' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $isEmail = filter_var($data['identifiant'], FILTER_VALIDATE_EMAIL) !== false;

    $user = $isEmail
        ? User::where('email', $data['identifiant'])->first()
        : User::whereRaw('LOWER(pseudo) = ?', [mb_strtolower($data['identifiant'])])->first();

    if ($user === null || ! Auth::attempt(['email' => $user->email, 'password' => $data['password']], $this->remember)) {
        throw ValidationException::withMessages([
            'identifiant' => __('Ces identifiants ne correspondent à aucun compte.'),
        ]);
    }
}
```

Les comptes sans `pseudo` (parents, comptes existants) continuent de se connecter via le chemin `email`, sans changement de comportement.

## 4. Affichage du nom complet

Nouvelle méthode sur `App\Models\User` :
```php
public function fullName(): string
{
    return $this->first_name !== null && $this->first_name !== ''
        ? trim("{$this->first_name} {$this->name}")
        : $this->name;
}
```

Les 9 points d'affichage identifiés en contexte sont mis à jour pour appeler `$user->fullName()` au lieu de `$user->name` brut. Aucun autre changement de mise en page.

## 5. Tests

- `UserTest` (ou équivalent) : `fullName()` avec et sans `first_name`.
- Pour chacun des 4 formulaires : `first_name`/`pseudo` requis, format `pseudo` invalide rejeté, unicité `pseudo` (deux créations avec le même pseudo → erreur sur la seconde), création réussie peuple bien les deux colonnes.
- `LoginTest` : connexion réussie par email (comportement existant préservé), connexion réussie par pseudo, connexion par pseudo avec casse différente de celle enregistrée, échec sur pseudo inexistant, un compte sans pseudo (ex. parent créé via le portail) reste connectable par email uniquement.
- Un test ciblé par point d'affichage modifié n'est pas nécessaire si les vues se contentent de déléguer à `fullName()` déjà testée unitairement ; conserver toutefois les assertions déjà existantes des tests Feature concernés (ne pas les casser) et ajouter une assertion `assertSee` sur le prénom affiché dans au moins un test représentatif (ex. `Staff\ShowTest` ou `Staff\IndexTest`) pour couvrir le câblage vue → modèle de bout en bout.

## Hors périmètre

- Portail parents (`Auth\Register.php`, modèle `Guardian`) : aucune modification.
- Pas de split automatique des `name` existants.
- Pas de champ `pseudo` pour les comptes déjà existants ni pour les parents.
