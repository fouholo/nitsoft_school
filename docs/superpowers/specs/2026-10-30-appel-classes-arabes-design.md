# Appel par classe arabe (sous-chantier 4)

*(Validé par l'utilisateur le 2026-10-30.)*

S'appuie sur [2026-10-20-emploi-du-temps-arabe-design.md](2026-10-20-emploi-du-temps-arabe-design.md) et sur l'appel français ([2026-09-06-appels-lies-emploi-du-temps-design.md](2026-09-06-appels-lies-emploi-du-temps-design.md)).

## Décisions validées

| Sujet | Décision |
|---|---|
| Tables | Réutilisation de `attendance_sessions` et `attendance_records` : une ligne d'appel est par élève, et le SMS, le portail parents et l'historique en profitent |
| SMS d'absence | SMS distinct qui nomme le cours d'arabe, modèle `attendance_absence_arabic` personnalisable par l'école |

## Données

`attendance_sessions` :

- `classroom_id` devient nullable ;
- nouvelles colonnes nullables : `arabic_classroom_id`, `arabic_subject_id`, `arabic_timetable_session_id` (`nullOnDelete`) ;
- unicité (`arabic_timetable_session_id`, `session_date`), comme le lien français ;
- règle vérifiée à l'enregistrement : un appel porte soit sur une classe française, soit sur une classe arabe.

`AttendanceSession` : `isArabic()`, `classroomName()`, `subjectName()`.

## Démarrer l'appel (écran « Présences », école franco-arabe)

- « Mes séances du jour » réunit les séances françaises et arabes du jour, triées par heure, avec l'étiquette « Arabe ». Samedi et dimanche sont couverts s'ils sont des jours de cours arabes.
- « Faire l'appel » (`startFromArabicTimetable`) crée l'appel de la séance, ou rouvre celui du jour.
- Saisie libre : un champ « Filière » (française ou arabe) propose les classes et les matières correspondantes ; un enseignant ne voit que ses affectations.
- Historique : classe et matière de chaque appel, avec l'étiquette « Arabe ».
- Hors école franco-arabe : rien ne change.

## Faire l'appel

La liste d'un appel arabe est celle de la classe arabe : inscriptions actives, classe principale ou secondaire. Statuts, « tous présents » et notes sont inchangés.

## Droits

- Créer un appel : les administrateurs de l'école, ou un enseignant ayant une affectation française ou arabe.
- Faire l'appel : les administrateurs, ou l'enseignant de l'appel s'il est affecté à la classe.

## SMS

- Une absence à un appel arabe utilise le modèle `attendance_absence_arabic`. Texte par défaut : « {{guardian_name}}, votre enfant {{student_name}} a été marqué(e) absent(e) au cours d'arabe aujourd'hui. »
- Les absences françaises sont inchangées.
- L'écran des modèles de SMS mentionne le nouveau code.

## Portail parents

Inchangé : l'historique liste déjà chaque appel par date et statut.

## Tests (Pest)

- Démarrage depuis une séance arabe : création, réouverture, enseignant d'une autre séance refusé.
- Saisie libre arabe, classe d'une autre école refusée.
- Liste des élèves d'un appel arabe, classe principale ou secondaire.
- Droits : enseignant affecté ou non.
- SMS : modèle arabe, texte par défaut, SMS français inchangé.
- Séances du jour : les deux filières, tri par heure, samedi.
- Hors école franco-arabe : pas de séance arabe ni de champ « Filière ».

## Hors périmètre

- Statistiques par filière.
- Affichage de la filière dans le portail parents.
