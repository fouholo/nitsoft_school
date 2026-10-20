# Emploi du temps des classes arabes (sous-chantier 3)

*(Validé par l'utilisateur le 2026-10-20.)*

Suite de [2026-10-20-classes-arabes-design.md](2026-10-20-classes-arabes-design.md) et [2026-10-20-liste-pdf-classe-arabe-design.md](2026-10-20-liste-pdf-classe-arabe-design.md). S'appuie sur l'emploi du temps français ([2026-09-01-emplois-du-temps-secondaire-design.md](2026-09-01-emplois-du-temps-secondaire-design.md)).

## Décisions validées

| Sujet | Décision |
|---|---|
| Organisation de la semaine | Variable d'une école à l'autre : grille de créneaux arabe propre à chaque école, jours de cours arabes choisis par l'école (samedi et dimanche possibles) |
| Chevauchement avec le français | **Refusé** quand des élèves de la classe arabe ont un cours français qui chevauche, le même jour ; également refusé pour l'enseignant et la salle, dans les deux filières |
| Cycles | Toutes les classes arabes, quel que soit le cycle (le français reste réservé au secondaire) |
| Enseignant | Un seul « Mon emploi du temps » qui réunit les deux filières |
| PDF d'une classe arabe | En arabe, de droite à gauche, sous l'en-tête officiel français |

## Modèle

- `App\Domain\Timetable\Enums\DayOfWeek` reçoit `Saturday` (« Samedi ») et `Sunday` (« Dimanche »), et la méthode `weekdays(): array` (lundi à vendredi). Les écrans et PDF français utilisent `weekdays()` au lieu de `cases()` : leur affichage ne change pas. La méthode `arabicLabel(): string` donne les libellés arabes (الإثنين … الأحد).
- `establishments.arabic_timetable_days` : JSON, nullable, casté en tableau. Une valeur nulle vaut du lundi au vendredi. `Establishment::arabicTimetableDays(): list<DayOfWeek>` renvoie les jours dans l'ordre de la semaine.
- `arabic_timetable_slots` (`ArabicTimetableSlot`), miroir de `timetable_slots` : `establishment_id`, `label`, `start_time`, `end_time`, `sequence`, `is_break`, timestamps.
- `arabic_timetable_sessions` (`ArabicTimetableSession`), miroir de `timetable_sessions` : `establishment_id`, `school_year_id`, `arabic_classroom_id` (`cascadeOnDelete`), `arabic_subject_id`, `user_id`, `day_of_week`, `arabic_timetable_slot_id` (`cascadeOnDelete`), `room` (nullable), timestamps. Index (`establishment_id`, `day_of_week`) et (`arabic_classroom_id`).
- Pas de `SoftDeletes` (comme le français depuis `2026_09_01_030000`), pas `Syncable`.

## Règles de conflit

À l'enregistrement d'une séance arabe (jour J, créneau C de plage [début, fin]), l'application refuse avec un message dans ces cas :

1. la classe arabe a déjà une séance sur le créneau C le jour J ;
2. l'enseignant n'est pas affecté à cette classe arabe pour la matière (`ArabicTeacherAssignment`) ;
3. l'enseignant a, le jour J, une séance arabe ou française dont la plage chevauche [début, fin] ;
4. la salle, si elle est renseignée, est occupée le jour J sur une plage qui chevauche, en arabe ou en français ;
5. une classe française qui partage au moins une inscription active avec la classe arabe a une séance qui chevauche, le jour J. Le message nomme la classe et le nombre d'élèves concernés.

Chevauchement : `début A < fin B` et `début B < fin A`. Deux séances qui se touchent (l'une finit à 10h00, l'autre commence à 10h00) ne se chevauchent pas.

Dans l'autre sens, l'enregistrement d'une séance française (`Academics\Timetable\Index::save`) vérifie aussi :
- les séances arabes de l'enseignant ;
- la salle ;
- les classes arabes qui partagent des élèves avec la classe française.

Service `App\Domain\Timetable\Services\CrossTrackConflicts` :
- `forArabicSession(int $arabicClassroomId, int $userId, ?string $room, DayOfWeek $day, string $start, string $end): ?string` : vérifie le français, renvoie le message ou null ;
- `forFrenchSession(int $classroomId, int $userId, ?string $room, DayOfWeek $day, string $start, string $end): ?string` : vérifie l'arabe ;
- `overlaps(string $startA, string $endA, string $startB, string $endB): bool`.

Les conflits internes à la filière arabe (règles 1, 3 et 4 en arabe) sont vérifiés par l'écran arabe, comme le fait l'écran français pour le français.

Limite assumée, comme en français : modifier les horaires d'un créneau ne recontrôle pas les séances déjà placées.

## Écrans

Menu « Arabe », réservé aux écoles franco-arabes :

- **Grille de créneaux** (`/arabic/timetable/slots`, `Arabic\Timetable\SlotsIndex`) : création, modification et suppression des créneaux arabes, plus les cases à cocher des jours de cours arabes (au moins un jour), enregistrées dans `arabic_timetable_days`.
- **Emplois du temps** (`/arabic/timetable`, `Arabic\Timetable\Browse`) : les classes arabes de l'année courante, avec un lien « Voir la grille » et un lien « PDF ». Réservé au personnel administratif (`browseStaff`).
- **Grille d'une classe arabe** (`/arabic/timetable/{arabicClassroom}`, `Arabic\Timetable\Index`) :
  - jours arabes en colonnes et créneaux arabes en lignes ;
  - un clic sur une case ouvre le formulaire : affectation enseignant / matière de la classe, salle ; on peut enregistrer, supprimer ou annuler ;
  - le message de conflit s'affiche au-dessus de la grille.

Policies `ArabicTimetableSlotPolicy` et `ArabicTimetableSessionPolicy` : mêmes règles que `TimetableSlotPolicy` et `TimetableSessionPolicy` (`timetable.manage`, administrateur local), en exigeant en plus `is_arabe`.

**Vues de l'enseignant**, dans une école franco-arabe :
- « Mon emploi du temps » (`MySchedule`) et la grille d'un enseignant (`TeacherSchedule`) deviennent un **agenda de la semaine** :
  - une colonne par jour, avec l'union des jours français (lundi à vendredi, s'il y a des séances françaises ou des créneaux français) et des jours arabes ;
  - dans chaque colonne, les séances triées par heure : horaire, matière, classe, salle, et l'étiquette « Arabe ».
