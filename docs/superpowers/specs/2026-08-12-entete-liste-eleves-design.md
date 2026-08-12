# En-tête administratif de la liste des élèves (PDF)

## Contexte

Demande explicite de l'utilisateur : remplacer l'en-tête actuel (logo + nom établissement) de `classroom-student-list.blade.php` (chantier précédent, commit `0f5083f`) par un en-tête administratif ivoirien classique à deux colonnes. Décisions actées en clarification :

1. **Armoirie** : nouveau champ uploadable sur la table `general_information` (singleton existant), pas un fichier statique.
2. **"REPUBLIQUE DE CÔTE D'IVOIRE" / "Union-Discipline-Travail"** : texte en dur dans la vue, pas configurable.
3. **Portée** : un partial réutilisable pensé pour toute la famille "Listes/Rapports" (pas seulement cette liste) — les bulletins/reçus PDF existants ne sont pas concernés.
4. **Données manquantes** (pas de direction, pas d'inspection) : la ligne concernée est simplement masquée.
5. **Établissement secondaire** : la ligne "INSPECTION DE L'ENSEIGNEMENT PRESCOLAIRE ET PRIMAIRE" n'apparaît jamais (réservée aux établissements `PrescolairePrimaire`).

## 1. Champ `armoirie_path` sur `general_information`

Nouvelle migration : `armoirie_path` (string, nullable). `GeneralInformation` : ajout au `$fillable`.

`Livewire\GeneralInformation\Edit` étendu — même mécanisme exact que le logo de `Direction` (chantier précédent) : `WithFileUploads`, `?TemporaryUploadedFile $armoirie`, `existingArmoiriePath`, validation `['nullable','image','mimes:jpg,jpeg,png,webp,gif','max:1024']`, suppression de l'ancien fichier avant remplacement, stockage dans `general-information/`. Vue étendue avec le champ fichier + aperçu.

## 2. Partial `resources/views/pdf/partials/reports-header.blade.php`

En-tête à deux colonnes (table HTML pour compatibilité dompdf, pas de flexbox), paramètres attendus : `$establishment` (avec `inspection.direction` chargés), `$generalInformation`.

**Colonne gauche** :
- Nom du ministère (`$generalInformation->nom_ministere`), affiché si renseigné.
- "DIRECTION REGIONALE {`$establishment->inspection->direction->direction_name`}" — affiché uniquement si `$establishment->inspection?->direction` existe.
- "INSPECTION DE L'ENSEIGNEMENT PRESCOLAIRE ET PRIMAIRE {`$establishment->inspection->inspection_name`}" — affiché uniquement si `$establishment->type === EstablishmentType::PrescolairePrimaire` **et** `$establishment->inspection` existe.
- Nom de l'établissement (`$establishment->name`).
- Logo de l'établissement (`$establishment->logo_path`, via `public_path('storage/'.$path)`, même convention que les en-têtes PDF existants).

**Colonne droite** :
- "REPUBLIQUE DE CÔTE D'IVOIRE" (texte fixe).
- "Union-Discipline-Travail" (texte fixe).
- Armoirie (`$generalInformation->armoirie_path`, masquée si absente).
- "Année scolaire : {`$generalInformation->annee_scolaire_courante`}", affiché si renseigné.

## 3. Intégration dans `classroom-student-list.blade.php`

Le bloc `.header` actuel est remplacé par `@include('pdf.partials.reports-header', ['establishment' => $classroom->establishment, 'generalInformation' => $generalInformation])`.

`ClassroomStudentListPdfController` : `loadMissing(['level', 'serie', 'schoolYear', 'establishment.inspection.direction'])` (au lieu de `establishment` seul) et passe `'generalInformation' => GeneralInformation::current()` à la vue.

## 4. Ajustement du seeder (cosmétique)

Les libellés `direction_name`/`inspection_name` seedés actuellement (`"Direction Régionale Abidjan"`, `"Inspection Abidjan 1"`) dupliqueraient les préfixes de l'en-tête. Renommés en libellés courts (ex. `"Abidjan"`, `"Bouaké"` pour les directions ; `"Abidjan 1"`, `"Abidjan 2"`, `"Bouaké"` pour les inspections) — ajustement du jeu de démo uniquement, aucun changement de structure de données.

## 5. Tests

Extension des tests existants de `ClassroomStudentListPdfTest.php` (rendu de vue directe, gabarit déjà utilisé) :
- En-tête complet affiché quand ministère/direction/inspection/armoirie/année scolaire sont tous renseignés (établissement préscolaire/primaire).
- Ligne direction absente quand `uid_direction` est vide sur l'inspection.
- Ligne inspection absente quand `inspection_id` est vide sur l'établissement.
- Ligne inspection absente pour un établissement secondaire même avec une inspection renseignée.
- Armoirie absente quand `armoirie_path` est vide sur `general_information`.

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : upload d'une armoirie dans les informations générales, génération du PDF liste des élèves pour un établissement préscolaire/primaire avec inspection+direction renseignées (en-tête complet), puis pour un établissement secondaire ou sans inspection (lignes absentes correctement).
5. Commit puis mise à jour de la mémoire projet.
