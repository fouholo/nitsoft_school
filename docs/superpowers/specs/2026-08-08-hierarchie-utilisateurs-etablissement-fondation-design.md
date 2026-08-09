# Hiérarchie des utilisateurs d'établissement/fondation : GENERAL_ADMIN / LOCAL_ADMIN / USER

## Contexte

Aujourd'hui, un utilisateur d'établissement (`establishment_user.role` : `admin`/`teacher`/`accountant`) ou de fondation (`foundation_user.role` : `founder`) ne peut être créé que par un Super Admin SaaS (écran Foundations) ou par le seeder — il n'existe aucun moyen pour un établissement de s'auto-administrer : pas d'auto-inscription, pas d'écran pour qu'un directeur d'école crée ses propres enseignants/comptables, pas de notion de hiérarchie entre plusieurs administrateurs d'un même établissement ou groupe.

Cette spec introduit :
1. Un vocabulaire de rôles en français, structuré dans une vraie table de référence (`fondateur`/`directeur`/`gestionnaire`/`enseignant`/`comptable`), remplaçant les chaînes libres actuelles.
2. Une hiérarchie de **pouvoir** (indépendante du rôle métier) à trois niveaux : **GENERAL_ADMIN** (un seul par fondation ou par établissement indépendant), **LOCAL_ADMIN** (un seul par établissement), **USER** (aucun pouvoir sur les autres utilisateurs).
3. Une auto-inscription par uid d'établissement, avec bootstrap automatique du premier arrivant et file d'attente pour les suivants.
4. Deux écrans de gestion (LOCAL_ADMIN, GENERAL_ADMIN) pour administrer les comptes staff d'un établissement/groupe.

Ce chantier est indépendant de la hiérarchie **Administrateurs SaaS MAIN/SECOND** (`docs/superpowers/specs/2026-08-08-administrateurs-saas-main-second-design.md`) : les admins SaaS bypassent tout via `Gate::before` et n'appartiennent à aucun établissement — GENERAL_ADMIN/LOCAL_ADMIN sont un pouvoir strictement borné à un établissement ou une fondation, porté par des Policies classiques, pas par un bypass global.

## 1. Table de référence `roles`

