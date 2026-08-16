# Génération dynamique de l'appréciation (primaire + secondaire)

*(Design validé par l'utilisateur le 2026-08-16.)*

## Contexte

Aujourd'hui, l'appréciation d'un bulletin (`ReportCard.appreciation`) n'existe que pour le primaire : un champ texte libre saisi à la main sur l'écran de notation (`EnterStudent`), enregistré dès la sauvegarde des notes (avant même la génération officielle du bulletin). Le secondaire n'a aucune appréciation — ni écran de saisie, ni logique de calcul.

L'utilisateur veut que l'appréciation soit **calculée automatiquement à partir de la moyenne**, via une **table de barème générale, commune au primaire et au secondaire**, plutôt que saisie à la main. Décisions prises pendant le brainstorming :

- La table est un **barème global au SaaS**, non personnalisable par établissement — seul un SaaS admin peut l'éditer (même pattern que `PrimarySubject` : policy qui refuse tout le monde, bypass `Gate::before` pour les SaaS admins).
- L'appréciation générée est **calculée, non modifiable** à la main — le champ de saisie libre disparaît de `EnterStudent`.
- Pour le primaire, l'appréciation reste **calculée et enregistrée dès la saisie des notes** (comme aujourd'hui), à partir de la moyenne en aperçu (non officielle). Pour le secondaire, elle est calculée à la génération officielle du bulletin (`ReportCardService::generate()`), qui est le seul point d'entrée existant pour ce cycle.
- Les 4 distinctions (tableau d'honneur, tableau d'excellence, félicitations, encouragements) portées par la table **ne sont pas dupliquées sur `ReportCard`** — elles restent consultables dans la table de barème si besoin plus tard, mais rien n'est enregistré ni affiché pour l'instant au-delà du libellé d'appréciation.

## Partie 1 — Modèle `AppreciationScale`

Nouvelle table de référence globale `appreciation_scales`, migration `create_appreciation_scales_table` :

```php
Schema::create('appreciation_scales', function (Blueprint $table): void {
    $table->id();
    $table->unsignedTinyInteger('percentage')->unique();
    $table->string('appreciation');
    $table->boolean('tableau_honneur')->default(false);
    $table->boolean('tableau_excellence')->default(false);
    $table->boolean('felicitation')->default(false);
    $table->boolean('encouragement')->default(false);
    $table->string('uid_local')->nullable();
    $table->string('uid_serveur')->nullable()->unique();
    $table->string('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

(Colonnes `uid_local`/`uid_serveur`/`device_id`/`client_updated_at` + `softDeletes()` : mêmes conventions que `PrimarySubject`, pour la synchro offline-first.)

`App\Domain\Grading\Models\AppreciationScale` (`uidPrefix()` = `'235'`, libre) :

```php
class AppreciationScale extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Syncable;

    protected $fillable = [
        'percentage', 'appreciation', 'tableau_honneur', 'tableau_excellence',
        'felicitation', 'encouragement', 'uid_local', 'uid_serveur', 'device_id', 'client_updated_at',
    ];

    protected $casts = [
        'tableau_honneur' => 'boolean',
        'tableau_excellence' => 'boolean',
        'felicitation' => 'boolean',
        'encouragement' => 'boolean',
        'client_updated_at' => 'datetime',
    ];

    protected static function uidPrefix(): string
    {
        return '235';
    }

    /**
     * $average est une moyenne sur 20 (convention déjà utilisée partout
     * dans ReportCardService/EnterStudent). Convertie en pourcentage pour
     * trouver la tranche la plus haute atteinte.
     */
    public static function forAverage(float $average): ?self
    {
        $percentage = ($average / 20) * 100;

        return static::query()
            ->where('percentage', '<=', $percentage)
            ->orderByDesc('percentage')
            ->first();
    }
}
```

Seeder (`DatabaseSeeder.php`, ou seeder dédié `AppreciationScaleSeeder` appelé depuis lui) :

| percentage | appreciation | tableau_honneur | tableau_excellence | felicitation | encouragement |
|---|---|---|---|---|---|
| 90 | Excellent | true | true | true | false |
| 80 | Très bien | true | false | true | false |
| 70 | Bien | true | false | false | false |
| 60 | Assez bien | false | false | false | true |
| 50 | Passable | false | false | false | false |
| 30 | Médiocre | false | false | false | false |
| 0 | Insuffisant | false | false | false | false |

La ligne `0` garantit qu'une moyenne trouve toujours une correspondance.

## Partie 2 — Écran d'administration (SaaS admin)

`App\Policies\AppreciationScalePolicy` — copie conforme de `PrimarySubjectPolicy` (tout refusé explicitement, le bypass `Gate::before` dans `AppServiceProvider` laisse passer tout SaaS admin automatiquement).

`App\Livewire\Academics\AppreciationScales\Index` — CRUD calqué sur `Academics\PrimarySubjects\Index` : liste triée par `percentage` décroissant, formulaire (`percentage`, `appreciation`, 4 booléens), `create()`/`edit()`/`save()`/`delete()` avec `$this->authorize(...)` à chaque étape. Validation : `percentage` entier 0-100, unique (sauf la ligne en cours d'édition) ; `appreciation` requis, max 100.

Route `routes/academics.php` : `Route::get('/appreciation-scales', AppreciationScalesIndex::class)->name('appreciation-scales.index');`, dans le même groupe que `primary-subjects.index`.

Nav (`resources/views/layouts/app.blade.php`, bloc `isSaasAdmin()`) : nouvel item "Barème d'appréciations" juste après "Matières du primaire".

## Partie 3 — Écran de saisie primaire (`EnterStudent`)

Suppression de la propriété publique `appreciation` et de sa validation. Suppression du préchargement dans `mount()` (le bloc qui lit `ReportCard::query()->...->first()` pour initialiser `$this->appreciation` disparaît — plus rien à précharger, c'est calculé à la volée).

`preview()` : ajout d'une clé `'appreciation'`, calculée à partir de `$average` déjà présent dans la méthode :

```php
'appreciation' => $average !== null ? AppreciationScale::forAverage($average)?->appreciation : null,
```

`save()` : calcule l'appréciation à partir de la même moyenne (non officielle) que l'aperçu, en réutilisant `$this->preview()` :

```php
$preview = $this->preview();

