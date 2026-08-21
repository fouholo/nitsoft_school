# Bilan financier par profil (utilisateur) — design

*Statut : approuvé par l'utilisateur le 2026-08-21.*

## Contexte

Le module Facturation dispose déjà de « Suivi des paiements » (liste plate élève par élève, avec un solde), mais aucun écran n'agrège les mouvements financiers par personne ayant encaissé ou dépensé de l'argent. Le besoin exprimé — « bilan financier par profil » — s'est précisé en cours d'échange : il s'agit du profil **utilisateur** (qui a manipulé de l'argent), pas du profil élève (sexe/redoublant/boursier/affecté, déjà couvert par les filtres de Listes/Rapports).

Constats issus de l'exploration du code existant :

- `Payment` porte `received_by` (BelongsTo `User`) et un champ `amount` ; `Expense` porte `recorded_by` (BelongsTo `User`) et `amount`. Les deux implémentent `HasOwnerColumn`/`HasOwnerScope`, déjà utilisé pour restreindre le rôle `éducateur` à ses propres écritures (`finance.scope_own_only`).
- `User::roleFor(int $establishmentId): ?string` résout le rôle d'un utilisateur pour l'établissement courant. `User::currentRoleLabel()` contient déjà le mapping rôle → libellé français, mais uniquement pour le rôle de l'utilisateur connecté (pas réutilisable tel quel pour un rôle arbitraire).
- `RolePermissions::ABILITIES['finance.access']` = `['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur']` — c'est la même liste de rôles qui gouverne l'accès à Suivi des paiements/Dépenses. `finance.scope_own_only` = `['educateur']`.
- `SchoolYear` a `starts_on`/`ends_on` (dates), `is_current` (bool) — convention déjà utilisée partout ailleurs (`$this->school_year_id` défaulté sur l'année courante).
- Aucun écran ni service n'agrège actuellement des montants par utilisateur/rôle. Aucune notion de bilan financier n'existe dans le code (confirmé par recherche exhaustive).

## Objectif

Donner à la direction (et, en scope restreint, à chaque éducateur) une vue consolidée des sommes encaissées et dépensées par personne, groupées par rôle, sur une période choisie (année scolaire ou plage de dates libre), avec un total net (encaissé − dépensé) par utilisateur, par rôle, et global.

## 1. Service d'agrégation

Nouveau : `App\Domain\Billing\Services\FinancialSummaryService`.

```php
/**
 * @return Collection<int, array{
 *     user_id: int|null,
 *     user_name: string,
 *     role: string|null,
 *     collected: float,
 *     spent: float,
 *     net: float,
 * }>
 */
public function summaryByUser(CarbonInterface $start, CarbonInterface $end, ?int $ownerId = null): Collection
```

Comportement :

1. Agrège `Payment::whereBetween('paid_at', [$start, $end])` (+ `where('received_by', $ownerId)` si fourni), groupé par `received_by`, `SUM(amount)` → montants encaissés par utilisateur.
2. Agrège `Expense::whereBetween('spent_at', [$start, $end])` (+ `where('recorded_by', $ownerId)` si fourni), groupé par `recorded_by`, `SUM(amount)` → montants dépensés par utilisateur.
3. Union des `user_id` apparus dans l'un ou l'autre groupe. `received_by`/`recorded_by` sont des clés étrangères `cascadeOnDelete()` vers `users` (non nullables) : un utilisateur supprimé entraîne la suppression de ses paiements/dépenses, donc aucun `user_id` orphelin ne peut apparaître ici — pas de cas « utilisateur supprimé » à gérer.
4. Pour chaque utilisateur : résout `user_name` (nom actuel), `role` via `roleFor($establishmentId)` (établissement courant résolu via `app('currentEstablishmentId')`), `null` si aucun rattachement actif → affiché sous le rôle « Autre » côté vue.
5. `net = collected - spent`. Tri : par rôle (ordre fixe défini côté vue/contrôleur), puis par `user_name` alphabétique à l'intérieur du rôle.

Tenant scoping : `Payment` et `Expense` portent déjà `TenantScoped`, donc les requêtes sont automatiquement bornées à l'établissement courant — pas de filtre `establishment_id` explicite nécessaire dans le service.

### Refactor associé

`User::currentRoleLabel()` est éclatée en une méthode statique réutilisable :

```php
public static function roleLabel(?string $role): string
{
    return match ($role) {
        'directeur' => 'Directeur',
        'gestionnaire' => 'Gestionnaire',
        'enseignant' => 'Enseignant',
        'caissier' => 'Caissier',
        'educateur' => 'Éducateur',
        'parent' => 'Parent',
        'fondateur' => 'Fondateur',
        default => 'Aucun rôle',
    };
}

public function currentRoleLabel(): string
{
    return self::roleLabel($this->currentRole());
}
```

Le service et la vue du bilan utilisent `User::roleLabel($row['role'])`.

## 2. Écran Livewire

Nouveau : `App\Livewire\Billing\FinancialSummary\Index`, vue `resources/views/livewire/billing/financial-summary/index.blade.php`, route nommée `billing.financial-summary.index` (`/billing/bilan-financier`), ajoutée à `routes/billing.php` et au menu de navigation Facturation (`layouts/app.blade.php`, à côté de Suivi des paiements / Dépenses / Tarifs / Réductions).

**État public :**
- `school_year_id` (défaut : année courante, comme les autres écrans du module).
- `useCustomRange` (bool, défaut `false`).
- `start_date`, `end_date` (nullable, string ISO) — actifs seulement si `useCustomRange`.

**Résolution de la période dans `render()` :**
- Si `useCustomRange` et `start_date`/`end_date` renseignés et valides (`end_date >= start_date`) → période = `[start_date, end_date]`.
- Sinon → période = `[schoolYear.starts_on, schoolYear.ends_on ?? now()]`.
- Si `useCustomRange` mais `end_date < start_date` → pas de requête, message de validation affiché (« La date de fin doit être postérieure à la date de début. »), tableau non calculé.

**Accès :** `mount()` appelle `$this->authorize('viewAny', Payment::class)` (gate `finance.access`, déjà en place, couvre les mêmes rôles que Suivi des paiements).

**Scope éducateur :** même pattern que `PaymentTracking\Index` — `$ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null`, passé à `summaryByUser()`. Un éducateur ne récupère donc que sa propre ligne (restriction au niveau de la requête, pas un filtre d'affichage a posteriori).

**Affichage :**
- Tableau groupé par rôle, ordre fixe : Fondateur, Directeur, Gestionnaire, Caissier, Éducateur, puis Autre (rôles non reconnus/rattachement expiré) en dernier.
- Pour chaque groupe de rôle : ligne d'en-tête avec le libellé du rôle et le sous-total (Encaissé / Dépensé / Net), puis une ligne par utilisateur.
- Ligne de total général en pied de tableau.
- Si aucune donnée sur la période : message « Aucun encaissement ni dépense enregistré sur cette période. » à la place du tableau.
- Un éducateur scope-restreint ne voit qu'un seul groupe de rôle (le sien) avec sa seule ligne — pas de sous-total « caché » d'autres personnes.

## 3. Export PDF

Nouveau : `App\Http\Controllers\Billing\FinancialSummaryPdfController` (même dossier que `PaymentReminderPdfController`), route `reports.financial-summary-pdf` (`/rapports/bilan-financier`) ajoutée à `routes/reports.php` (même convention que les autres PDF de rapports).

- Paramètres query : `school_year_id`, ou `start_date`/`end_date` si plage personnalisée — réplique la résolution de période du composant Livewire (logique dupliquée volontairement, comme pour les filtres de liste d'élèves, afin de ne pas coupler contrôleur HTTP et composant Livewire).
- Applique la même restriction de scope éducateur que l'écran (`Gate::authorize('viewAny', Payment::class)` + `finance.scope_own_only`).
- Vue `resources/views/pdf/financial-summary.blade.php`, A4 portrait, réutilise `pdf.partials.reports-header`, même structure groupée par rôle avec sous-totaux et total général que l'écran.
- Nom de fichier : `bilan-financier-{période}.pdf` (slug de l'année scolaire ou des deux dates).
- Bouton « Bilan financier (PDF) » sur l'écran Livewire, ouvert dans un nouvel onglet, reflète les filtres actifs (période) dans l'URL — même pattern que les liens PDF existants dans Listes/Rapports.

## 4. Cas limites

- Aucun mouvement sur la période → message dédié (voir ci-dessus), pas de tableau vide avec en-têtes seules.
- Utilisateur sans rattachement actif à l'établissement au moment de la consultation (mutation, rôle retiré) → `roleFor()` retourne `null`, regroupé sous « Autre ».
- Plage personnalisée avec date de fin avant date de début → validation bloquante, pas de requête.
- Rôle `parent`/`enseignant` apparaissant dans les données (ne devrait normalement pas arriver, ces rôles n'ont pas la permission `billing.manage`/`finance.access`) → traité comme n'importe quel autre rôle reconnu, pas de cas spécial nécessaire.

## 5. Tests

- `tests/Feature/Domain/FinancialSummaryServiceTest.php` : agrégation correcte par utilisateur, calcul du net, restriction `$ownerId`, utilisateur présent seulement côté dépenses (resp. seulement paiements), exclusion des mouvements hors période, tri par rôle puis nom.
- `tests/Feature/Livewire/Billing/FinancialSummary/IndexTest.php` : accès refusé hors rôles `finance.access`, éducateur ne voit que sa propre ligne (pas les totaux des autres), bascule année scolaire ↔ plage personnalisée, totaux/sous-totaux affichés correctement, validation date fin < date début, message d'état vide.
- `tests/Feature/Http/FinancialSummaryPdfTest.php` : rendu HTML direct (colonnes, groupement par rôle, totaux), restriction d'accès et de scope identique à l'écran, isolation tenant.

## Hors périmètre (pour l'instant)

- Ventilation par mode de paiement (espèces/mobile money/virement/carte).
- Export autre que PDF (Excel/CSV).
- Historique/comparaison entre plusieurs périodes sur un même écran.
