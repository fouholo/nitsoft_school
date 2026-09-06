# Accès au manuel d'utilisation depuis l'application

*(Validé par l'utilisateur le 2026-09-06.)*

## Contexte

Un manuel d'utilisation PDF a été produit hors de l'application (chapitres couvrant le personnel d'établissement et le portail parents dans un seul document). Demande : que les utilisateurs y accèdent directement depuis l'application, sans passer par un envoi manuel. Décision prise avec l'utilisateur : le document unique est scindé en deux manuels autonomes, chacun avec son propre point d'accès.

## Périmètre

- **Manuel du personnel** : reprend le contenu existant (12 chapitres + annexe), moins la section « Le portail, côté parent » (retirée).
- **Manuel des parents** (nouveau, court, ton simple sans jargon technique) : connexion, inscription, lier un enfant par code, tableau de bord « Mes enfants », consultation Notes/Présences/Facturation.
- Les deux PDF sont des **livrables statiques** (produits par la présente session, comme le premier manuel) — aucun écran d'upload/édition n'est construit ; une mise à jour future du contenu se fait en remplaçant les fichiers lors d'un déploiement, pas depuis l'UI.
- Accès réservé aux utilisateurs authentifiés (personnel ou parent indifféremment) — document non sensible, pas de Policy dédiée ni de restriction de rôle.

## 1. Stockage

Les deux fichiers sont placés sur le disque `local` (`storage/app/private/`, déjà configuré, non exposé publiquement contrairement au disque `public`) :

```
storage/app/private/manuals/manuel-personnel.pdf
storage/app/private/manuals/manuel-parents.pdf
```

## 2. Routes et contrôleurs

Deux contrôleurs à action unique, suivant le patron déjà utilisé dans le projet pour servir un fichier stocké (`App\Http\Controllers\Backup\BackupExportController`) :

```php
// app/Http/Controllers/StaffManualPdfController.php
class StaffManualPdfController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path('manuals/manuel-personnel.pdf'));
    }
}

// app/Http/Controllers/GuardianManualPdfController.php
class GuardianManualPdfController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path('manuals/manuel-parents.pdf'));
    }
}
```

`response()->file()` (et non `download()`) affiche le PDF directement dans le navigateur, cohérent avec le comportement par défaut des autres PDF de l'application (bulletins, reçus). Pas de vérification d'autorisation dédiée au-delà du middleware `auth` déjà présent sur les deux groupes de routes.

Déclaration des routes :

```php
// routes/web.php, dans le groupe Route::middleware('auth')->group(...), à côté de account.password.edit
Route::get('/manuel-utilisation', StaffManualPdfController::class)->name('manual.staff');

// routes/guardian-portal.php, dans le groupe Route::prefix('portal')->name('guardian-portal.')->group(...)
Route::get('/manuel-utilisation', GuardianManualPdfController::class)->name('manual');
```

(nom complet une fois préfixé : `guardian-portal.manual`)

## 3. Navigation

- `resources/views/layouts/app.blade.php` : lien **« Manuel d'utilisation »** ajouté en bas de la sidebar, à côté des liens « Mot de passe » / « Déconnexion » déjà présents — `target="_blank"` pour ouvrir le PDF dans un nouvel onglet sans perdre l'écran en cours. Visible pour tout utilisateur authentifié, aucune condition `@can`.
- `resources/views/layouts/guardian-portal.blade.php` : lien **« Manuel d'utilisation »** ajouté dans la barre du portail, à côté de « Lier un enfant » / « Mot de passe », même comportement `target="_blank"`.

## Hors périmètre

- Pas d'écran d'administration pour uploader/remplacer les PDF depuis l'UI.
- Pas de version traduite (anglais/arabe) des manuels — uniquement le français, comme le reste de l'application à ce jour... à confirmer si besoin futur.
- Pas de suivi de consultation (qui a ouvert le manuel, quand).

## Tests

`tests/Feature/Http/ManualPdfTest.php` :
- Un utilisateur authentifié (personnel, n'importe quel rôle) reçoit le PDF personnel sur `GET /manuel-utilisation` (statut 200, `Content-Type: application/pdf`).
- Un utilisateur authentifié (parent) reçoit le PDF parents sur `GET /portal/manuel-utilisation`.
- Un visiteur non authentifié est redirigé vers la connexion sur les deux routes.

## Vérification

1. Suite Pest, PHPStan, Pint scopés aux fichiers touchés.
2. Vérification manuelle : connexion personnel → lien visible et PDF personnel s'ouvre ; connexion parent → lien visible dans le portail et PDF parents s'ouvre.
3. Aucune migration de données.
