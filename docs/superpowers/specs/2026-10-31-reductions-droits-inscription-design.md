# Réductions sur les frais d'inscription

## Contexte

Une réduction (`Discount`) ne porte aujourd'hui que sur les 7 tranches de scolarité (voir `2026-08-18-refonte-factures-scolarite-design.md`, section 3). Une école doit aussi pouvoir réduire les frais d'inscription d'un élève, indépendamment de sa scolarité : par exemple frais d'inscription offerts à 100 % et 20 % sur la scolarité.

Il reste une seule ligne `discounts` par élève et par année, qui porte désormais deux réductions facultatives.

## 1. Données

Migration sur `discounts` :

- ajout de `registration_type` (string, nullable : `percentage` ou `fixed_amount`) ;
- ajout de `registration_value` (decimal 12,2, nullable) ;
- `type` et `value` deviennent nullable, pour permettre une réduction sur les frais d'inscription seuls.

`type`/`value` restent la réduction sur la scolarité. Les lignes existantes ne sont pas converties : elles portent sur la scolarité seule. Dans chaque paire, les deux colonnes sont soit toutes deux renseignées, soit toutes deux nulles.

`down()` supprime les lignes sans réduction sur la scolarité (elles n'ont plus de sens avec l'ancien schéma), puis retire les colonnes et rétablit `type`/`value` non nullable.

La migration est validée sur un MySQL jetable (création, retour arrière, nouvelle application).

## 2. Écran « Réductions »

**Formulaire** : l'élève et le motif ne changent pas. Deux blocs, « Scolarité » et « Frais d'inscription », ont chacun leur type (pourcentage ou montant fixe) et leur valeur.

Validation :

- au moins un des deux blocs doit avoir une valeur ;
- une valeur vide signifie « pas de réduction sur cette partie » : le type du bloc est alors ignoré et les deux colonnes sont enregistrées à `null` ;
- une valeur est numérique et positive ou nulle ;
- un pourcentage ne dépasse pas 100.

**Liste** : les colonnes « Type » et « Valeur » sont remplacées par « Scolarité » et « Frais d'inscription », avec le montant ou le pourcentage de chaque partie, ou « — » s'il n'y en a pas.

**Confirmation de suppression** : le message mentionne les parties réellement concernées, et non plus seulement les tranches de scolarité.

`DiscountPolicy` ne change pas.

## 3. Calcul

Dans `DiscountService`.

**Frais d'inscription** : on part du montant de la grille de la classe principale, puis on retire la réduction sans descendre sous 0.

- Grille : `LevelFeeDefaultsService` expose ce montant pour une inscription (française avec `registration_amount_assigned` si l'élève est affecté, arabe sinon, 0 sans classe principale), sur le modèle de `installmentDefaultsForEnrollment()`.
- Pourcentage : `montant × valeur / 100`, arrondi à 2 décimales.
- Montant fixe supérieur au montant dû : le montant dû tombe à 0, et le surplus ne se reporte pas sur la scolarité. Il n'y a pas non plus de report de la scolarité vers les frais d'inscription.

**Scolarité** : calcul inchangé (retour aux tranches de la grille, puis retranchement de la tranche 7 vers la tranche 1).

**Parties non concernées** : chaque partie est traitée séparément, selon son état avant et après l'enregistrement.

| Réduction avant | Réduction après | Effet sur cette partie |
|---|---|---|
| Non | Non | Aucun : un montant ajusté à la main sur la fiche financière est conservé |
| Oui ou non | Oui | Retour à la grille, puis application de la réduction |
| Oui | Non | Retour à la grille |

La suppression d'une réduction correspond à la dernière ligne pour chaque partie qui en avait une.

Comme aujourd'hui, le calcul ne s'applique qu'à l'inscription active de l'élève pour l'année de la réduction.

## 4. Réduction accordée avant l'inscription

Aujourd'hui, une réduction accordée à un élève pas encore inscrit est ignorée à son inscription. `Students\Show::saveEnrollment()` applique désormais la réduction existante pour cet élève et cette année, sur les deux parties, juste après avoir créé l'inscription.

## 5. Effets sur les paiements

Aucun changement de code : `PaymentService` et `Enrollment` couvrent déjà les frais d'inscription avant les tranches.

- Si les frais sont déjà payés puis réduits, le trop-versé part automatiquement sur la scolarité.
- S'il ne reste aucune scolarité à payer, il reste un trop-perçu, géré comme aujourd'hui.
- Les reçus déjà émis ne changent pas, car ils conservent leur propre état financier.

## 6. Tests

- Frais d'inscription : pourcentage, montant fixe, plafond à 0 sans report sur la scolarité.
- Réduction sur les frais seuls : les tranches ne bougent pas, y compris ajustées à la main.
- Réduction sur la scolarité seule : les frais ajustés à la main sont conservés.
- Les deux réductions ensemble.
- Grilles : française, tarif « affecté », arabe.
- Modification : bloc vidé (retour à la grille), valeur changée (pas de double retranchement).
- Suppression : retour à la grille des seules parties qui avaient une réduction.
- Validation : aucun bloc rempli, pourcentage supérieur à 100.
- Réduction accordée avant l'inscription : appliquée à la création, sur les deux parties.
- Frais déjà payés puis réduits : le trop-versé est imputé à la scolarité.

## Hors périmètre

- Pas de cumul de plusieurs réductions sur une même partie.
- Pas de remboursement ni de traitement particulier du trop-perçu.
- Pas de nouveau droit d'accès.
- Pas de nouvelle mention de la réduction sur les reçus ni sur les PDF.
- Pas de reconduction automatique d'une année sur l'autre.
