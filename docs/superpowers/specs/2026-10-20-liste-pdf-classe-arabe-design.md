# Liste PDF d'une classe arabe (sous-chantier 2)

*(Validé par l'utilisateur le 2026-10-20.)*

Suite de [2026-10-20-classes-arabes-design.md](2026-10-20-classes-arabes-design.md) (sous-chantier 1 : classes arabes, classe principale et classe secondaire).

## Décisions validées

| Sujet | Décision |
|---|---|
| Langue | Liste en arabe, de droite à gauche ; noms des élèves tels que saisis |
| En-tête officiel | Inchangé, en français (ministère, direction, inspection, République) |
| Accès | Écran « Listes/Rapports » (avec filtres) **et** lien sur chaque ligne de l'écran « Classes arabes » (sans filtres) |
| Filtres | Sexe, redoublement **arabe** (`is_arabic_repeating`) |

## Génération

- Route `GET /rapports/classes-arabes/{arabicClassroom}/eleves` (`reports.arabic-classroom-students-pdf`), dans `routes/reports.php`.
- Contrôleur `App\Http\Controllers\Arabic\ArabicClassroomStudentListPdfController`. Comme la liste française, le PDF est rendu à la volée et jamais stocké ; `download=1` force le téléchargement.
- Accès : `ArabicClassroomPolicy::view` (même établissement). Une classe d'une autre école renvoie 404, car la liaison de route passe par le filtrage par établissement.
- Liste des élèves : `App\Domain\Arabic\Services\ArabicClassroomRoster::students(ArabicClassroom $arabicClassroom, ?string $gender, ?string $repeating): Collection<Student>` :
  - inscriptions actives dont `arabic_classroom_id` est la classe, qu'elle soit principale ou secondaire ;
  - tri par nom puis prénom ;
  - `gender` vaut `m`, `f` ou vide ; `repeating` vaut `1`, `0` ou vide, et s'applique à `is_arabic_repeating`.

## Document (`resources/views/pdf/arabic-classroom-student-list.blade.php`)

- **En-tête** : la partie commune `pdf.partials.reports-header`, inchangée.
- **Titre**, en arabe et de droite à gauche : « قائمة تلاميذ القسم » suivi du libellé arabe du niveau (`wording`), de la série (`serie_wording`) et du numéro. Le nom de la classe (`name`, ex. « CP1 AR A ») est en petit dessous.
- **Filtres actifs** : ذكور / إناث, معيدون / غير معيدين, séparés par « · ».
- **Tableau** (`direction: rtl`, police Noto Naskh Arabic comme le bulletin arabe) :
  - colonnes : الرقم, رقم التسجيل, اللقب, الاسم, الجنس (ذ / أ), تاريخ ومكان الميلاد ;
  - ligne vide : « لا يوجد تلاميذ مسجلون في هذا القسم ».
- **Cachet** : `pdf.partials.director-stamp` reçoit un paramètre facultatif `title`, « Le directeur » par défaut ; ici « المدير ». Les autres documents ne changent pas.
- **Pied de page** : « أنشئت في » suivi de la date et de l'heure (`d/m/Y H:i`).
- **Nom du fichier** : `liste-eleves-arabe-<nom de la classe>.pdf`, après `Str::slug`.

## Accès dans l'application

- **Listes/Rapports** (`Reports\Index`) : un bloc « Liste des élèves d'une classe arabe », affiché seulement si l'établissement est `is_arabe`. Il contient l'année scolaire, la classe arabe de cette année, le sexe et le redoublement (arabe), puis le bouton « Liste des élèves (PDF) ». Ses propriétés sont distinctes de celles du bloc français.
- **Classes arabes** (`Arabic\Classrooms\Index`) : un lien « Liste PDF » sur chaque ligne, dans un nouvel onglet, sans filtres.

## Tests (Pest)

- `ArabicClassroomRoster` :
  - tri par nom ;
  - élèves dont la classe arabe est principale ou secondaire ;
  - exclusion des autres classes et des inscriptions retirées ;
  - filtres sexe et redoublement, seuls puis combinés.
- Contrôleur : PDF affiché dans le navigateur, téléchargement, classe d'une autre école renvoyant 404.
- Rendu HTML de la vue : titre arabe, nom de la classe, libellés des filtres, « المدير », ligne vide.
- `Reports\Index` : le bloc est présent dans une école franco-arabe et absent sinon ; le lien porte les filtres.
- `Arabic\Classrooms\Index` : le lien « Liste PDF » est présent.

## Hors périmètre

- Cartes d'identité scolaires par classe arabe.
- Traduction de l'en-tête officiel.
