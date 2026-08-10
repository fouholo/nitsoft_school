# Menu filtré par permissions

## Contexte

La sidebar (`resources/views/layouts/app.blade.php`, tableau `$navItems`) affiche aujourd'hui tous les liens/groupes à tout utilisateur connecté, indépendamment de son rôle. Chaque écran cible fait déjà un `authorize('viewAny', X::class)` (ou `'create'` pour l'envoi de SMS) dans son `mount()` — un utilisateur sans accès clique, arrive sur l'écran, et se prend une erreur d'autorisation. Ce chantier fait disparaître du menu tout lien auquel l'utilisateur n'a pas accès, en réutilisant ces mêmes vérifications (aucune nouvelle règle métier créée).

## 1. Structure des entrées de menu

Chaque entrée `link` et chaque enfant de `group` gagne une paire `ability`/`model` :

```php
['type' => 'link', 'label' => 'Élèves', 'route' => 'students.index', 'active' => 'students.*', 'icon' => 'users', 'ability' => 'viewAny', 'model' => \App\Domain\Enrollment\Models\Student::class],
```

Mapping complet (ability par défaut `viewAny`, sauf mention contraire) :

| Item | Modèle |
|---|---|
| Années scolaires | `SchoolYear` |
| Périodes | `Term` |
| Classes | `Classroom` |
| Matières | `Subject` |
| Coefficients par matière | `SubjectCoefficient` |
| Affectations | `TeacherAssignment` |
| Élèves | `Student` |
| Tuteurs | `Guardian` |
| Demandes de liaison | `GuardianStudentPivot` |
| Évaluations | `GradeSheet` |
| Bulletins | `ReportCard` |
| Présences | `AttendanceSession` |
| Tarifs | `Installment` |
| Factures | `Invoice` |
| Dépenses | `Expense` |
| Suivi des paiements | `Invoice` |
| Réductions | `Discount` |
| Modèles (SMS) | `SmsTemplate` |
| Journal (SMS) | `SmsMessage` |
| Envoyer un SMS | `SmsMessage`, **ability `create`** |

"Demandes de liaison" remplace son gate inline actuel (`hasAdminRightsOnCurrentEstablishment()`) par ce même mécanisme — `GuardianStudentPivotPolicy::viewAny()` vérifie déjà exactement cette condition, donc le comportement ne change pas, seul le mécanisme est unifié.

"Tableau de bord" n'a pas de check (aucune Policy sur ce composant) — toujours visible. Les entrées déjà conditionnées par autre chose que `$navItems` (Groupes scolaires, Administrateurs SaaS, Mon établissement, Mon organisation) restent strictement inchangées — elles suivent déjà le principe "visible seulement si accessible".

## 2. Filtrage

Avant la boucle de rendu, `$navItems` est filtré :
- Un item `link` est retiré si `ability`/`model` sont présents et que `Gate::allows($ability, $model)` est faux.
- Un item `group` est retiré si, après filtrage de ses `children` selon la même règle, la liste résultante est vide.

Les items sans `ability`/`model` (Tableau de bord) ne sont jamais filtrés par ce mécanisme.

## Hors périmètre

- Pas de nouvelle Policy ni de changement de règle d'autorisation — uniquement la réutilisation des `viewAny`/`create` déjà en place.
- Pas de page d'erreur 403 personnalisée pour les accès directs par URL (hors menu) — si un utilisateur tape une URL à laquelle il n'a pas accès, le comportement actuel (page d'erreur Laravel standard) ne change pas ; ce chantier ne concerne que la visibilité du menu.
- Pas de traitement des items déjà conditionnés hors `$navItems` (SaaS/staff) — ils fonctionnent déjà correctement.
