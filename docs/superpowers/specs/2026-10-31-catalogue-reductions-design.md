# Catalogue de réductions

## Contexte

Aujourd'hui, le directeur, le caissier et l'éducateur saisissent librement le montant d'une réduction (`Discount`). Le fondateur veut fixer lui-même les réductions possibles, pour que la caisse n'ait plus qu'à les choisir, sans décider du montant.

Le catalogue ne détecte aucun élève : c'est la personne à la caisse qui reconnaît le cas et choisit l'entrée. Les règles détectées automatiquement (fratrie, boursier, etc.) sont hors périmètre.

Ce chantier s'appuie sur les deux réductions par élève et par année décrites dans `2026-10-31-reductions-droits-inscription-design.md`. Le calcul de `DiscountService` ne change pas.

## 1. Données

Nouvelle table `discount_presets`, propre à chaque établissement (`TenantScoped`, sans `SoftDeletes`) :

- `establishment_id` ;
- `name` (string), unique par établissement, y compris parmi les entrées inactives ;
- `type`, `value` : réduction sur la scolarité, nullable ;
- `registration_type`, `registration_value` : réduction sur les frais d'inscription, nullable ;
- `is_active` (booléen, vrai par défaut) ;
- `created_by` ;
- horodatages.

Dans chaque paire, les deux colonnes sont soit toutes deux renseignées, soit toutes deux nulles. Au moins une paire est renseignée.

Sur `discounts`, ajout de `discount_preset_id` (nullable, clé étrangère, `nullOnDelete`). Une réduction avec cette colonne vaut « réduction du catalogue », une réduction sans cette colonne vaut « réduction libre ». Les lignes existantes ne sont pas converties et deviennent donc des réductions libres.

La migration est validée sur un MySQL jetable (création, retour arrière, nouvelle application).

## 2. Écran « Réductions types »

Nouveau composant Livewire `Billing\DiscountPresets\Index`, route `billing.discount-presets.index`, entrée dans le menu Facturation, visible du seul fondateur.

- **Liste** : nom, scolarité, frais d'inscription, état.
- **Formulaire** : nom et les deux blocs « Scolarité » et « Frais d'inscription », avec les mêmes règles de validation que le formulaire des réductions (au moins un bloc, pourcentage plafonné à 100, valeur positive ou nulle).
- **Désactivation** : une entrée n'est jamais supprimée, elle se désactive et peut être réactivée. Une entrée inactive disparaît des listes de choix, et les réductions déjà accordées restent lisibles.

## 3. Application d'une entrée

Un nouveau service unique, `DiscountService::applyPreset()`, sert aux deux points d'entrée ci-dessous. Il reçoit l'élève, l'année et l'entrée, puis :

1. refuse une entrée inactive ou d'un autre établissement ;
2. crée la réduction de l'élève pour l'année, ou remplace celle qui existe (en réactivant au besoin une ligne supprimée en douceur, comme le formulaire actuel) ;
3. **copie** dans la réduction les types et les valeurs de l'entrée, avec son nom dans `reason` et son identifiant dans `discount_preset_id` ;
4. applique la réduction à l'inscription active par `applyToEnrollment()`.

Comme les valeurs sont copiées, modifier ou désactiver une entrée ne change pas les réductions déjà accordées. Pour appliquer les nouvelles valeurs à un élève, il faut choisir de nouveau l'entrée pour lui.

Une réduction reste unique par élève et par année : en choisir une autre remplace la précédente, sans cumul.

**À la caisse** (`Billing\Enrollments\Show`) : un bloc « Réduction » affiche la réduction en cours, la liste des entrées actives, et un bouton pour retirer la réduction si la personne en a le droit. Les montants de la fiche se recalculent aussitôt. Si la réduction en cours est libre et que la personne n'a pas le droit de la modifier, le bloc est en lecture seule.

**Écran « Réductions »** (`Billing\Discounts\Index`) : le formulaire propose la même liste d'entrées. Le fondateur et le directeur voient aussi les deux blocs de saisie libre. Le caissier et l'éducateur ne voient que la liste, et les boutons « Modifier » et « Supprimer » sont masqués sur les réductions libres.

Si l'école n'a aucune entrée active, le caissier et l'éducateur voient un message indiquant que le fondateur doit d'abord créer des réductions types.

## 4. Droits

Dans `RolePermissions` :

| Droit | Rôles |
|---|---|
| `discount_presets.manage` | fondateur |
| `discounts.apply_preset` | fondateur, directeur, caissier, éducateur |
| `discounts.free_entry` | fondateur, directeur |
| `enrollments.edit_amounts` | fondateur, directeur |

Effets :

- `DiscountPolicy::create` utilise `discounts.apply_preset`, ce qui ouvre l'accès au fondateur.
- `DiscountPolicy::update` et `delete` utilisent `discounts.apply_preset` pour une réduction du catalogue et `discounts.free_entry` pour une réduction libre.
- `Discounts\Index::save()` refuse la saisie libre sans `discounts.free_entry`. Le contrôle se fait côté serveur, et pas seulement en masquant les champs.
- `Enrollments\Show::editAmounts()` et `saveAmounts()`, ainsi que le bouton « Modifier les montants », utilisent `enrollments.edit_amounts`.
- `billing.manage` ne change pas : l'enregistrement d'un paiement reste réservé au directeur, au caissier et à l'éducateur.

**Changements à connaître** :

- Le caissier et l'éducateur ne peuvent plus modifier les montants à la main sur la fiche financière, ni saisir une réduction libre. Ils passent par le directeur ou le fondateur.
- Le fondateur obtient la modification des montants, qu'il n'avait pas.
- Le gestionnaire reste sans droit d'écriture sur les réductions, comme aujourd'hui.

**Limite connue** : dans une école sans compte fondateur, le catalogue reste vide. Le caissier ne peut alors accorder aucune réduction, mais le directeur le peut toujours en saisie libre.

## 5. Tests

- **Droits** : pour chaque rôle, accès accepté ou refusé côté serveur, et boutons affichés ou masqués.
  - Catalogue réservé au fondateur.
  - Saisie libre refusée au caissier, même en envoyant directement les champs.
  - Réduction libre en lecture seule pour le caissier.
  - Modification des montants refusée au caissier et acceptée au fondateur et au directeur.
- **Application** : depuis la caisse et depuis l'écran « Réductions », avec recalcul des montants.
- **Remplacement** : une entrée en remplace une autre, sans cumul, et sans double retranchement.
- **Copie** : modifier ou désactiver une entrée ne change pas une réduction déjà accordée.
- **Listes** : une entrée inactive en est absente, et son application directe est refusée.
- **Établissements** : catalogues isolés, et entrée d'une autre école refusée.
- **Validation** : nom unique par école, au moins un bloc, pourcentage supérieur à 100 refusé.
- **Navigateur** : création d'une entrée par le fondateur, puis application par le caissier à la caisse, dans une école temporaire.

## Hors périmètre

- Pas de règle détectée automatiquement.
- Pas de catalogue partagé entre les écoles d'une fondation.
- Pas de mise à jour en masse des réductions déjà accordées quand une entrée change.
- Pas de cumul de plusieurs réductions sur une même partie.
- Pas de nouvelle mention sur les reçus ni sur les PDF.
