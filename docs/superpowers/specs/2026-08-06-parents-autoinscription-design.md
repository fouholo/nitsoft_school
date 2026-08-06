# Auto-inscription des parents et liaison par uid

**Date** : 2026-08-06
**Statut** : Approuvé

## Contexte

La demande initiale de l'utilisateur ("ajouter nom du père, contact du père, nom de la mère, contact de la mère, nom du tuteur, contact du tuteur, et un contact principal pour l'envoi de SMS") a été confrontée au système `Guardian` déjà existant (`app/Domain/Enrollment/Models/Guardian.php`), qui couvre déjà nom/téléphone/rôle par élève avec une liaison compte portail parent, via une table pivot `guardian_student` qui possède déjà un champ `is_primary_contact`.

L'exploration a révélé deux trous réels dans l'existant :
1. `is_primary_contact` n'est pas exclusif — rien n'empêche plusieurs tuteurs du même élève d'être marqués principaux simultanément.
2. Le listener `NotifyGuardiansOfAbsence` ignore complètement ce champ et envoie un SMS à **tous** les tuteurs ayant un téléphone, pas seulement au principal.

En creusant avec l'utilisateur, la demande s'est élargie : au lieu de simples champs d'information, il veut un système d'**auto-inscription des parents** — un parent crée son propre compte, se lie à un enfant via l'uid de synchronisation de l'élève (`docs/superpowers/specs/2026-08-06-uid-synchronisation-design.md`), et cette liaison doit être validée par le directeur avant de devenir active. Un parent peut être lié à plusieurs enfants.

## Décisions validées avec l'utilisateur

