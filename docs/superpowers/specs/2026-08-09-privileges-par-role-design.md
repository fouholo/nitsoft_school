# Privilèges par rôle métier

## Contexte

Le contrôle d'accès actuel est fragmenté : `User::hasAdminRightsOn()` (binaire directeur/gestionnaire/fondateur vs le reste), `ChecksEstablishmentMembership::isBillingManagerOfCurrentEstablishment()` (`directeur`/`gestionnaire`/`comptable`), des `in_array($role, [...])` ad hoc dans `Dashboard.php`, `dashboard.blade.php`, et plusieurs Policies (`AttendanceSessionPolicy`, `GradeSheetPolicy`, `TeacherAssignmentPolicy`). Aucune de ces vérifications ne distingue les 5 rôles métier d'établissement entre eux — un `directeur` et un `gestionnaire` ont aujourd'hui exactement les mêmes droits partout, un `comptable` n'a accès qu'à la facturation.

Ce chantier introduit une **matrice de permissions par rôle**, centralisée dans une classe PHP, remplaçant ces vérifications dispersées et couvrant 5 domaines métier (élèves, enseignants, finances, notes, parents) pour les 5 rôles d'établissement/organisation. Il inclut aussi deux ajustements de vocabulaire (`comptable` → `caissier`, ajout du rôle `éducateur`) et une nouvelle fonctionnalité "Dépenses" qui n'existe pas encore dans l'application.

Ce chantier est indépendant de la hiérarchie **GENERAL_ADMIN/LOCAL_ADMIN/USER** (`docs/superpowers/specs/2026-08-08-hierarchie-utilisateurs-etablissement-fondation-design.md`), déjà livrée : ce pouvoir continue de gouverner exclusivement la gestion du roster staff (créer/activer/désactiver/supprimer des comptes, nommer un LOCAL_ADMIN), inchangé. Ce chantier gouverne les droits **métier** (élèves, finances, notes, parents) et vient border ce que le titulaire du pouvoir peut faire sur le domaine "enseignants" — les deux mécanismes coexistent, voir section 2.

## 1. Vocabulaire de rôles

Purs renommages de valeur dans la table `roles` (section 1 de la spec précédente), sans changement de portée ailleurs que celui décrit dans ce document :
- `comptable` → `caissier`
- `enseignant` reste `enseignant` (rôle inchangé, hors périmètre — voir section 3)

