# Suivi des retards et avances de paiement

## Contexte

Sous-chantier 3/4 de "gestion des paiements" (1 — structure tarifaire par niveau, commit `5567cb5` — et 2 — génération des factures depuis l'échéancier, commit `d151a4a` — sont livrés ; le 4 — réductions de scolarité — reste à faire). Les factures existent maintenant (une par tranche de scolarité + une pour l'inscription, chacune avec son propre `amount_due`/`amount_paid`/`status`/`due_date`), mais rien ne permet aujourd'hui de savoir, à un instant donné, quels élèves sont en retard de paiement ou ont payé par anticipation. Ce sous-chantier ajoute ce suivi, sans toucher à la génération des factures elle-même.

## 1. Calcul du solde par élève

Nouveau service `App\Domain\Billing\Services\PaymentTrackingService`, seule source de vérité du calcul, réutilisée par les deux écrans de ce sous-chantier :

- **Dû à ce jour** = somme des `amount_due` des factures de l'élève sur l'année scolaire considérée (hors statut `cancelled`) dont `due_date` est déjà passée (`<= aujourd'hui`).
- **Payé** = somme des `amount_paid` de **toutes** les factures de l'élève sur cette année (hors `cancelled`), échues ou non — un paiement fait par anticipation sur une tranche future compte déjà dans ce total, c'est ce qui permet de détecter une avance.
- **Solde** = Dû à ce jour − Payé. Positif = **retard** de ce montant, négatif = **avance** (affichée en valeur absolue), nul = à jour.

Le calcul est **toujours fait à la volée** à l'affichage, jamais stocké ni recalculé par une tâche planifiée (`Invoice.status` reste inchangé, la valeur `overdue` de son enum n'est pas utilisée par ce sous-chantier) — cohérent avec le principe déjà en place dans ce projet pour tout ce qui peut se recalculer sans coût prohibitif (PDF de bulletin/reçu "à la volée, jamais pré-généré").

Pour l'écran de vue d'ensemble, une seule requête agrégée (`GROUP BY student_id`, pas de boucle par élève) :
```php
Invoice::query()
    ->where('school_year_id', $schoolYearId)
    ->where('status', '!=', 'cancelled')
    ->when($ownerId, fn ($q) => $q->where('created_by', $ownerId))
    ->selectRaw('student_id, SUM(CASE WHEN due_date <= ? THEN amount_due ELSE 0 END) as due_so_far, SUM(amount_paid) as total_paid', [now()->toDateString()])
    ->groupBy('student_id')
    ->get();
```
`$ownerId` n'est renseigné que si l'utilisateur courant a `RolePermissions::can($role, 'finance.scope_own_only')` (éducateur) — même filtrage que sur `Invoices\Index`/`InvoicePolicy`, appliqué ici au niveau de l'agrégat plutôt que ligne par ligne.

Un élève sans aucune facture sur l'année n'apparaît simplement pas dans le résultat du `GROUP BY` — pas de ligne "0 dû / 0 payé / à jour" à filtrer explicitement.

## 2. Écran "Suivi des paiements"

Nouveau `Livewire\Billing\PaymentTracking\Index` (route `billing.payment-tracking.index`, lien nav "Suivi des paiements" dans le groupe "Facturation") :
- Sélecteur d'année scolaire (défaut `is_current`, même pattern que l'écran Tarifs).
- Filtre optionnel par niveau (`Level`, via la classe de l'inscription active de l'élève sur l'année sélectionnée).
- Tableau **Élève | Classe | Dû à ce jour | Payé | Solde**, trié par retard décroissant (les cas les plus urgents en premier). Solde affiché en badge : rouge "Retard de X" / vert "Avance de X" / gris "À jour".

Autorisation : `viewAny` → `RolePermissions::can($role, 'finance.access')`, ability déjà existante, aucune nouvelle entrée de matrice nécessaire.

## 3. Bloc "Situation financière" sur la fiche élève

Sur `Livewire\Students\Show` (vue `resources/views/livewire/students/show.blade.php`) : un nouveau bloc affichant les 3 mêmes chiffres (Dû à ce jour / Payé / Solde) pour l'année scolaire de l'inscription active de l'élève (fallback sur l'année marquée `is_current` s'il n'a pas d'inscription active). Visible **seulement** si l'utilisateur courant a `finance.access` (`@can('viewAny', \App\Domain\Billing\Models\Invoice::class)`) — défense en profondeur : la fiche élève elle-même reste accessible à des rôles sans accès finance (`enseignant`), qui ne doivent pas voir cette information.

## Hors périmètre

- Pas de tâche planifiée (cron) marquant automatiquement une facture `overdue` en base — calcul toujours à la volée (section 1).
- Pas de répartition automatique d'un paiement unique sur plusieurs factures en attente (déjà hors périmètre du sous-chantier 2).
- Pas de relance automatique (SMS/e-mail) déclenchée par un retard détecté — resterait un chantier distinct si demandé.
- Pas de vue "retard par tranche précise" (ex: "en retard sur la tranche de janvier uniquement") — le solde de ce sous-chantier est un cumul global par élève, pas un statut par facture individuelle (choix tranché explicitement avec l'utilisateur).
