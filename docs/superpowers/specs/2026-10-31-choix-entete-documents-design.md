# Choix de l'en-tête des documents

## Contexte

Les documents PDF d'une école portent un en-tête officiel : à gauche le ministère, la direction régionale, l'inspection, le nom de l'école et un petit logo, à droite la République, la devise, les armoiries et l'année scolaire.

Une école doit pouvoir choisir à la place un modèle centré sur son logo, d'après `ETAT_StatistiqueEvaluation.pdf` : à gauche le logo seul, en grand, à droite la même colonne qu'aujourd'hui.

## 1. Réglage

Migration sur `establishments` : `pdf_header_style` (string, non nullable, défaut `official`).

Enum `App\Domain\Establishments\Enums\PdfHeaderStyle` :

- `official` : « Officiel », le modèle actuel ;
- `logo` : « Logo », le nouveau modèle.

Le défaut garantit qu'aucune école existante ne change d'apparence au déploiement.

La migration est validée sur un MySQL jetable (création, retour arrière, nouvelle application).

## 2. Où faire le choix

- **« Mon organisation »** (`Staff\ManageOrganization`) : l'administrateur général choisit le modèle pour chacune de ses écoles. Pour une école indépendante, il n'y a qu'un seul réglage. Le droit est `manageOrganization`, et le serveur refuse une école hors de l'organisation.
- **Fiche de l'établissement** (`Establishments\Index`) : le même choix pour l'administrateur SaaS.

Le changement de logo reste réservé à la fiche de l'établissement : il est hors périmètre pour le fondateur.

## 3. Rendu du modèle « Logo »

- **Gauche** : le logo seul, environ 100 pt de haut. Sans logo, le nom de l'école en gras à la place.
- **Droite** : identique au modèle actuel (République, devise, armoiries, année scolaire).
- Le ministère, la direction et l'inspection ne s'affichent pas.

Le nom de l'école n'est pas ajouté sous un logo : sur le modèle, il fait partie de l'image.

## 4. Documents concernés

Le choix s'applique à tous les documents qui portent l'en-tête officiel.

**Par l'en-tête partagé** `pdf/partials/reports-header` :

- listes de classe française et arabe ;
- emplois du temps : français, arabe et agenda ;
- bilan financier ;
- lettres de rappel de paiement.

**Par son propre gabarit** : le relevé de notes du primaire français (`pdf/report-card-primaire`), qui reprend le ministère, la direction, l'inspection et les armoiries dans son propre tableau.

- En modèle « Officiel », il reste exactement tel qu'aujourd'hui.
- En modèle « Logo », il affiche l'en-tête partagé à la place de son tableau.

**Non concernés**, car ils n'ont pas cet en-tête :

- reçus de paiement et bulletin du secondaire français : logo et nom de l'école seulement ;
- cartes d'élève : présentation propre à la carte ;
- bulletin et relevés arabes : formats bilingues repris des modèles de l'école.

## 5. Tests

- Valeur par défaut `official`, et école existante inchangée.
- Rendu des deux modèles par l'en-tête partagé, avec et sans logo.
- Relevé du primaire dans les deux modèles.
- Réglage : accepté pour l'administrateur général et l'administrateur SaaS, refusé pour un autre fondateur, un directeur et une école hors de l'organisation, valeur inconnue refusée.
- Génération réelle d'un PDF dans chaque modèle, avec contrôle visuel par rapport au modèle.

## Hors périmètre

- Pas de changement de logo par le fondateur.
- Pas de choix par type de document : le réglage vaut pour toute l'école.
- Pas d'état statistique des compositions : c'est un nouveau document, à traiter à part.
