# Contacts famille de référence sur la fiche élève

## Contexte

Depuis l'auto-inscription des parents (`docs/superpowers/specs/2026-08-06-parents-autoinscription-design.md`), un parent peut demander à se lier à un élève via son uid, en indiquant son nom, son téléphone et son rôle (père/mère/tuteur). L'admin valide ou rejette cette demande depuis l'écran "Demandes de liaison", mais n'a aujourd'hui aucun moyen de vérifier que la personne qui demande à être liée correspond réellement à la famille de l'élève — il n'y a aucune donnée de référence à comparer.

## Objectif

Ajouter sur la fiche élève des champs de référence (nom + téléphone du père, de la mère, du tuteur), renseignés librement par l'école, et les faire apparaître à côté de chaque demande de liaison en attente pour permettre une vérification visuelle avant approbation.

Ces champs sont **purement informatifs** : ils ne sont pas liés au système `Guardian`, ne sont pas synchronisés automatiquement, et n'entraînent aucun rapprochement/validation automatique. C'est un aide-mémoire pour l'admin.

## 1. Migration

Nouvelle migration ajoutant 6 colonnes nullable à `students` :

```php
Schema::table('students', function (Blueprint $table): void {
    $table->string('father_name')->nullable()->after('gender');
    $table->string('father_phone')->nullable()->after('father_name');
    $table->string('mother_name')->nullable()->after('father_phone');
    $table->string('mother_phone')->nullable()->after('mother_name');
    $table->string('tutor_name')->nullable()->after('mother_phone');
    $table->string('tutor_phone')->nullable()->after('tutor_name');
});
```

Facultatifs par choix (confirmé) : une école n'a pas toujours ces informations au moment de l'inscription.

## 2. Modèle `Student`

Ajouter les 6 colonnes à `$fillable`. Aucun cast particulier (chaînes simples).

## 3. Formulaire élève (`Livewire\Students\Index`)

Ajouter 6 propriétés publiques (`father_name`, `father_phone`, `mother_name`, `mother_phone`, `tutor_name`, `tutor_phone`), toutes `string` avec valeur par défaut `''`. Règles de validation : `['nullable', 'string', 'max:255']` pour chacune. Normalisation `'' → null` avant `fill()`, même convention que `birth_date`/`gender` existants. Peuplées dans `edit()`, réinitialisées dans `resetForm()`.

Vue (`resources/views/livewire/students/index.blade.php`) : nouvelle section "Contacts familiaux (référence)" dans le modal de création/édition, sous les champs existants — 3 paires nom/téléphone (Père, Mère, Tuteur).

## 4. Fiche élève (`Livewire\Students\Show`)

Nouveau bloc en lecture seule, affiché à côté du tableau "Tuteurs" existant, listant les 3 paires nom/téléphone renseignées (ligne masquée si les deux champs d'une paire sont vides). Pas de changement au composant PHP — les données viennent directement de `$this->student`.

## 5. Écran "Demandes de liaison" (`Livewire\GuardianLinkRequests\Index`)

C'est l'usage principal de la fonctionnalité. Pour chaque demande en attente, la vue affiche déjà le nom/téléphone du parent demandeur et le rôle demandé (`relationship`). Ajouter à côté un encart "Référence école" tiré du champ de l'élève correspondant au rôle demandé :

- `relationship = pere` → `student->father_name` / `student->father_phone`
- `relationship = mere` → `student->mother_name` / `student->mother_phone`
- `relationship = tuteur` → `student->tutor_name` / `student->tutor_phone`

Résolution faite côté vue (accès direct aux propriétés du modèle déjà chargé via `with(['guardian', 'student'])`), pas de logique de comparaison automatique — juste un affichage côte à côte laissant le jugement à l'admin. Si le champ de référence est vide, afficher "Non renseigné".

## 6. Tests

- `Livewire\Students\IndexTest` (étendre le test existant de création/édition, ou en ajouter un) : vérifier que les 6 champs sont sauvegardés et normalisés (`'' → null`).
- `Livewire\GuardianLinkRequests\IndexTest` (étendre) : vérifier que la vue expose bien les bonnes valeurs de référence selon le rôle demandé.

## Hors périmètre

- Pas de rapprochement/validation automatique.
- Pas d'ajout à un éventuel import CSV (aucun import CSV élève n'existe actuellement dans le projet).
- Aucune modification du système `Guardian` / de la table `guardian_student`.
