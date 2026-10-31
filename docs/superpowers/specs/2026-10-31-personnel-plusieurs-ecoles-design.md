# Personnel sur plusieurs écoles

## Contexte

Depuis « Mon organisation » (`Staff\ManageOrganization`), le fondateur crée un membre du personnel pour une seule école, et l'unicité de l'e-mail l'empêche d'ajouter à une autre école une personne qui a déjà un compte.

Pourtant, le modèle gère déjà ce cas : `establishment_user` porte une ligne par école et par rôle, `User::accessibleEstablishments()` alimente le sélecteur d'école, et `User::roleFor()` donne le rôle pour l'école courante. Aucune migration n'est donc nécessaire.

L'auto-inscription (`Staff\Register`), le formulaire d'inscription du fondateur et l'écran du personnel d'une école (`Staff\Index`) ne changent pas.

## 1. Créer un compte

Le champ `staff_establishment_id` devient `staff_establishment_ids` (liste d'identifiants).

- **Fondation** : cases à cocher sur ses écoles, au moins une requise. Chaque identifiant doit appartenir à l'organisation.
- **École indépendante** : pas de case, son école est prise d'office.

À l'enregistrement, dans une transaction :

1. un seul `User`, avec le mot de passe par défaut et `must_change_password` ;
2. une ligne `establishment_user` active par école cochée, avec le rôle choisi ;
3. si le rôle est `enseignant`, une ligne `Teacher` par école cochée, comme le fait `Staff\Index`. `ManageOrganization` ne la créait pas jusqu'ici.

Le message d'erreur sur un e-mail déjà utilisé renvoie vers l'action « Ajouter à d'autres écoles » de la section 2.

## 2. Ajouter une personne existante à d'autres écoles

Sur chaque personne de la liste « Utilisateurs », une action « Ajouter à d'autres écoles », pour une fondation uniquement. Elle est masquée s'il ne reste aucune école à proposer.

Le formulaire propose les écoles de la fondation où la personne n'a aucune ligne, et le rôle parmi `ASSIGNABLE_ROLES`. Il exige au moins une école.

À l'enregistrement, dans une transaction : une ligne `establishment_user` active par école cochée et, pour un enseignant, une ligne `Teacher` par école. Le compte et le mot de passe de la personne ne changent pas.

Le rôle peut différer d'une école à l'autre, en refaisant l'action.

## 3. Règles

- **Une personne, un rôle par école.** La contrainte unique porte sur (école, personne, rôle) et accepterait deux rôles, mais `roleFor()` n'en lit qu'un seul. Le serveur refuse donc une école où la personne a déjà une ligne, active ou non. Une ligne inactive se réactive depuis la liste, comme aujourd'hui.
- **Personnes de l'organisation uniquement.** La personne est choisie à partir d'une ligne de la liste, résolue par `findStaffPivot()`, jamais d'un e-mail saisi. Un fondateur ne peut donc ni savoir qu'une adresse a un compte ailleurs, ni s'attacher le compte d'une autre école.
- **Personnes concernées** : celles qui ont un rôle de `ASSIGNABLE_ROLES` dans l'organisation. Les fondateurs et les parents sont exclus, car ils ont leurs propres flux.
- **Droits** : `manageOrganization`, donc l'administrateur général de l'organisation, comme le formulaire actuel.
- **Gestion ensuite** : la liste garde une ligne par école. On désactive ou supprime la personne école par école.

**Limite connue** : une personne qui a déjà un compte hors de l'organisation ne peut pas être ajoutée. Il lui faut un autre e-mail.

## 4. Tests

- Création sur une école et sur plusieurs écoles : un seul compte, une ligne par école.
- Aucune école cochée et école hors de l'organisation : refus, et rien n'est créé.
- École indépendante : son école est prise d'office.
- Enseignant : une fiche `Teacher` par école, à la création comme à l'ajout.
- Ajout d'une personne existante, avec un rôle différent, sans changement de mot de passe.
- Ajout refusé pour une école où la personne figure déjà, y compris inactive.
- Ajout refusé pour une personne hors de l'organisation, un fondateur ou un parent.
- Refus pour un fondateur qui n'est pas administrateur général.
- Navigateur : création d'un caissier sur deux écoles, connexion, puis changement d'école avec le bon rôle.

## Hors périmètre

- Pas de choix du personnel dans le formulaire d'inscription du fondateur.
- Pas d'ajout d'une personne extérieure à l'organisation.
- Pas de rôle différent par école dans le formulaire de création : on passe par l'action d'ajout.
- Pas de changement du rôle d'une ligne existante.
- Pas de désactivation groupée sur toutes les écoles d'une personne.
