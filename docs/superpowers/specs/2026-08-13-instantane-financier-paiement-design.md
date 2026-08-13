# Instantané de la situation financière sur le paiement

*(Design validé par l'utilisateur le 2026-08-13.)*

## Contexte

Le reçu de paiement (`resources/views/pdf/receipt.blade.php`, contrôlé par
`app/Http/Controllers/Billing/PaymentReceiptPdfController.php`) affiche
actuellement une situation financière (total scolarité, total versement,
reste scolarité, date et montant du prochain versement) **calculée en
direct** à chaque ouverture du reçu, à partir de l'état courant des factures
de l'élève. Conséquence : rouvrir le reçu d'un ancien paiement après que
d'autres paiements ont eu lieu affiche la situation *actuelle*, pas celle
*au moment de ce paiement*.

Objectif : figer cette situation financière sur le `Payment` lui-même au
moment de sa création, pour que chaque reçu reste historiquement exact.

## 1. Nouvelles colonnes sur `payments`

Nouvelle migration (les migrations existantes ne sont jamais modifiées),
colonnes nullables, sans backfill pour les paiements déjà en base (décision
utilisateur — voir "Décisions actées" ci-dessous) :

- `tuition_paid_total` (`decimal(10,2)`, nullable) — cumul versé pour la
  scolarité (hors facture d'inscription), jusqu'à ce paiement inclus.
- `tuition_remaining` (`decimal(10,2)`, nullable) — reste à payer pour la
  scolarité à ce moment (`total scolarité − tuition_paid_total`).
- `next_installment_due_date` (`date`, nullable) — date du prochain
  paiement à ce moment (peut être `null` si l'élève est soldé).
- `next_installment_amount` (`decimal(10,2)`, nullable) — montant de ce
  prochain versement (`null` si `next_installment_due_date` est `null`).

Pas de colonne "total scolarité" séparée : elle se déduit par
`tuition_paid_total + tuition_remaining` à l'affichage — pas besoin de la
figer séparément puisqu'elle découle des deux autres.

`Payment::$fillable` et `$casts` (`decimal:2` pour les 3 montants,
`date` pour l'échéance) sont mis à jour en conséquence.

## 2. Calcul dans `PaymentService::recordPayment()`

Le calcul de l'instantané est déplacé du contrôleur PDF vers
`PaymentService::recordPayment()`, seul point d'entrée de création d'un
paiement. La méthode est réordonnée : l'incrémentation de
`$invoice->amount_paid` et son `save()` passent **avant** la création du
`Payment`, pour que le calcul du cumul (`tuitionInvoices`) inclue bien ce
paiement.

Logique reprise telle quelle depuis le contrôleur actuel :
- `$tuitionInvoices` = factures de l'élève pour l'année scolaire du
  paiement, `whereNotNull('installment_id')` (exclut l'inscription),
  `where('status', '!=', 'cancelled')`.
- `tuition_paid_total` = somme `amount_paid` sur `$tuitionInvoices`.
- `tuition_remaining` = somme `amount_due` − `tuition_paid_total`.
- `next_installment_due_date` = `Invoice::nextDueDateAfterCumulativePayments($tuitionInvoices, $tuitionPaidTotal)`
  (méthode déjà existante sur le modèle `Invoice`, inchangée).
- `next_installment_amount` = somme des `amount_due` des factures dont
  `due_date <= next_installment_due_date`, moins `tuition_paid_total` (si
  `next_installment_due_date` est `null`, `next_installment_amount` reste
  `null`).

Ces 4 valeurs sont passées directement dans le `create()` du `Payment` :
l'instantané est figé pour toujours dès la création, qu'il s'agisse d'un
paiement de tranche ou d'un paiement d'inscription (dans ce dernier cas,
l'instantané reflète simplement la situation de la scolarité, non affectée
par ce paiement d'inscription lui-même — comportement identique à
aujourd'hui).

## 3. Contrôleur PDF et vue

`PaymentReceiptPdfController::__invoke()` se simplifie fortement : plus
besoin de la requête `tuitionInvoices` ni de
`Invoice::nextDueDateAfterCumulativePayments()` (ce code est supprimé du
contrôleur). Il lit directement les 4 champs sur `$payment` et les passe à
la vue.

Dans `receipt.blade.php`, le bloc "situation financière" (divider + table
`Total scolarité`/`Total versement`/`Reste scolarité` + sous-lignes
`Date du prochain paiement`/`Somme prochain versement`) est **masqué en
bloc** si `$payment->tuition_paid_total` est `null` (paiements existants
avant ce chantier). À l'intérieur de ce bloc, les deux sous-lignes du
prochain versement restent conditionnelles à
`$payment->next_installment_due_date` non nul (élève soldé). Le divider
séparant la situation financière du cadre "Cachet de l'établissement"
reste, lui, toujours affiché (structurel, indépendant des données).

## Décisions actées

- **Pas de backfill** pour les paiements déjà en base : leurs 4 champs
  restent `null`.
- **Lignes masquées** (pas de calcul de secours) sur le reçu d'un paiement
  dont l'instantané est `null` — pas de logique de calcul dupliquée entre
  `PaymentService` et le contrôleur.

## Tests à mettre à jour

- `tests/Feature/Domain/PaymentServiceTest.php` : assertions sur les 4
  nouveaux champs après `recordPayment()`, y compris le cas où l'élève est
  soldé (`next_installment_due_date`/`next_installment_amount` restent
  `null`) et le cas où le paiement concerne l'inscription (l'instantané
  reflète la scolarité, pas l'inscription).
- `tests/Feature/Http/PaymentReceiptPdfTest.php` : les tests qui rendent
  `pdf.receipt` directement avec des valeurs manuelles (`totalTuition`,
  `totalPayments`, `nextPaymentDueDate`, `nextInstallmentAmount`) sont
  réécrits pour passer un `$payment` dont les 4 champs sont déjà renseignés
  (ou `null`), et la vue lit `$payment->xxx` au lieu de variables séparées.
  Nouveau test : reçu d'un paiement dont l'instantané est `null` → le bloc
  situation financière est absent du rendu.
