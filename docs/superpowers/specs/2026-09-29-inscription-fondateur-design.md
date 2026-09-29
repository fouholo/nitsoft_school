# Inscription : page de choix et création d'école par un fondateur

*(Validé par l'utilisateur le 2026-09-29.)*

## Contexte

- La page de connexion propose « Vous êtes parent d'élève ? S'inscrire », qui mène à `/register` (`App\Livewire\Auth\Register`) : un formulaire qui crée un `User` + un `Guardian`, réservé aux parents.
- `/staff/register` (`App\Livewire\Staff\Register`) permet déjà à un fondateur, directeur ou gestionnaire de **rejoindre** un établissement ou une fondation existant via son UID (`uid_serveur`). Aucun lien de l'application n'y mène.
- Les établissements et fondations ne sont créés que par un administrateur SaaS (`Establishments\Index`, `Foundations\Index`). Un fondateur d'un groupe scolaire peut ensuite ajouter des écoles à son groupe depuis `/staff/organisation` (`Staff\ManageOrganization::createEstablishment()`).
- `establishments.is_active` n'est contrôlé nulle part à l'accès : ce n'est pas un mécanisme d'attente fiable.

Demande de l'utilisateur : le bouton « S'inscrire » ne doit plus être réservé aux parents ; il mène à une page de choix, et un fondateur doit pouvoir soit rejoindre une organisation existante, soit créer lui-même son école — sous réserve de validation par un administrateur SaaS.

## Décisions validées

| Sujet | Décision |
|---|---|
| Parcours fondateur | Les deux : « J'ai un identifiant » (existant) **et** « Je crée mon école » (nouveau) |
| Activation | Après validation par un administrateur SaaS |
| Structure créée | Au choix du fondateur : école indépendante, ou groupe scolaire (fondation) + première école |
| Cartes de la page de choix | Trois : Fondateur, Personnel de direction, Parent d'élève |
| Refus | Suppression complète de la demande, e-mail libéré |
| Stockage de l'attente | Table de demandes dédiée — rien n'est créé dans les tables métier avant validation |

## Parcours et pages

### Page de connexion

Le lien devient « Pas encore de compte ? S'inscrire » → `route('register')`.

### `/register` — page de choix

Nouveau composant `App\Livewire\Auth\RegisterChoice` (layout `layouts.guest`, sans formulaire), route nommée `register`. Trois cartes :

1. **Fondateur**, avec deux actions :
   - « Je crée mon école » → `/register/fondateur` (`register.school`) ;
   - « J'ai un identifiant fourni par la plateforme » → `/staff/register?role=fondateur`.
2. **Personnel de direction** (directeur, gestionnaire) → `/staff/register`.
3. **Parent d'élève** → `/register/parent` (`register.guardian`).

### `/register/parent`

Le formulaire parent actuel, déplacé sans changement de comportement. Le composant `App\Livewire\Auth\Register` est renommé `App\Livewire\Auth\RegisterGuardian` (vue `livewire.auth.register-guardian`).

### `/staff/register?role=fondateur`

`Staff\Register::$role` reçoit l'attribut `#[Url]` : le paramètre de requête présélectionne le rôle. Seules les valeurs déjà autorisées par la validation (`fondateur`, `directeur`, `gestionnaire`) ont un effet ; la validation existante reste la seule barrière.

### `/register/fondateur` — « Je crée mon école »

Nouveau composant `App\Livewire\Auth\RegisterSchool` (layout `layouts.guest`).

- **Votre compte** : nom, prénom, e-mail, pseudo, mot de passe + confirmation (mêmes règles que `Staff\Register`, dont `User::pseudoRules()`).
- **Votre école** : nom, type (`EstablishmentType`), inspection si Préscolaire/Primaire **ou** direction si Secondaire (obligatoire selon le type, exclusifs — règle de `Establishments\Index::save()`), téléphone et adresse (facultatifs). Les listes inspection/direction se mettent à jour selon le type (`wire:model.live` sur le type).
- **Case « Je gère un groupe scolaire (plusieurs écoles) »** (`wire:model.live`) → champ « Nom du groupe scolaire », obligatoire si cochée.
- Après envoi : le formulaire est remplacé par « Votre demande a été enregistrée. Vous pourrez vous connecter dès qu'elle aura été validée par l'équipe Nitsoft. » + lien « Retour à la connexion ».

Hors formulaire (complétés après validation, depuis les écrans existants) : logo, code d'ouverture, code DSPS, coordonnées GPS, option arabe, e-mail de l'école.

Toutes les routes d'inscription restent dans le groupe `guest` de `routes/web.php`.

## Modèle de données

### Table `school_registrations`

Modèle `App\Domain\Establishments\Models\SchoolRegistration`.

| Colonne | Type |
|---|---|
| `id` | bigint |
| `name`, `first_name` | string |
| `email` | string, unique |
| `pseudo` | string, unique |
| `password` | string (haché — cast `hashed`) |
| `establishment_name` | string |
| `establishment_type` | string (cast `EstablishmentType`) |
| `inspection_id` | FK `inspections`, nullable, `nullOnDelete` |
| `direction_id` | FK `directions`, nullable, `nullOnDelete` |
| `phone`, `address` | string, nullable |
| `foundation_name` | string, nullable (renseigné = groupe scolaire) |
| `created_at`, `updated_at` | timestamps |

Pas de colonne de statut : une ligne existe tant que la demande est en attente, elle est supprimée à la validation comme au refus. Pas de `SoftDeletes` (voir le piège SoftDeletes + contrainte unique). `password` est dans `$hidden`. Casts en propriété `$casts` (pas la méthode `casts()`, pour Larastan).

La table n'a pas d'`establishment_id` : elle n'est soumise à aucun scope tenant.

## Règles métier

### Envoi du formulaire (`RegisterSchool::register()`)

- `email` : `unique:users,email` **et** `unique:school_registrations,email`.
- `pseudo` : `User::pseudoRules()` **et** `unique:school_registrations,pseudo`.
- `inspection_id` / `direction_id` : `requiredIf` selon le type + `exists`, puis mise à `null` de celui qui ne correspond pas au type.
- `foundation_name` : `required_if` case cochée ; forcé à `null` si décochée.
- Création de la `SchoolRegistration`, aucune connexion de l'utilisateur.

### Validation (`App\Domain\Establishments\Services\SchoolRegistrationApprover::approve(SchoolRegistration, User $author)`)

Dans une `DB::transaction()` :

1. Revérifie que `email` et `pseudo` sont libres dans `users` (ils ont pu être pris depuis, p. ex. par une inscription parent). Sinon lève une exception métier dédiée (`SchoolRegistrationConflictException`) → message d'erreur à l'administrateur SaaS, qui peut alors refuser.
2. Crée le `User` (`name`, `first_name`, `email`, `pseudo`, `password` déjà haché — le cast `hashed` ne re-hache pas une valeur déjà hachée, `Hash::isHashed()`).
3. **Avec `foundation_name`** : crée la `Foundation` (`is_active = true`, slug unique), puis l'`Establishment` avec `foundation_id`, puis le pivot `foundation_user` (`role = fondateur`, `is_active = true`, `is_general_admin = true`).
   **Sans** : crée l'`Establishment` indépendant, puis le pivot `establishment_user` (`role = fondateur`, `is_active = true`, `is_general_admin = true`).
4. L'`Establishment` est créé actif, slug unique (même logique que `uniqueSlugFor()` des composants existants ; factoriser dans un helper partagé si cela évite une troisième copie).
5. Supprime la demande ; `Log::info('school_registration.approved', [...])` avec l'e-mail du fondateur, l'école et l'auteur.

Retourne l'`Establishment` créé.

### Refus (`SchoolRegistrationApprover::reject(SchoolRegistration, User $author)`)

Supprime la demande ; `Log::info('school_registration.rejected', [...])`.

### Droits

`App\Policies\SchoolRegistrationPolicy` : `viewAny`, `approve`, `reject` → `$user->isSaasAdmin()` (Principal et Secondaire). Cohérent avec la création d'établissements, déjà ouverte à tout administrateur SaaS, et avec le `Gate::before` global — aucun carve-out à ajouter.

### Connexion d'un fondateur en attente

Dans `App\Livewire\Auth\Login` : si aucun `User` ne correspond à l'identifiant (e-mail ou pseudo), rechercher une `SchoolRegistration` par `email` ou `pseudo`. Si elle existe **et** que `Hash::check()` valide le mot de passe → erreur « Votre inscription est en attente de validation par l'équipe Nitsoft. » ; sinon, l'erreur habituelle. On ne révèle ainsi l'existence d'une demande qu'à qui connaît son mot de passe.

## Écran administrateur SaaS

### `Establishments\Index`

- Encart « Écoles en attente de validation (N) » au-dessus de la liste, affiché seulement si N > 0. Colonnes : date de la demande, école + type, inspection ou direction, groupe scolaire (le cas échéant), fondateur (nom, prénom, e-mail), actions.
- **Valider** → `approve()` ; en cas de `SchoolRegistrationConflictException`, message d'erreur sur la ligne.
- **Refuser** → `wire:confirm` puis `reject()`.
- Chargement des demandes avec `inspection` et `direction` en eager loading (pas de N+1).

### Menu

Les entrées de `$navItems` (`resources/views/layouts/app.blade.php`) acceptent une clé facultative `badge` (entier) ; l'entrée « Établissements » du menu SaaS reçoit le nombre de demandes en attente (affiché seulement si > 0). Une seule requête `count()`, exécutée uniquement pour un administrateur SaaS.

## Traductions

Tous les nouveaux textes passent par `__()` avec la phrase française comme clé, traduits dans `lang/en.json` et `lang/ar.json`. Le libellé modifié de la page de connexion remplace l'ancienne clé dans les deux fichiers.

## Tests (Pest)

- **Page de choix** : les trois cartes et leurs liens ; la page de connexion pointe vers `route('register')` ; accès refusé à un utilisateur connecté (`guest`).
- **Formulaire parent** : les tests existants (`tests/Feature/Livewire/Auth/RegisterTest.php`) migrés vers `RegisterGuardian`, comportement inchangé.
- **`RegisterSchool`** :
  - crée une demande sans groupe, puis avec groupe ; mot de passe haché ;
  - inspection obligatoire en Préscolaire/Primaire, direction obligatoire en Secondaire, l'autre remise à `null` ;
  - nom du groupe obligatoire si la case est cochée ;
  - refuse un e-mail/pseudo déjà pris dans `users` et dans `school_registrations` ;
  - n'authentifie pas l'utilisateur.
- **`SchoolRegistrationApprover`** :
  - école indépendante : `User` + `Establishment` actif + pivot `establishment_user` fondateur actif `is_general_admin` ;
  - groupe : `Foundation` + `Establishment` rattaché + pivot `foundation_user` fondateur actif `is_general_admin` ;
  - le fondateur se connecte avec **son** mot de passe (pas de double hachage) et accède à son école (`accessibleEstablishments()`) ;
  - conflit d'e-mail survenu entre-temps → exception, rien n'est créé, la demande est conservée ;
  - `reject()` supprime la demande.
- **Policy** : un administrateur SaaS (Principal et Secondaire) peut `viewAny`/`approve`/`reject` ; un fondateur ou directeur ne peut pas.
- **`Establishments\Index`** : l'encart liste les demandes ; Valider et Refuser fonctionnent.
- **Connexion** : message « en attente » avec le bon mot de passe (par e-mail et par pseudo) ; erreur habituelle avec un mauvais mot de passe.
- **`/staff/register?role=fondateur`** présélectionne le rôle.

## Vérification

- Suite complète (`php artisan test --parallel`), Pint, Larastan.
- `migrate:fresh` sur une base MySQL jetable (InnoDB) pour valider la migration et les index/FK réels (pièges limite 64 caractères et InnoDB non visibles sous SQLite).
- Parcours réel dans le navigateur : choix → création de demande → connexion « en attente » → validation par un administrateur SaaS → connexion réussie du fondateur sur son école.

## Hors périmètre

- Notification par e-mail (au fondateur ou aux administrateurs SaaS).
- Vérification de l'adresse e-mail.
- Inscription en libre-service d'une seconde école par le fondateur d'une école indépendante (inchangé : passe par l'administrateur SaaS).
- Modification d'une demande en attente par le fondateur ou l'administrateur SaaS.