Nouveau rôle ajouté à la table `roles` : `educateur` (wording "Éducateur"). Rôle **distinct** de `enseignant` : provisionné uniquement par un admin (comme `enseignant`/`caissier`), jamais par auto-inscription (le formulaire d'auto-inscription reste limité à `fondateur`/`directeur`/`gestionnaire`, inchangé).

Les 5 rôles d'établissement/organisation régis par la matrice de ce chantier : `fondateur`, `directeur`, `gestionnaire`, `caissier`, `educateur`. Le rôle `enseignant` et le rôle dérivé `parent` restent hors périmètre.

## 2. Deux mécanismes de portée qui coexistent

- **Domaine "enseignants" (gestion du roster staff)** — reste gardé par `isLocalAdminOf($establishment)`/`isGeneralAdminOf($org)`, le pouvoir du chantier précédent (un seul titulaire par établissement/organisation, nommé explicitement — n'importe quel rôle peut être nommé LOCAL_ADMIN par le GENERAL_ADMIN, pas seulement `directeur`/`gestionnaire`). Ce qui change avec ce chantier : les actions précises permises une fois qu'on est titulaire (supprimer un enseignant, l'affecter à une classe) dépendent désormais du **rôle métier du titulaire**, consulté via la matrice — un titulaire `gestionnaire` ne peut pas supprimer, un titulaire `directeur` le peut.
- **Domaines élèves / finances / notes / parents** — accessibles à **tout utilisateur** portant l'un des 5 rôles ci-dessus dans son établissement (ou dans toute son organisation pour `fondateur`), **indépendamment** du flag GENERAL_ADMIN/LOCAL_ADMIN. Un `directeur` qui n'est pas le LOCAL_ADMIN désigné de son école gère quand même ses élèves, ses finances, etc.

## 3. `RolePermissions` — matrice centralisée

Nouvelle classe `App\Domain\Establishments\Support\RolePermissions`, matrice statique consommée par les Policies :

```php
final class RolePermissions
{
    private const MATRIX = [
        'students.view'    => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'students.create'  => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'students.update'  => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'students.delete'  => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],

        'staff.update'     => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],
        'staff.delete'     => ['fondateur', 'directeur'],
        'staff.assign'     => ['fondateur', 'directeur', 'educateur'],

        'fee_schedules.create' => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'billing.manage'       => ['directeur', 'caissier', 'educateur'], // Invoice+Payment create/update
        'payments.delete'      => ['fondateur', 'directeur'],
        'expenses.create'      => ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'],
        'expenses.delete'      => ['fondateur', 'directeur'],

        'grades.view'   => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],
        'grades.enter'  => ['educateur'],

        'guardians.view'        => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],
        'guardians.notify_only' => ['caissier'],
    ];

    public static function can(string $role, string $ability): bool
    {
        return in_array($role, self::MATRIX[$ability] ?? [], true);
    }
}
```

`fondateur` n'apparaît volontairement pas dans `billing.manage` : il ne gère pas les paiements au quotidien, seulement la vue d'ensemble et la saisie des échéances (`fee_schedules.create`). Les trois abilities `staff.*` ne sont consultées qu'**après** que `isLocalAdminOf`/`isGeneralAdminOf` a déjà validé que l'utilisateur est bien titulaire du pouvoir sur ce domaine (section 2) — `fondateur` y figure car un fondateur titulaire du pouvoir garde les droits les plus larges sur le roster.

## 4. Portée "vue sur les finances" — org/école/personnel

- `fondateur` : vue sur toutes les données financières (FeeSchedule/Invoice/Payment/Expense) de **toutes les écoles de son organisation**.
- `directeur`/`gestionnaire`/`caissier` : vue sur toutes les données financières de **leur école**.
- `educateur` : vue restreinte à **ses propres saisies** sur Invoice/Payment/Expense (enregistrements où il est l'auteur) — les FeeSchedule (échéances, référentiel partagé entre écoles) restent visibles en entier, elles n'ont pas de notion de "propriétaire".

Pour permettre ce filtrage, une colonne `created_by` (`foreignId` nullable vers `users`, renseignée à la création) est ajoutée à `invoices` (`Payment` a déjà `received_by` ; `Expense`, nouveau modèle, aura `recorded_by` dès sa création — voir section 6).

## 5. Domaine "enseignants" (roster) — actions par rôle du titulaire

Inchangé dans son mécanisme (gardé par `isLocalAdminOf`/`isGeneralAdminOf`, écrans `Staff\Index`/`Staff\ManageOrganization` existants). Ce qui devient dépendant du rôle du titulaire, via `RolePermissions` :

| Action | Titulaire `fondateur`/`directeur` | Titulaire `gestionnaire` | Titulaire `educateur` |
|---|---|---|---|
| Créer / modifier | ✅ | ✅ | ✅ |
| Supprimer | ✅ | ❌ | ❌ |
| Affecter à une école/classe | ✅ | ❌ | ✅ |

`caissier` n'apparaît pas dans cette table : un `caissier` n'a aucun accès au domaine "enseignants", qu'il soit ou non titulaire du pouvoir (cas marginal — un GENERAL_ADMIN pourrait nommer un `caissier` LOCAL_ADMIN, ce qui le priverait alors de toute action sur ce domaine malgré son titre ; comportement volontairement permis, pas de garde-fou supplémentaire).

## 6. Nouveau domaine : Dépenses (`Expense`)

N'existe pas actuellement — nouveau modèle, même famille que `FeeSchedule`/`Invoice`/`Payment` (`App\Domain\Billing\Models\Expense`, `TenantScoped`, `SoftDeletes`) :

```php
Schema::create('expenses', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->decimal('amount', 12, 2);
    $table->date('spent_at');
    $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();

    $table->char('uid', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['establishment_id', 'spent_at']);
});
```

`ExpensePolicy` : `create` → `RolePermissions::can($role, 'expenses.create')` (5 rôles) ; `viewAny`/`view` → même portée que la section 4 (org/école/personnel selon le rôle) ; `delete` → `RolePermissions::can($role, 'expenses.delete')` (fondateur/directeur uniquement). Pas d'`update` dans cette itération (une dépense mal saisie se supprime et se ressaisit, comme les paiements — cohérent avec l'immutabilité déjà choisie pour `Payment`).

Écran `App\Livewire\Billing\Expenses\Index` : liste + formulaire de saisie, même gabarit que `Billing\FeeSchedules\Index`.

## 7. Domaine "parents/Guardians"

- `GuardianPolicy::viewAny`/`view` sont aujourd'hui à accès **large** (`isMemberOfCurrentEstablishment`, ouvert à tout membre de l'établissement — `enseignant`/`parent` compris, pas seulement les rôles admin-tier). Ce chantier applique une **exclusion minimale** (`$user->currentRole() !== 'caissier'`) plutôt qu'un remplacement complet par une liste `RolePermissions` — un remplacement retirerait aussi l'accès à `enseignant`/`parent`, régression hors périmètre. `educateur` conserve donc l'accès qu'il a déjà par ce check large (aucun ajout de matrice n'était nécessaire pour lui).
- `caissier` : exclu de `GuardianPolicy` (ne voit pas les fiches parents). Compensation : un **nouvel écran** `Notifications\SmsMessages\Send` (route `notifications.sms-messages.create`), qui permet de composer et d'envoyer un SMS aux tuteurs approuvés d'un élève choisi (réutilise le job d'envoi existant `SendSmsJob`, aucune nouvelle infrastructure) — gardé par `SmsMessagePolicy::create`, nouvelle ability `RolePermissions::MATRIX['guardians.notify']` = `caissier` uniquement. Décision prise après revue critique du plan : aucun écran d'envoi interactif n'existait avant ce chantier (seul un envoi automatique sur absence), donc "ouvrir la Policy existante" n'aurait débloqué aucune fonctionnalité réelle.
- Création/modification/suppression d'une fiche `Guardian` : **inchangé**, reste réservé à `fondateur`/`directeur`/`gestionnaire` (`hasAdminRightsOn()`) — ni `caissier` ni `educateur` ne peuvent créer/modifier/supprimer une fiche parent, seulement la consulter (ou envoyer un SMS pour `caissier`).

## 8. Domaine "notes"

- Vue sur les notes/moyennes (`GradeSheetPolicy::viewAny`/`view`) : même logique d'exclusion minimale que pour les tuteurs (section 7) — le check large existant (`isMemberOfCurrentEstablishment`) perd uniquement `caissier`, pas de nouvelle entrée `grades.view` dans la matrice (pas de nouvel écran de stats dédié, réutilise les vues existantes de `GradeSheets`/`ReportCards` en lecture).
- `grades.enter` : `educateur` uniquement, parmi les 5 rôles de ce chantier. `enseignant` garde son droit de saisie existant (scopé à ses classes assignées via `TeacherAssignment`), inchangé et hors périmètre.
- `caissier` : aucun accès aux notes (ni vue ni saisie).

## Hors périmètre

- Pas de refonte du rôle `enseignant` — son système de portée par classe assignée (`TeacherAssignment`, `GradeSheetPolicy`, `AttendanceSessionPolicy`) reste inchangé.
- Pas d'écran de statistiques/moyennes dédié — seule la vue existante des bulletins/notes est concernée par `grades.view`.
- Pas de modification de la logique GENERAL_ADMIN/LOCAL_ADMIN elle-même (nomination, cession, réclamation) — uniquement les actions permises une fois titulaire.
- Pas de configuration dynamique des permissions (pas de table `role_permissions` en base) — la matrice est en dur dans le code, modifiable par déploiement, conformément au choix acté.
- Pas de `update` sur `Expense` — suppression + re-saisie uniquement, comme `Payment`.