1. **Réutiliser et adapter `Guardian`**, pas de colonnes à plat sur `students` — évite la duplication de données et préserve le mécanisme de compte portail parent déjà construit.
2. **Remplacement complet du système actuel** de création manuelle de compte portail par l'admin : un nouveau système d'auto-inscription + liaison par uid + validation devient le parcours principal, tout en gardant le parcours admin existant en filet de sécurité (voir décision 6).
3. **Inscription** : e-mail + mot de passe comme identifiant de connexion (cohérent avec l'auth actuelle), téléphone capturé séparément pour l'envoi de SMS.
4. **Rôle du parent** : choix fermé père / mère / tuteur au moment de la demande de liaison (remplace le champ texte libre `relationship` actuel). Au plus un lien approuvé par rôle et par élève (3 maximum).
5. **Validation** : écran dédié "Demandes de liaison" listant les demandes en attente de l'établissement, avec Approuver/Rejeter. Si le rôle demandé est déjà pourvu, le directeur arbitre au cas par cas plutôt qu'un blocage automatique.
6. **Contact principal SMS** : reste désigné côté staff (fiche élève), devient réellement exclusif. `NotifyGuardiansOfAbsence` n'envoie qu'à ce contact — pas de repli vers tous les parents si aucun n'est désigné.
7. **Compatibilité** : le parcours admin actuel (créer un tuteur, l'attacher à un élève depuis la fiche élève) reste disponible, pour les cas où le parent n'a pas accès à l'auto-inscription. Les liens créés ainsi sont directement `approved` (l'admin vouche directement, pas de file d'attente).

## Design

### 1. Modèle de données

- `Guardian` perd le trait `TenantScoped`. Un parent auto-inscrit n'appartient à aucun établissement tant qu'il n'est lié à aucun élève, et peut avoir des enfants dans des établissements différents — le tenant scoping se fait désormais uniquement au niveau du lien (`guardian_student.establishment_id`, déjà présent), pas du profil parent lui-même.
- `guardian_student` : ajout d'un champ `status` (`pending` / `approved` / `rejected`, défaut `pending`). `relationship` devient un enum fermé (père/mère/tuteur) au lieu de texte libre.
- Contrainte applicative (pas une contrainte SQL) : au plus un lien `approved` par (`student_id`, `relationship`) — vérifiée à l'approbation, pas à la demande.

### 2. Inscription parent (libre-service)

Nouveau composant `Livewire\Auth\Register` (route publique, pas d'authentification requise) : nom, e-mail + mot de passe, téléphone. Crée un `User` + un profil `Guardian` (`user_id` renseigné), sans établissement ni lien à un élève à ce stade. Pas de vérification e-mail pour l'instant — cohérent avec l'authentification actuelle, volontairement légère (comptes admin créés sans ce flux non plus).

### 3. Demande de liaison par uid

Le parent connecté saisit le uid d'un élève (recherche globale sur `Student::where('uid', ...)`, pas besoin de connaître l'établissement au préalable — c'est justement l'intérêt du uid global). S'il existe, le parent choisit son rôle (père/mère/tuteur) et confirme → crée une ligne `guardian_student` en `status='pending'` avec l'`establishment_id` de l'élève trouvé. Le contact principal n'est pas choisi à cette étape — voir §5.

### 4. Écran "Demandes de liaison" (admin)

Nouvel écran établissement-scopé listant les liens `pending` : parent (nom/téléphone/e-mail), élève, rôle demandé, boutons Approuver/Rejeter.
- Si le rôle demandé est déjà pourvu par un lien `approved` pour cet élève, l'écran l'indique clairement avant confirmation.
- Approuver alors que le rôle est déjà pourvu **remplace automatiquement** l'ancien titulaire (son lien passe à `rejected`) dans la même action.
- Approuver provisionne aussi, si elle n'existe pas déjà, une ligne `establishment_user` (`role='parent'`) pour cet établissement — ceci afin que le RBAC existant (`currentRole()`, redirection `home` vers le portail parent, switcher) continue de fonctionner sans modification de sa logique.

### 5. Contact principal exclusif + SMS

Le cochage "contact principal" reste une action côté staff sur la fiche élève (pattern déjà existant), mais devient réellement exclusif : le fixer sur un parent décoche les autres pour ce même élève, dans la même transaction. `NotifyGuardiansOfAbsence` n'interroge plus que le lien `approved` + `is_primary_contact=true` de l'élève concerné et envoie un seul SMS à ce contact. Si aucun contact principal n'est désigné, **aucun SMS n'est envoyé** — pas de repli vers l'ensemble des parents liés.

### 6. Parcours admin existant (compatibilité)

Le flux actuel (écran Tuteurs → créer un tuteur → l'attacher à un élève depuis la fiche élève) reste disponible tel quel. Les liens créés par ce biais sont insérés directement en `status='approved'`. L'écran Tuteurs, qui listait auparavant tous les `Guardian` de l'établissement via `TenantScoped`, doit être adapté pour filtrer via les liens `approved` de l'établissement courant, puisque `Guardian` n'est plus scopé par établissement.

### 7. Accès portail parent

`EnsuresGuardianAccess` (trait existant régissant l'accès aux routes `/portal`) est mis à jour pour n'autoriser l'accès qu'aux liens `status='approved'` — un lien en attente ne doit donner accès à aucune donnée de l'élève concerné.

### 8. Erreurs / cas limites

- uid inconnu à la demande de liaison → message de validation clair, pas d'exception brute.
- E-mail déjà utilisé à l'inscription → erreur de validation standard.
- Rôle déjà pourvu au moment de la demande → acceptée quand même dans la file d'attente, arbitrage au moment de l'approbation (§4), pas de blocage a priori.
- Retrait de `TenantScoped` sur `Guardian` : tout code existant qui supposait implicitement un scope établissement sur les requêtes `Guardian::` (écran Tuteurs, fiche élève, `NotifyGuardiansOfAbsence`, `EnsuresGuardianAccess`) doit être audité et corrigé — point de vigilance explicite pour le plan d'implémentation, pas quelque chose à deviner en cours de route.

### 9. Tests

- Inscription : création `User` + `Guardian` correcte, e-mail déjà utilisé rejeté.
- Demande de liaison : uid valide crée un lien `pending` avec le bon `establishment_id` ; uid invalide rejeté proprement.
- Approbation : statut passe à `approved`, `establishment_user` provisionné si absent, remplacement correct d'un rôle déjà pourvu (ancien lien passe à `rejected`).
- Rejet : statut `rejected`, aucun accès portail.
- Exclusivité du contact principal : en fixer un décoche les autres pour le même élève.
- SMS : envoyé uniquement au contact principal `approved` ; aucun SMS si aucun contact principal désigné.
- Accès portail : bloqué tant que le lien est `pending` ou `rejected`.

## Hors périmètre (explicitement écarté)

- Vérification d'e-mail à l'inscription (peut être ajoutée plus tard si besoin réel).
- Écran/switcher spécifique pour un parent lié à des enfants dans plusieurs établissements différents — le mécanisme actuel (une ligne `establishment_user` par établissement où le parent a un enfant approuvé) suffit pour l'instant, un vrai tableau de bord multi-établissements resterait un chantier futur distinct si demandé.
- Renommage des écrans/routes "Tuteurs" existants en "Parents" — non demandé, seul le comportement change.
