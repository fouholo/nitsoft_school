# Administrateurs SaaS : hiérarchie MAIN/SECOND

## Contexte

L'application distingue aujourd'hui deux échelles d'utilisateurs, mais une seule est vraiment construite : les **utilisateurs d'établissement** (`establishment_user.role` : admin/teacher/accountant/parent). L'échelle **plateforme** (administrateurs de la solution SaaS elle-même) existe à peine — un rôle `super_admin` est stocké sur `establishment_user`, ce qui l'attache artificiellement à un établissement précis, alors qu'un administrateur de la plateforme n'appartient à aucun établissement en particulier. Il n'y a par ailleurs aucun moyen de créer le tout premier compte administrateur autrement qu'en le codant en dur dans le seeder.

Cette spec introduit une vraie hiérarchie d'administrateurs SaaS à deux niveaux :
- **MAIN** : le tout premier administrateur, créé par auto-inscription. Un seul MAIN existe jamais.
- **SECOND** : administrateurs supplémentaires, créés uniquement par MAIN depuis un écran dédié. MAIN peut les activer, désactiver ou supprimer.

Les deux tiers ont exactement les mêmes accès partout ailleurs dans l'application (contournement total des Policies, comme l'actuel `super_admin`) — seule la gestion du **roster des admins SaaS eux-mêmes** est réservée à MAIN.

## 1. Table `saas_admins`

Remplace complètement le rôle `super_admin` sur `establishment_user`.

