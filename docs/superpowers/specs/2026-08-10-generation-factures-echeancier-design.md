# Génération des factures depuis l'échéancier

## Contexte

Sous-chantier 2/4 de "gestion des paiements" (le 1 — structure tarifaire par niveau — est livré, `docs/superpowers/specs/2026-08-10-structure-tarifaire-par-niveau-design.md`, commit `5567cb5` ; les sous-chantiers 3 — suivi retards/avances — et 4 — réductions de scolarité — restent à faire). La structure tarifaire (`Installment`/`LevelFee`/`LevelFeeInstallment`) définit ce qui est dû par niveau, mais aucune facture (`Invoice`) n'en est encore générée : `Invoice.fee_schedule_id` a été retiré sans remplacement au sous-chantier 1, explicitement reporté ici.

Ce sous-chantier connecte les deux : un bouton "Générer les factures" par niveau crée les factures manquantes pour tous les élèves actuellement inscrits dans ce niveau, à partir de la structure tarifaire configurée.

## 1. Granularité : une facture par tranche + une pour l'inscription

Un élève dont le niveau a 7 tranches de scolarité renseignées + des frais d'inscription se voit émettre **8 factures indépendantes** (pas une facture globale) : chacune garde son propre `amount_due`/`amount_paid`/`status`/`due_date`, ce qui permet de savoir précisément quelle tranche est en retard (nécessaire pour le sous-chantier 3, suivi retards/avances).

Une tranche du niveau sans montant renseigné (`LevelFeeInstallment.amount` nul, cf. sous-chantier 1) ne génère **aucune facture** — elle n'est pas due pour ce niveau.

## 2. Modèle de données

Migration ajoutant `installment_id` à `invoices` :

```php
Schema::table('invoices', function (Blueprint $table): void {
    $table->foreignId('installment_id')->nullable()->after('school_year_id')->constrained()->nullOnDelete();
});
```

`installment_id` nul = facture de frais d'inscription. `installment_id` renseigné = facture de scolarité pour cette tranche précise. `App\Domain\Billing\Models\Invoice::installment(): BelongsTo` ajoutée.

## 3. Déclenchement : bouton manuel par niveau

Sur le bloc "Tarifs par niveau" de l'écran Tarifs (`Livewire\Billing\TuitionFees\Index`), un bouton "Générer les factures" par ligne de niveau — **visible seulement si un `LevelFee` existe** pour ce niveau et l'année scolaire sélectionnée (rien à générer sinon, pas de bouton qui ne ferait jamais rien). Confirmation via `wire:confirm`, même convention que les autres actions destructives/en masse du projet.

Autorisation : `$this->authorize('create', Invoice::class)` — réutilise l'ability existante `billing.manage` de `InvoicePolicy::create`, aucune nouvelle ability.

## 4. Logique de génération

Nouvelle méthode `generateInvoices(int $levelId)` sur `TuitionFees\Index`, pour l'année scolaire actuellement sélectionnée à l'écran :

1. Récupère le `LevelFee` du niveau pour cette année (avec ses `installmentAmounts`). S'il n'existe pas, no-op (le bouton est de toute façon masqué dans ce cas — garde-fou redondant, pas une situation normale).
2. Résout les inscriptions actives (`Enrollment.status = 'active'`) de cette année scolaire dont la classe (`classroom.level_id`) appartient à ce niveau.
3. **Frais d'inscription** (si `registration_amount > 0`) : pour chaque élève sans facture d'inscription existante (`installment_id` null) pour cette année, crée une `Invoice` — libellé "Frais d'inscription", `amount_due` = `registration_amount`, `due_date` = date d'inscription de l'élève (`enrolled_on`), `status` = `pending`, `created_by` = utilisateur courant.
4. **Scolarité** : pour chaque `LevelFeeInstallment` du niveau ayant un montant non nul, pour chaque élève sans facture existante pour cette tranche précise (`installment_id` = X), crée une `Invoice` — libellé = celui de la tranche, `amount_due` = le montant configuré, `due_date` = date d'échéance de la tranche, `status` = `pending`, `created_by` = utilisateur courant.
5. **Idempotent et jamais destructif** : n'importe quel appel ultérieur ne comble que les manques (nouvel élève inscrit après coup, tranche ajoutée après coup) — une facture déjà émise n'est **jamais** modifiée ni supprimée, même si le tarif du niveau change ensuite. Cohérent avec l'immutabilité déjà choisie pour `Payment`/`Expense` (sous-chantier 1, section 3 du Contexte de la spec précédente).

## 5. Écrans existants — aucun changement nécessaire

`Livewire\Billing\Invoices\Index`/`Show` restent inchangés : le champ `label` textuel déjà affiché suffit à distinguer une facture d'inscription d'une facture de tranche de scolarité, sans besoin de nouvelle colonne d'affichage. Le filtrage/tri existant (statut, recherche élève, portée `finance.scope_own_only`) s'applique tel quel aux nouvelles factures générées.

## Hors périmètre

- Suivi des retards/avances de paiement à l'échelle d'un élève ou d'un établissement (sous-chantier 3).
- Réductions de scolarité (sous-chantier 4).
- Répartition automatique d'un paiement unique sur plusieurs factures en attente (un paiement reste rattaché à une seule facture, comme aujourd'hui) — resterait un chantier distinct si demandé.
- Régénération/mise à jour de factures déjà émises quand le tarif change (tranché : jamais, section 4 point 5).
- Proratisation des frais d'inscription selon la date d'entrée dans l'année (tranché au sous-chantier 1 : montant plein, identique pour tout élève inscrit dans l'année).
