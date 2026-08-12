# Liste imprimable des élèves d'une classe (PDF)

## Contexte

Demande explicite de l'utilisateur : des listes de données imprimables (PDF), avec plusieurs types cités (élèves par niveau/classe, enseignants par établissement, cours, utilisateurs, écoles, groupes scolaires). Périmètre trop large pour un seul chantier — décomposé en sous-projets successifs. Ce chantier traite le premier type, qui sert de gabarit réutilisable pour les suivants : la liste des élèves d'une classe.

Décisions actées avec l'utilisateur en clarification :
1. **Regroupement** : par classe précise (pas par niveau agrégeant plusieurs classes) — malgré l'intitulé initial "par niveau", chaque classe a déjà un niveau (`Classroom::level()`), donc choisir une classe suffit.
2. **Colonnes** : N°, matricule (`student_number`), nom, prénom, sexe, date de naissance, lieu de naissance.
3. **Point d'accès** : nouvel écran dédié "Listes/Rapports" (pas un bouton sur l'écran Classes existant) — pensé pour accueillir les futurs types de listes au même endroit.
4. **RBAC** : tout le personnel de l'établissement (admin, enseignant, comptable, éducateur), pas seulement l'admin.
5. **Mécanisme PDF** : reprend à l'identique la convention déjà établie du projet — rendu à la volée, jamais pré-généré ni stocké (`ReportCardPdfController`/`PaymentReceiptPdfController`).

## 1. Écran `Livewire\Reports\Index`

Formulaire de sélection (pas de CRUD) :
- Select "Année scolaire" (`SchoolYear` de l'établissement courant, pré-sélectionne celle où `is_current = true`).
- Select "Classe" (`Classroom` de l'année scolaire sélectionnée, `wire:model.live` sur l'année pour filtrer la liste des classes).
- Lien/bouton "Télécharger le PDF" vers la route du contrôleur PDF (`target="_blank"`, pas d'action Livewire — c'est un téléchargement de fichier, pattern déjà utilisé pour les bulletins/reçus).

`mount()` : `$this->authorize('viewAny', Classroom::class)` — réutilise `ClassroomPolicy::viewAny()` (déjà "tout membre de l'établissement courant", via `ChecksEstablishmentMembership::isMemberOfCurrentEstablishment()`), pas de nouvelle Policy.

## 2. `ClassroomStudentListPdfController`

`app/Http/Controllers/Academics/ClassroomStudentListPdfController.php`, même gabarit exact que `ReportCardPdfController` :
- `Gate::authorize('view', $classroom)` (même `ClassroomPolicy`).
- Charge les élèves de la classe : `$classroom->enrollments()->where('status', 'active')->with('student')->get()->pluck('student')`, triés par `last_name`/`first_name`.
- `Pdf::loadView('pdf.classroom-student-list', [...])->setPaper('a4')`.
- `?download=1` télécharge, sinon affichage inline (`stream()`).

## 3. Vue `resources/views/pdf/classroom-student-list.blade.php`

En-tête : nom de l'établissement (+ logo si présent, même bloc que `report-card.blade.php`/`receipt.blade.php`), nom de la classe, année scolaire. Tableau : N° (rang 1-based calculé dans la boucle), Matricule (`student_number`), Nom, Prénom, Sexe (`gender`), Date de naissance (`birth_date`, formatée), Lieu de naissance (`birth_place`).

## 4. Route et navigation

`routes/reports.php` (nouveau) :
```php
Route::prefix('rapports')->name('reports.')->group(function (): void {
    Route::get('/', Index::class)->name('index');
    Route::get('/classes/{classroom}/eleves', ClassroomStudentListPdfController::class)->name('classroom-students-pdf');
});
```
`routes/web.php` : `require __DIR__.'/reports.php';` dans le bloc `auth`, avant les `require` SaaS-only (à côté de `academics.php`/`enrollment.php` — c'est une fonctionnalité d'établissement, pas SaaS).

`resources/views/layouts/app.blade.php` : nouveau lien "Listes/Rapports" ajouté dans le `$navItems` général (pas le bloc `isSaasAdmin()`), avec `'ability' => 'viewAny', 'model' => \App\Domain\Academics\Models\Classroom::class` — même mécanisme de filtrage que les autres liens généraux du menu.

## 5. Tests

- `tests/Feature/Livewire/Reports/IndexTest.php` : le formulaire liste bien les classes de l'établissement courant filtrées par année scolaire ; accès refusé pour un utilisateur d'un autre établissement (test cross-tenant, gabarit déjà utilisé ailleurs dans le projet).
- `tests/Feature/Http/ClassroomStudentListPdfTest.php` : gabarit `ReportCardPdfTest` — rend `view('pdf.classroom-student-list', [...])->render()` directement (pas besoin de passer par dompdf/le contrôleur pour vérifier le contenu), vérifie la présence des colonnes attendues et des élèves triés ; test HTTP sur le contrôleur vérifiant le refus (403) pour un utilisateur d'un autre établissement.

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : accès à `/rapports`, sélection année scolaire + classe, téléchargement du PDF, contenu correct (colonnes, élèves de la bonne classe) ; lien nav visible pour un enseignant/comptable, accès refusé pour un utilisateur d'un autre établissement.
5. Commit puis mise à jour de la mémoire projet.
