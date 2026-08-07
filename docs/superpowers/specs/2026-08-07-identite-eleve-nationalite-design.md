# Informations d'identité complémentaires sur la fiche élève

## Contexte

La fiche élève ne couvre aujourd'hui que l'identité minimale (nom, date de naissance, genre, matricule) plus, depuis la dernière itération, des contacts familiaux de référence. Il manque des informations d'état civil importantes pour un établissement scolaire ivoirien : lieu de naissance, nationalité, informations d'acte de naissance (numéro, date, lieu), et résidence.

## 1. Table `nationalites`

Table de référence **globale**, partagée par toute la plateforme (pas de `establishment_id` — une nationalité n'a pas de sens "par établissement").

```php
Schema::create('nationalites', function (Blueprint $table): void {
    $table->string('code', 5)->primary();
    $table->string('libelle', 20);
    $table->timestamps();
});
```

Modèle `App\Domain\Enrollment\Models\Nationalite` :
```php
class Nationalite extends Model
{
    protected $primaryKey = 'code';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['code', 'libelle'];
}
```

**Gestion** : aucun écran d'administration dans cette itération. La table est peuplée par une poignée de nationalités de test via le seeder (Ivoirienne, Française, Malienne, Burkinabè, Ghanéenne). Une future gestion par les admins de la plateforme SaaS (super_admin uniquement — pas les directeurs d'établissement, puisque c'est une liste partagée par tous) est envisageable plus tard mais reste hors périmètre ici.

## 2. Champs ajoutés sur `students`

Tous facultatifs (nullable), cohérent avec les contacts familiaux ajoutés précédemment — une école n'a pas toujours ces informations au moment de l'inscription.

```php
Schema::table('students', function (Blueprint $table): void {
    $table->string('birth_place')->nullable()->after('gender');
    $table->string('nationalite_code', 5)->nullable()->after('birth_place');
    $table->string('birth_certificate_number')->nullable()->after('nationalite_code');
    $table->date('birth_certificate_date')->nullable()->after('birth_certificate_number');
    $table->string('birth_certificate_place')->nullable()->after('birth_certificate_date');
    $table->string('residence')->nullable()->after('birth_certificate_place');

    $table->foreign('nationalite_code')->references('code')->on('nationalites')->nullOnDelete();
});
```

`Student` gagne une relation :
```php
public function nationalite(): BelongsTo
{
    return $this->belongsTo(Nationalite::class, 'nationalite_code', 'code');
}
```

## 3. Formulaire élève (`Livewire\Students\Index`)

Nouvelle section "Identité" dans le modal de création/édition existant, sous les champs actuels. 6 nouvelles propriétés publiques, validation `['nullable', 'string'/'date', 'max:255']` selon le champ, normalisation `'' → null` (même convention que les champs optionnels existants). Le champ nationalité est un `<select>` alimenté par `Nationalite::orderBy('libelle')->get()`, passé au `render()`.

## 4. Fiche élève (`Livewire\Students\Show`)

Nouveau bloc en lecture seule "Identité", affiché **avant** la section "Inscriptions" (contrairement au bloc "Contacts familiaux" qui reste informatif/secondaire, celui-ci fait partie de l'identité principale de l'élève). Affiche lieu de naissance, nationalité (libellé, pas le code), informations d'acte de naissance, résidence — chaque ligne masquée si vide. Aucun changement au composant PHP : les données viennent de `$this->student` avec la relation `nationalite` chargée en eager loading dans `render()`.

## 5. Tests

- Extension de `Livewire\Students\IndexTest` : un élève créé avec ces champs (dont une nationalité valide) les enregistre correctement ; normalisation `'' → null` vérifiée pour la date d'acte de naissance (même risque que `birth_date` déjà connu dans ce composant).
- Nouveau test rapide vérifiant que la fiche élève affiche le libellé de la nationalité (pas le code brut).

## Hors périmètre

- Pas d'écran de gestion des nationalités dans cette itération.
- Pas de validation de format sur le numéro d'acte de naissance (texte libre).
- Pas de lien entre `residence` et un système de géolocalisation structuré — simple champ texte.