ReportCard::updateOrCreate(
    [...],
    [
        'establishment_id' => $this->gradeSheet->establishment_id,
        'classroom_id' => $this->classroom->id,
        'appreciation' => $preview['appreciation'],
    ]
);
```

Vue : le `<textarea wire:model="appreciation">` du cadre Résultats est remplacé par une case en lecture seule (même style que `Total`/`Moyenne`/`Résultat`) affichant `$preview['appreciation'] ?? '—'`.

## Partie 4 — Génération officielle (`ReportCardService::generate()`)

Une fois `$average` calculée pour un élève (boucle `foreach ($averages as $studentId => $average)`), l'appréciation est calculée et ajoutée au tableau passé à `updateOrCreate` — pour le primaire **et** le secondaire, sans branche par cycle (la logique est déjà cycle-agnostique, basée uniquement sur la moyenne sur 20) :

```php
$reportCards->push(ReportCard::updateOrCreate(
    ['student_id' => $studentId, ...$reportCardKey],
    [
        'establishment_id' => $classroom->establishment_id,
        'classroom_id' => $classroom->id,
        'average' => $average,
        'rank' => $rank,
        'appreciation' => AppreciationScale::forAverage($average)?->appreciation,
        'generated_at' => now(),
    ]
));
```

Ce changement remplace l'ancien comportement qui omettait volontairement `appreciation` de cet appel (pour ne pas écraser une saisie manuelle) : cette protection n'a plus lieu d'être puisque l'appréciation est désormais une fonction pure de la moyenne — la recalculer à la génération officielle donne un résultat cohérent avec celui déjà affiché/enregistré côté primaire, et peuple pour la première fois l'appréciation du secondaire.

Le PDF (`resources/views/pdf/report-card.blade.php`) affiche déjà `$reportCard->appreciation` conditionnellement — aucun changement nécessaire.

## Tests à écrire/adapter

- `Domain/AppreciationScaleTest.php` (nouveau) : `forAverage()` retourne la tranche correcte pour plusieurs moyennes (bornes incluses/exclues), retourne la ligne `0` par défaut, retourne `null` si la table est vide.
- `Livewire/Academics/AppreciationScalesTest.php` (nouveau) : CRUD réservé aux SaaS admins, refusé à tout le monde d'autre (établissement admin, directeur, etc.), validation (percentage unique, bornes 0-100).
- `GradeSheets/PrimaireEnterStudentTest.php` : suppression des scénarios de saisie manuelle d'appréciation (`->set('appreciation', ...)`, préchargement d'une appréciation existante) ; nouveaux scénarios vérifiant que l'appréciation enregistrée/affichée en aperçu correspond au barème pour une moyenne donnée.
- `Domain/ReportCardServiceTest.php` : `generate()` peuple `appreciation` pour le primaire et pour le secondaire, à partir du barème.
- `ReportCards/PrimaireTest.php` : la génération officielle recalcule l'appréciation de façon cohérente avec celle déjà affichée à la saisie (même moyenne ⇒ même libellé).

## Vérification

1. `php artisan migrate` + seeder du barème sur la base WAMP.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur les données WAMP existantes : noter un élève primaire avec une moyenne connue, vérifier que l'appréciation affichée/enregistrée correspond au barème ; générer un bulletin secondaire et vérifier qu'il a désormais une appréciation ; éditer le barème en tant que SaaS admin et vérifier qu'un utilisateur non-SaaS-admin n'y a pas accès.