- Le PDF « Mon emploi du temps » (`TimetableTeacherPdfController`) suit la même présentation (vue `pdf.timetable-agenda`), en français.
- Construction : `App\Domain\Timetable\Services\WeeklyAgenda::forTeacher(int $userId, Establishment $establishment): array{days: list<DayOfWeek>, entries: array<string, list<array>>}`.
- Hors école franco-arabe : rien ne change.

## PDF de l'emploi du temps d'une classe arabe

- Route `GET /rapports/classes-arabes/{arabicClassroom}/emploi-du-temps` (`reports.arabic-timetable-classroom-pdf`), contrôleur `Arabic\ArabicTimetableClassroomPdfController`, format A4 paysage.
- Vue `pdf.arabic-timetable` :
  - en-tête officiel français ;
  - titre « جدول الحصص — » suivi du niveau, de la série et du numéro, avec le nom de la classe dessous ;
  - grille de droite à gauche : colonne des créneaux à droite, jours arabes avec le premier jour à droite. Les colonnes sont écrites dans l'ordre d'affichage, dompdf ignorant `direction: rtl` pour l'ordre des colonnes ;
  - matières passées par `pdf_rtl()` ;
  - « استراحة » sur les lignes de pause ;
  - cachet « المدير » et pied de page « أنشئت في… ».

## Traductions

Nouveaux textes via `__()`, avec la phrase française comme clé, traduits dans `lang/en.json` et `lang/ar.json`.

## Tests (Pest)

- Créneaux arabes : CRUD, jours enregistrés (au moins un), accès refusé hors école franco-arabe ou sans `timetable.manage`.
- Séances arabes :
  - création valide ;
  - affectation exigée ;
  - conflits de classe, d'enseignant (arabe puis français), de salle (arabe puis français) et d'élèves (français) ;
  - jour hors des jours arabes refusé.
- Séances françaises : refus quand l'enseignant, la salle ou des élèves ont une séance arabe qui chevauche.
- `CrossTrackConflicts::overlaps` : chevauchement, inclusion, séances qui se touchent.
- `WeeklyAgenda` et `MySchedule` : fusion triée par heure, étiquette « Arabe », samedi ajouté ; grille française inchangée hors école franco-arabe.
- PDF : affichage dans le navigateur, téléchargement, classe d'une autre école introuvable, rendu (titre, jours arabes, pause).
- Non-régression : la grille française reste du lundi au vendredi.

## Hors périmètre

- Appel par séance arabe (sous-chantier 4).
- Jours de cours configurables pour le français.
- Recontrôle des séances après modification des horaires d'un créneau.