```php
Schema::create('saas_admins', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('type'); // main | second
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

`user_id` unique : un utilisateur ne peut être administrateur SaaS qu'une seule fois. Enum `App\Domain\Establishments\Enums\SaasAdminType` (`Main`, `Second`), même style que `Cycle`/`GuardianRelationship`.

Modèle `App\Domain\Establishments\Models\SaasAdmin` : fillable `['user_id', 'type', 'is_active']`, cast `type => SaasAdminType::class`, relation `user(): BelongsTo`.

`User` gagne une relation `saasAdmin(): HasOne` et les méthodes suivantes remplacent `isSuperAdmin()` :
- `isSaasAdmin(): bool` — ligne active dans `saas_admins`, quel que soit le type.
- `isMainSaasAdmin(): bool` — ligne active de type `main`.

## 2. Migration de la donnée existante (`super_admin` → `saas_admins`)

Projet greenfield (`migrate:fresh --seed`) : pas de migration de données réelle nécessaire, juste une mise à jour ciblée :
- **Seeder** : le compte "Super Admin SaaS" est créé directement dans `saas_admins` (type `main`), sans plus être attaché à `establishment_user`.
- **Migration `establishment_user`** : le commentaire de la colonne `role` (`super_admin | admin | teacher | accountant | parent`) est mis à jour pour retirer `super_admin`.
- **`Gate::before`** (`AppServiceProvider::boot()`) : `$user->isSuperAdmin()` → `$user->isSaasAdmin()`.
- Tout usage de `isSuperAdmin()`/du rôle `'super_admin'` ailleurs dans l'app (nav "Groupes scolaires", `currentRoleLabel()`) est mis à jour en conséquence.
- **Tests** : le helper `createUserWithRole($establishment, 'super_admin')` ne peut plus produire un admin SaaS valide (il n'appartient à aucun établissement). Nouveau helper `createSaasAdmin(string $type = 'main')` dans `tests/Pest.php`, créant un `User` + une ligne `saas_admins`. Les deux fichiers de tests qui utilisent l'ancien motif (`AcademicsAndEnrollmentPolicyTest.php`, `Foundations/ManagementTest.php`) sont adaptés.

## 3. Point technique : la restriction MAIN-only ne passe pas par une Policy

Le `Gate::before` actuel court-circuite **toutes** les Policies pour tout admin SaaS actif (MAIN ou SECOND) — c'est le comportement voulu partout *sauf* pour la gestion du roster d'admins SaaS lui-même, où SECOND ne doit rien pouvoir faire. Une `SaasAdminPolicy` classique serait donc inutile : `Gate::before` répondrait `true` avant même qu'elle soit consultée.

**Décision** : pas de Policy pour cette restriction. Les actions mutantes du composant `SaasAdmins\Index` (`create`, `activate`, `deactivate`, `delete`) commencent par une vérification explicite :
```php
abort_unless(Auth::user()->isMainSaasAdmin(), 403);
```
La lecture seule (afficher la liste) reste accessible à MAIN et SECOND sans restriction particulière (les deux passent déjà par le bypass `Gate::before` pour `viewAny`-style d'accès à la route elle-même, donc pas de Policy nécessaire non plus côté lecture).

Garde-fous supplémentaires dans le composant :
- MAIN ne peut pas se désactiver ni se supprimer lui-même (empêcherait tout accès futur au roster).
- "Supprimer" retire la ligne `saas_admins` (révoque le statut d'admin SaaS) — ne supprime **pas** le compte `User` sous-jacent, cohérent avec le reste de l'app (un utilisateur peut avoir d'autres rôles ailleurs).

## 4. Inscription du premier admin (MAIN)

Nouvelle route publique (ex. `/saas-admin/register`), nouveau composant `Livewire\SaasAdmin\Register` (namespace à préciser en plan, distinct de `Livewire\Auth\Register` qui gère l'auto-inscription parent).

Au chargement (`mount()`), vérifie `SaasAdmin::where('type', SaasAdminType::Main)->exists()` :
- Si un MAIN existe déjà → redirection vers `/login`, sans afficher le formulaire (comportement identique que la route existe ou non, pour ne pas laisser deviner l'état du bootstrap).
- Sinon → formulaire classique (nom, e-mail, mot de passe), crée le `User` puis la ligne `saas_admins` (type `main`), connecte automatiquement.

## 5. Écran de gestion des admins SECOND (réservé à MAIN)

Nouveau composant `Livewire\SaasAdmins\Index` (route protégée, authentifiée). Accessible uniquement si `isMainSaasAdmin()` — sinon 403 (vérification explicite en `mount()`, même logique qu'au §3).

- Liste des admins SaaS (MAIN inclus, en lecture seule pour lui-même) : nom, e-mail, type, statut actif/inactif, date de création.
- **Créer un SECOND** : formulaire nom/e-mail, mot de passe généré aléatoirement et affiché une fois à l'écran — même convention que `Guardians\Index::createPortalAccountFor()` et `Foundations\Show::addFounder()` (pas de nouvelle infra d'envoi d'e-mail à construire).
- **Activer/Désactiver** : bascule `is_active` sur la ligne `saas_admins`.
- **Supprimer** : supprime la ligne `saas_admins` (voir garde-fous §3).

Nav : nouvelle entrée "Administrateurs SaaS" dans la sidebar, visible uniquement si `isMainSaasAdmin()`.

## 6. Page d'atterrissage d'un admin SaaS

Un admin SaaS (MAIN ou SECOND) n'a en général **aucun** établissement/fondation qui lui soit propre. La route `home` actuelle redirige vers `dashboard` (établissement) ou `guardian-portal.dashboard` (parent) — ni l'un ni l'autre ne convient. Un admin SaaS est redirigé vers `foundations.index` ("Groupes scolaires"), l'écran principal déjà accessible pour ce rôle.

## 7. Hors périmètre

- Pas de flux de réinitialisation de mot de passe (sous-chantier distinct, déjà identifié séparément).
- Pas d'écran de gestion des utilisateurs d'établissement (teacher/accountant/admin) — sous-chantier distinct.
- Pas de succession si MAIN est supprimé côté base par un accès direct (hors app) — cas non couvert, l'app elle-même empêche MAIN de se supprimer/désactiver lui-même.
- Pas de journalisation d'audit des actions MAIN sur le roster (créer/activer/désactiver/supprimer) dans cette itération.
