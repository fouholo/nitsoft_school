# Compositions du primaire arabe et relevé de notes

*(Validé par l'utilisateur le 2026-10-30.)*

Modèle du relevé : livret de composition mensuelle fourni par l'utilisateur (« Compos primaire 1 et 2 »). S'appuie sur les classes arabes ([2026-10-20-classes-arabes-design.md](2026-10-20-classes-arabes-design.md)) et reprend le fonctionnement des compositions du primaire français ([2026-08-15-composition-primaire-toutes-matieres-design.md](2026-08-15-composition-primaire-toutes-matieres-design.md)).

## Décisions validées

| Sujet | Décision |
|---|---|
| Identification | Par mois : au plus une composition par mois et par année |
| Notes | Sur 10, sans coefficient (le secondaire arabe sera sur 20, sans coefficient) |
| Moyenne | Total ÷ nombre de matières notées |
| Absence | Matière retirée du calcul ; élève sans aucune note : pas de moyenne, pas de rang |
| Matières | Chaque école choisit ses matières et leur ordre, par niveau arabe |

## Données

- `arabic_level_subjects` (`ArabicLevelSubject`, `TenantScoped`) : `establishment_id`, `arabic_level_id`, `arabic_subject_id`, `sequence`, timestamps. Unique (`establishment_id`, `arabic_level_id`, `arabic_subject_id`).
- `arabic_compositions` (`ArabicComposition`, `TenantScoped`) : `establishment_id`, `school_year_id`, `month` (1 à 12), `graded_on`, `created_by`, timestamps. Unique (`establishment_id`, `school_year_id`, `month`). Ordre scolaire : septembre à août.
- `arabic_composition_grades` (`ArabicCompositionGrade`, `TenantScoped`) : `establishment_id`, `arabic_composition_id`, `enrollment_id`, `arabic_subject_id`, `score` (nullable, décimal 4,2), `is_absent`, timestamps. Unique (`arabic_composition_id`, `enrollment_id`, `arabic_subject_id`).
- `arabic_composition_results` (`ArabicCompositionResult`, `TenantScoped`) : `establishment_id`, `arabic_composition_id`, `enrollment_id`, `arabic_classroom_id`, `total`, `average`, `mention`, `rank`, `appreciation` (texte libre du maître, 200 caractères au plus), `generated_at`, timestamps. Unique (`arabic_composition_id`, `enrollment_id`).

Pas de `SoftDeletes` ; pas `Syncable` (comme les emplois du temps arabes).

## Règles

Service `App\Domain\Arabic\Services\ArabicCompositionCalculator` :

- notes sur 10 (constante `MAX_SCORE`) ;
- `summarize(iterable $grades): array{total, count, average, mention}` : une note absente ou vide ne compte pas ; `average = round(total / count, 2)` ; `average = null` si `count = 0` ;
- mention : `AppreciationScale::forAverage($average, 10.0)?->appreciation` ;
- `generate(ArabicComposition, ArabicClassroom): Collection` : calcule les résultats des inscriptions actives de la classe. Rang dans la classe, ex æquo au même rang, l'élève sans moyenne n'est pas classé. L'appréciation déjà saisie est conservée.

Effectif : nombre d'inscriptions actives de la classe.

À la génération, le résultat d'un élève qui a quitté la classe est retiré. L'écran « Relevés » signale les notes modifiées après la dernière génération.

## Périmètre

- Écoles `is_arabe` de type préscolaire-primaire (`Establishment::isPrescolairePrimaire()`), classes arabes dont le niveau est au cycle **primaire**.
- Une école franco-arabe secondaire n'y a pas accès.
- Dans une école primaire franco-arabe, le menu Arabe n'affiche plus « Coefficients par matière », « Périodes », « Grilles de notes » et « Bulletins ». Ces écrans restent inchangés pour le secondaire.

## Droits

`ArabicCompositionPolicy` :

- `viewAny` : membre d'une école franco-arabe primaire, sauf caissier et parent ;
- `create`, `delete` : directeur (comme en français) ; suppression refusée si la composition a des notes ;
- `accessClassroom(User, ArabicClassroom)`, pour saisir les notes et consulter les relevés : éducateur (`grades.enter`), fondateur, directeur, gestionnaire, ou enseignant affecté à la classe arabe ;
- `generate` : fondateur, directeur, gestionnaire, ou `report_cards.generate`.

`ArabicLevelSubjectPolicy` : `viewAny` comme ci-dessus ; `manage` : fondateur, directeur, gestionnaire.

## Écrans (menu Arabe)

- **Matières du primaire** (`/arabic/primary-subjects`, `Arabic\Compositions\LevelSubjects`) : choix du niveau arabe primaire, cases à cocher des matières du catalogue, boutons monter / descendre pour l'ordre.
- **Compositions** (`/arabic/compositions`, `Arabic\Compositions\Index`) : liste, « Nouvelle composition » (mois, date), suppression.
- **Élèves d'une composition** (`/arabic/compositions/{arabicComposition}`, `Arabic\Compositions\Students`) : choix de la classe arabe primaire, liste des élèves, nombre de notes saisies. Un enseignant ne voit que ses classes.
- **Saisie** (`/arabic/compositions/{arabicComposition}/enrollments/{enrollment}`, `Arabic\Compositions\EnterStudent`) : note /10 et absence par matière, « absent à la composition », aperçu en direct (total, moyenne, mention), appréciation du maître. Message renvoyant vers « Matières du primaire » si le niveau n'a aucune matière.
- **Relevés** (`/arabic/composition-reports`, `Arabic\Compositions\Reports`) : classe + composition, « Générer les relevés », liste par rang, PDF individuel et PDF groupé.

## Relevé PDF

- Routes : `reports.arabic-composition-result-pdf` (un élève), `reports.arabic-composition-class-pdf` (classe). Rendu à la volée, format A5, `download=1` pour télécharger.
- En-tête : école, année scolaire, « شهر يونيو / Mois de juin », nom, classe, matricule.
- Tableau : Matières (nom français) | Notes / الدرجات | المواد (nom arabe). Absence : « Abs ». Colonnes écrites dans l'ordre visuel.
- Résultats bilingues : Total / مجموع الدرجات, Moyenne / المعدل, Mention / التقدير, Rang / الترتيب, Effectif / عدد التلاميذ. Ligne Observation / الملاحظات vide.
- Signatures : Le Maître / توقيع المعلم, Visa des parents / توقيع الأهل ; Appréciation du maître / تقدير المعلم (texte saisi ou pointillés).
- Textes arabes de plusieurs mots passés par `pdf_rtl()`.
- Une page A5 avec les 20 matières du livret, vérifié sur le rendu réel. Noto Naskh agrandit la hauteur de ligne : les cellules arabes ont un `line-height` réduit, sans quoi chaque rang passe à 16 pt et le relevé déborde.
- L'appréciation du maître est limitée à 200 caractères (deux lignes), pour que le relevé tienne toujours sur une page.

## Tests (Pest)

- Matières par niveau, ordre.
- Composition : unicité par mois, droits, suppression refusée s'il y a des notes.
- Saisie : plafond à 10, absences, enseignant affecté ou non, classe d'une autre école.
- Calcul : moyenne, absences, rang et ex æquo, élève non classé, appréciation conservée.
- Filtrage : classes secondaires et préscolaires exclues, école secondaire refusée, menu.
- PDF : individuel, groupé, téléchargement, école étrangère, ordre des mots arabes.

## Hors périmètre

- Bulletin du secondaire arabe (trimestres, notes sur 20 sans coefficient, Résultat, président du jury).
- Notes arabes dans le portail parents.
- Appel par classe arabe (en pause).
