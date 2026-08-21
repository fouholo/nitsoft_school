# Filière arabe — sous-chantier 1 : fondations — design

*Statut : approuvé par l'utilisateur le 2026-08-21.*

## Contexte

Demande initiale : « On va entamer des modifications pour le cas où l'école enseigne aussi l'arabe. » Chantier explicitement qualifié de « colossal » par l'utilisateur — décomposé en 3 sous-chantiers indépendants, chacun avec sa propre spec/plan/implémentation :

1. **Fondations arabe** (ce document) — catalogues, coefficients, écrans d'administration.
2. Affectation des enseignants arabes + saisie des notes.
3. Bulletins arabes (génération + PDF en écriture arabe RTL).

Constat de départ : `Establishment.is_arabe` (boolean, défaut `false`) existe déjà en base depuis la migration `2026_08_11_010100_add_administrative_fields_to_establishments_table.php` — une simple case à cocher « École arabe » dans 2 écrans (SaaS admin, création d'établissement fondateur), **sans aucune logique métier** : le design doc `2026-08-11-champs-etablissements-design.md` le classait explicitement « hors périmètre ». Ce chantier active enfin ce flag.

## Décisions validées

1. **Nature du besoin** : une vraie filière franco-arabe parallèle (matières, coefficients, bulletins arabes propres) — pas une simple matière ajoutée au cursus existant, pas une école coranique autonome avec son propre calendrier de bout en bout.
2. **Périmètre élèves** : dans un établissement `is_arabe`, **tous** les élèves suivent systématiquement les deux cursus (français + arabe). Pas de choix de filière à l'inscription, pas de distinction français-seul/arabe-seul.
3. **Bulletins** : deux bulletins séparés par élève (français et arabe), chacun avec sa propre moyenne/rang — traité au sous-chantier 3, mais structure la conception dès maintenant les modèles de fondation (aucune donnée arabe ne doit se mélanger aux tables françaises).
4. **Périodes** : le cursus arabe reproduit la distinction déjà en place côté français — périodes datées (« ArabicTerm », comme `Term`) pour le cycle Secondaire-équivalent, numéro de composition séquentiel pour Préscolaire/Primaire-équivalent (comme `composition_number`). Pas de nouveau modèle de période dans ce sous-chantier (le numéro de composition arabe sera un simple champ entier porté par les notes/bulletins arabes au sous-chantier 2/3) ; `ArabicTerm` sera introduit au sous-chantier 2, quand la saisie de notes en aura besoin.
5. **Séries arabes** : la filière arabe a sa propre notion de série (comme A/C/D en Secondaire français), rattachée **à l'élève** (`Enrollment`), pas à la classe — un élève peut avoir une série arabe différente de ses camarades de classe française.
6. **Niveaux arabes** : nomenclature totalement indépendante des niveaux français (pas CP1→Tle) — un établissement peut avoir une progression arabe/coranique qui ne correspond pas 1:1 à l'âge scolaire français. Catalogue **partagé, géré par l'admin SaaS** (comme les niveaux français), pas par établissement.
7. **Écriture arabe (RTL)** : les champs texte arabes doivent afficher du vrai texte arabe avec direction RTL sur les champs concernés (pas la page entière). Le rendu RTL en PDF (bulletins) est explicitement hors périmètre de ce sous-chantier — traité au sous-chantier 3.
8. **Approche technique** : nouveau domaine `App\Domain\Arabic\` avec des modèles dédiés, séparés des modèles français — cohérent avec le précédent déjà en place dans ce code (`Subject`/`PrimarySubject` déjà séparés pour une raison similaire). Aucune modification des tables françaises existantes (`subjects`, `primary_subjects`, `terms`, `series`, `levels`).
9. **Simplification par rapport au français** : un seul modèle `ArabicSubject` pour tous les cycles (au lieu du couple `Subject`/`PrimarySubject`) — justifié techniquement : le pattern français `PrimarySubject` bake les coefficients en colonnes fixes (`coefficient_cp1`…`coefficient_cm2`) parce que la nomenclature Préscolaire/Primaire française est un ensemble fixe et connu de 6 niveaux ; la nomenclature `ArabicLevel` étant libre et définie par l'admin SaaS (nombre de niveaux inconnu à l'avance), les coefficients doivent obligatoirement passer par une table de jointure (`ArabicSubjectCoefficient`), qu'il s'agisse d'un niveau arabe « primaire-équivalent » ou « secondaire-équivalent ». Un seul modèle suffit donc.

## 1. Modèles (`App\Domain\Arabic\Models\`)

### `ArabicLevel`

Catalogue global (pas de `establishment_id`), géré par l'admin SaaS — miroir de `Level` mais nomenclature libre.

```php
Schema::create('arabic_levels', function (Blueprint $table) {
    $table->id();
    $table->string('code');           // libre, ex. "N1", "Houffadh"
    $table->string('wording');        // libellé arabe, affiché dir="rtl"
    $table->string('cycle');          // Cycle::class (Prescolaire|Primaire|Secondaire) — réutilisé tel quel
    $table->boolean('requires_series')->default(false);
    $table->timestamps();
});
```

`cycle` réutilise l'enum `App\Domain\Academics\Enums\Cycle` existant (pas de nouvel enum) : c'est un concept de palier pédagogique générique, pas la propriété exclusive du modèle `Level` français. Il détermine, aux sous-chantiers suivants, si le niveau utilise `ArabicTerm` (Secondaire-équivalent) ou un numéro de composition (Préscolaire/Primaire-équivalent).

### `ArabicSerie`

Catalogue global, miroir simple de `Serie`.

```php
Schema::create('arabic_series', function (Blueprint $table) {
    $table->id();
    $table->string('serie');          // code court
    $table->string('serie_wording');  // libellé arabe, dir="rtl"
    $table->timestamps();
});
```

Applicable uniquement aux inscriptions dont l'`ArabicLevel` a `requires_series = true` (contrainte applicative, pas une FK stricte niveau↔série puisque plusieurs niveaux peuvent partager le même ensemble de séries — même logique que le français).

### `ArabicSubject`

Catalogue global, un seul modèle pour tous les cycles (voir décision 9).

```php
Schema::create('arabic_subjects', function (Blueprint $table) {
    $table->id();
    $table->string('name');           // libellé arabe, dir="rtl"
    $table->string('abbreviation')->nullable();
    $table->timestamps();
});
```

### `ArabicSubjectCoefficient`

Propre à chaque établissement — miroir de `SubjectCoefficient`.

```php
Schema::create('arabic_subject_coefficients', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_serie_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('coefficient');
    $table->timestamps();

    $table->unique(['establishment_id', 'arabic_level_id', 'arabic_serie_id', 'arabic_subject_id'], 'arabic_subject_coefficients_unique');
});
```

`ArabicSubjectCoefficient` utilise le trait `TenantScoped` existant, comme tout autre modèle établissement-scopé de l'app (`SubjectCoefficient` y compris) — rien de particulier à ce sujet.

### `Enrollment` — deux colonnes ajoutées

```php
Schema::table('enrollments', function (Blueprint $table) {
    $table->foreignId('arabic_level_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
    $table->foreignId('arabic_serie_id')->nullable()->after('arabic_level_id')->constrained()->nullOnDelete();
});
```

Nullable : une inscription dans un établissement non-`is_arabe` n'aura jamais ces colonnes renseignées. Validation applicative (pas contrainte SQL) : `arabic_serie_id` ne peut être renseigné que si `arabic_level_id` pointe vers un `ArabicLevel.requires_series = true`.

## 2. Écrans d'administration

- **`Livewire\Arabic\Levels\Index`**, **`Livewire\Arabic\Series\Index`**, **`Livewire\Arabic\Subjects\Index`** : CRUD des 3 catalogues globaux, réservés à l'admin SaaS — même gabarit que les écrans `Academics\SchoolYears\Index`/`Subjects\Index` déjà réservés à ce rôle.
- **`Livewire\Arabic\SubjectCoefficients\Index`** : CRUD des coefficients par établissement, réservé à fondateur/directeur/gestionnaire (même rôle que `SubjectCoefficients` français). Visible uniquement si `app('currentEstablishmentId')` correspond à un établissement `is_arabe = true` — sinon 403, même pattern que les autres écrans à accès conditionnel.
- **Navigation** : nouveau groupe « Arabe » dans `layouts/app.blade.php`, affiché uniquement si l'établissement courant a `is_arabe = true`, contenant le lien vers l'écran de coefficients (les 3 catalogues globaux restent dans l'espace admin SaaS existant, pas dans cette nav établissement).
- **Champs texte arabes** : `wording`/`serie_wording`/`name` sur les formulaires de ces 4 écrans portent `dir="rtl"` sur le `<input>`/`<textarea>` concerné ; le reste de chaque écran (libellés, boutons, tableaux) reste en français/LTR.

## 3. Tests

- **Domaine** : CRUD des 4 modèles ; un `ArabicSerie` ne peut être rattaché à une inscription que si l'`ArabicLevel` associé a `requires_series = true` (test de validation applicative, pas de contrainte DB) ; unicité du coefficient (établissement × niveau × série × matière) ; deux établissements `is_arabe` distincts partagent le même catalogue `ArabicLevel`/`ArabicSerie`/`ArabicSubject` mais ont chacun leurs propres `ArabicSubjectCoefficient`.
- **Livewire** :
  - Les 3 écrans de catalogue (`Levels`, `Series`, `Subjects`) renvoient 403 pour tout rôle non-admin-SaaS.
  - `SubjectCoefficients` renvoie 403 pour un établissement `is_arabe = false`, même pour un fondateur/directeur ; accessible pour fondateur/directeur/gestionnaire d'un établissement `is_arabe = true` ; 403 pour caissier/éducateur/enseignant même dans un établissement arabe (même liste de rôles que le `SubjectCoefficients` français).
  - Le groupe de nav « Arabe » est absent pour tout établissement `is_arabe = false`, présent sinon.
- **Isolation** : la modification d'un coefficient dans l'établissement A n'affecte jamais l'établissement B, alors qu'une modification du catalogue `ArabicSubject` (admin SaaS) est immédiatement visible par tous les établissements arabes.

## Hors périmètre (pour l'instant)

- `ArabicTerm` (périodes datées) et le numéro de composition arabe — introduits au sous-chantier 2 (saisie de notes), quand un concret consommateur en a besoin.
- Affectation des enseignants aux matières arabes, saisie de notes, `ArabicGrade`/`ArabicGradeSheet` — sous-chantier 2.
- `ArabicReportCard`, génération PDF, rendu RTL en PDF, police arabe pour dompdf — sous-chantier 3.
- Présences spécifiques à la filière arabe — évoqué comme piste possible, non planifié à ce stade.
- Regroupement des matières arabes par domaine (équivalent de `Domain` côté français) — non demandé, à ajouter plus tard si le besoin se confirme.