Table globale (même famille que `levels`/`series`/`nationalites` : pas de `establishment_id`, gérée par seeder, pas d'écran de gestion) :

```php
Schema::create('roles', function (Blueprint $table): void {
    $table->string('code', 20)->primary();
    $table->string('wording', 50);
    $table->timestamps();
});
```

Valeurs seedées : `fondateur` (Fondateur), `directeur` (Directeur), `gestionnaire` (Gestionnaire), `enseignant` (Enseignant), `comptable` (Comptable).

`establishment_user.role` et `foundation_user.role` restent des colonnes `string`, mais gagnent une contrainte `foreign('role')->references('code')->on('roles')`. Aucun renommage de colonne — uniquement l'ajout de la contrainte, dans une migration postérieure à la création de `roles`.

**Remplace** l'ancien rôle `admin` : ce qui était `admin` devient soit `directeur` soit `gestionnaire` (deux valeurs désormais distinctes), sans changement de droits sur le reste de l'appli — partout où le code vérifie `role === 'admin'` (`User::hasAdminRightsOn()`, trait `ChecksEstablishmentMembership`, nav, etc.), la condition devient `role IN ('directeur', 'gestionnaire')`. `teacher`→`enseignant`, `accountant`→`comptable`, `founder`→`fondateur` sont de purs renommages de valeur, sans changement de portée.

**Nouveau : `fondateur` peut aussi vivre sur `establishment_user`** (pas seulement `foundation_user`) — voir section 3, cas de l'établissement indépendant.

## 2. Pouvoir GENERAL_ADMIN / LOCAL_ADMIN

Pas de nouvelle table dédiée : deux colonnes booléennes nullables ajoutées aux pivots existants, avec le même idiome que `saas_admins.is_main` (nullable + unique — `true` sur le titulaire, `null` partout ailleurs, la contrainte unique en base garantit l'unicité même sous course concurrente) :

```php
// establishment_user
$table->boolean('is_general_admin')->nullable();
$table->boolean('is_local_admin')->nullable();
$table->unique(['establishment_id', 'is_general_admin']);
$table->unique(['establishment_id', 'is_local_admin']);

// foundation_user
$table->boolean('is_general_admin')->nullable();
$table->unique(['foundation_id', 'is_general_admin']);
```

- **`is_local_admin`** : toujours porté par `establishment_user` (le pouvoir LOCAL_ADMIN est intrinsèquement borné à un établissement). Un seul titulaire par établissement, nommé/démis exclusivement par le GENERAL_ADMIN de l'organisation (fondation, ou l'établissement lui-même s'il est indépendant). Nommer un nouveau LOCAL_ADMIN retire automatiquement le flag de l'ancien titulaire (même transaction).
- **`is_general_admin`** : porté par `foundation_user` si l'établissement de rattachement appartient à une fondation, ou par `establishment_user` si l'établissement est indépendant (voir section 3 pour la logique de rattachement). Un seul titulaire par fondation OU par établissement indépendant.
- Les deux flags ne sont **pas mutuellement exclusifs** : un GENERAL_ADMIN d'un établissement indépendant peut aussi déléguer `is_local_admin` à quelqu'un d'autre sur ce même établissement tout en gardant son propre `is_general_admin` (délégation du quotidien, autorité ultime conservée).
- **Titre effectif** d'un utilisateur = calculé à la volée via des méthodes sur `User` (`isGeneralAdminOf($org)`, `isLocalAdminOf($establishment)`), jamais stocké comme valeur unique — un utilisateur peut être GENERAL_ADMIN d'une organisation et USER ordinaire ailleurs.

**Mécaniques** :
- *Cession volontaire* : le GENERAL_ADMIN désigne un autre titulaire du rôle `fondateur` de son périmètre (ou `directeur`/`gestionnaire` si établissement indépendant) → transaction qui bascule le flag `is_general_admin` de sa ligne vers celle du désigné.
- *Réclamation* : n'importe quel utilisateur de rôle `fondateur` dans le périmètre peut, à tout moment et sans validation du titulaire actuel, reprendre le flag `is_general_admin` pour lui-même (même mécanique de bascule).
- *Nomination LOCAL_ADMIN* : le GENERAL_ADMIN bascule le flag `is_local_admin` d'un établissement de son périmètre vers un `directeur`/`gestionnaire` de cet établissement.

## 3. Auto-inscription par uid d'établissement

Nouvelle route publique (ex. `/etablissements/inscription`), nouveau composant `Livewire\Establishments\StaffRegister`. Champs : nom, e-mail, mot de passe, **uid de l'établissement**, rôle (limité à `fondateur`/`directeur`/`gestionnaire` — `enseignant`/`comptable` restent provisionnés uniquement par un admin, voir section 4). Uid inconnu → erreur de validation, formulaire non soumis.

**Rattachement**, selon le rôle choisi et si l'établissement (trouvé via l'uid) appartient à une fondation :

| Rôle choisi | Établissement indépendant | Établissement dans une fondation |
|---|---|---|
| `fondateur` | Ligne `establishment_user` sur **cet établissement** | Ligne `foundation_user` sur **la fondation entière** (pas cet établissement en particulier) |
| `directeur` / `gestionnaire` | Ligne `establishment_user` sur cet établissement | Ligne `establishment_user` sur cet établissement (jamais la fondation) |

**Bootstrap ou attente**, au niveau qui correspond au pouvoir potentiel du rôle choisi :
- **`fondateur`** : si le périmètre concerné (la fondation, ou l'établissement s'il est indépendant) n'a pas encore de GENERAL_ADMIN → l'inscrit devient GENERAL_ADMIN et est activé immédiatement (`is_active = true`, personne d'autre ne pourrait l'approuver). Sinon → compte créé mais **en attente** (`is_active = false`), à activer par le GENERAL_ADMIN en place.
- **`directeur` / `gestionnaire`** : si **son établissement précis** n'a pas encore de LOCAL_ADMIN → il le devient et est activé immédiatement. Sinon → en attente, à activer par le LOCAL_ADMIN ou le GENERAL_ADMIN de son établissement.

Un directeur qui s'inscrit en premier dans une école d'un groupe **ne devient jamais** GENERAL_ADMIN de la fondation entière — il ne devient LOCAL_ADMIN que de sa propre école, car son rôle est intrinsèquement borné à un établissement. Le GENERAL_ADMIN d'une fondation ne peut venir que d'un `fondateur`. Tant qu'aucun fondateur ne s'est inscrit, la fondation n'a pas de GENERAL_ADMIN et les inscriptions en attente à l'échelle fondation restent bloquées — comportement voulu, pas un défaut.

**Activation** : réutilise `is_active` (déjà existant sur `establishment_user`/`foundation_user`) — pas de statut `pending`/`approved`/`rejected` séparé. Activer un nouvel inscrit en attente ou réactiver un compte désactivé passe par le même bouton "Activer" dans les écrans de gestion (section 4), même logique que l'écran des admins SaaS.

## 4. Écrans de gestion

**Écran LOCAL_ADMIN** (route `establishments.staff.index`, visible si l'utilisateur porte `is_local_admin` sur son établissement) : liste des utilisateurs de **son établissement uniquement** (`directeur`/`gestionnaire`/`enseignant`/`comptable`). Actions :
- Créer un compte `enseignant`/`comptable` (mot de passe généré affiché une fois — même convention que `Foundations\Show::addFounder()`/`SaasAdmins\Index::create()` ; `directeur`/`gestionnaire` rejoignent uniquement par auto-inscription, jamais créés à la main).
- Activer un compte (nouvel inscrit en attente, ou compte désactivé).
- Désactiver un compte.
- **Pas de suppression** — pouvoir explicitement refusé à LOCAL_ADMIN.

**Écran GENERAL_ADMIN** (route `establishments.admin.index`, visible si l'utilisateur porte `is_general_admin` sur sa fondation ou son établissement indépendant) : liste des utilisateurs de **toutes les écoles de son périmètre** (toute la fondation si groupé, sinon son unique établissement). Actions :
- Tout ce que fait LOCAL_ADMIN, plus :
- **Supprimer** un compte (pouvoir réservé à GENERAL_ADMIN).
- **Nommer/démettre un LOCAL_ADMIN** pour n'importe quelle école de son périmètre.
- **Céder** son `is_general_admin` à un autre titulaire admissible de son périmètre (redevient alors utilisateur ordinaire, garde son rôle).
- Approuver les `fondateur` en attente à l'échelle de la fondation.

**Bouton "Réclamer"** : visible pour tout utilisateur de rôle `fondateur` qui n'est pas déjà GENERAL_ADMIN de son périmètre, même s'il n'a lui-même aucun pouvoir actuellement (contrairement au reste des deux écrans ci-dessus, qui exigent déjà `is_general_admin`/`is_local_admin`).

**Autorisation** : `StaffPolicy` classique (méthodes `create`/`update`/`delete` réelles, vérifiant `is_general_admin`/`is_local_admin` sur le périmètre de la cible), suivant le même schéma que `ChecksEstablishmentMembership` déjà utilisé partout dans le projet. **Pas** de bypass `Gate::before` ici — ce pouvoir est strictement borné à un établissement/une fondation, à la différence du bypass global des admins SaaS (qui reste inchangé et prioritaire : un admin SaaS continue de tout voir/modifier, y compris ces écrans).

## Hors périmètre

- Pas de notification (e-mail/SMS) au GENERAL_ADMIN/LOCAL_ADMIN quand une inscription arrive en attente — l'admin doit consulter l'écran pour la voir, comme le flux existant de liaison parent-élève.
- Pas de journalisation d'audit des cessions/réclamations/nominations dans cette itération.
- Pas de migration des comptes de démo existants (`admin@nitsoft.test` etc.) — projet greenfield, le seeder est réécrit pour le nouveau modèle, `migrate:fresh --seed` suffit.
- Pas de limite au nombre de `fondateur` par organisation — un nombre quelconque de co-fondateurs peut exister, un seul détient `is_general_admin` à la fois.
- Pas de flux de réinitialisation de mot de passe (sous-chantier distinct déjà identifié séparément, cf. spec admins SaaS).
