# Bulletin du secondaire arabe au format de l'école

*(Validé par l'utilisateur le 2026-10-30.)*

Modèle : bulletin de notes fourni par l'utilisateur (« Rel Arabe 6E »). Le calcul sans coefficient est déjà en place (commit `d7efe48`).

## Décisions validées

| Sujet | Décision |
|---|---|
| Noms en arabe | Champs facultatifs (élève, école) ; repli sur le nom latin |
| Résultat | Sur chaque bulletin : Admis / ناجح si moyenne ≥ 10/20, sinon Non admis / راسب |
| Notes | Sur 20, sans coefficient |

## Données (toutes facultatives)

- `students.arabic_name`, saisi sur le formulaire de l'élève.
- `establishments.name_ar` et `establishments.city`, saisis sur la fiche de l'établissement.
- `arabic_terms.label_ar`, saisi sur l'écran des périodes.

## Ordre des matières

- L'écran `Arabic\Compositions\LevelSubjects` devient « Matières par niveau » et s'ouvre aux écoles franco-arabes secondaires. Les niveaux proposés sont ceux des cycles de l'école, préscolaire exclu.
- `ArabicLevelSubjectPolicy` exige `is_arabe` au lieu du primaire. Les compositions restent réservées au primaire.
- `ArabicReportCardService::subjectBreakdown` suit l'ordre choisi. Une matière notée hors liste s'affiche à la fin, par ordre alphabétique.

## Bulletin PDF (A4, `pdf.arabic-report-card`)

1. En-tête sur trois colonnes : français à gauche (République de Côte d'Ivoire, Union - Discipline - Travail, école, Franco-Arabe, année scolaire), logo au centre, arabe à droite (جمهورية كوت ديفوار, اتحاد - نظام - عمل, nom arabe de l'école, année scolaire).
2. Titre : « كشف الدرجات » + niveau arabe, puis « BULLETIN DE NOTES » + niveau français.
3. Identité : Nom et prénoms / الاسم واللقب, période dans les deux langues.
4. Tableau, dans l'ordre visuel : bloc Effectif et Résultat, MATIERES, NOTE / الدرجات, المواد الدراسية.
5. Résultats : Moyenne / المعدل, Total / المجموع, Rang / الترتيب, Mention / التقدير.
6. Date : ville de l'école et date d'édition ; sans ville, la date seule.
7. Signatures : Directeur général / المدير العام, Cachet Officiel / الختم الرسمي, Président du Jury / رئيس لجنة الامتحان.

Le nom de l'enseignant et les appréciations par matière ne figurent plus sur le bulletin.

## Règles

- Total : somme des moyennes des matières. Moyenne : total ÷ matières notées (déjà calculée par `ArabicReportCardService`).
- Résultat : `average >= 10` donne Admis, sinon Non admis.
- Effectif : inscriptions actives de la classe arabe de l'élève.

## Différences assumées avec le modèle

- Pas de filigrane du logo.
- Pas de mention verticale « المواد الدراسية ».
- Année en chiffres courants.

## Tests (Pest)

- Saisie des nouveaux champs, repli quand ils sont vides.
- Écran des matières ouvert au secondaire ; compositions toujours refusées au secondaire.
- Ordre des matières, matière hors liste.
- Résultat au seuil de 10, total, effectif.
- PDF : contenu HTML, ordre des mots arabes ; rendu réel contrôlé par la position des mots.

## Hors périmètre

- Notes arabes dans le portail parents.
- Appel par classe arabe.
