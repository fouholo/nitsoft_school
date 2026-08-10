# Uid local / uid serveur préfixés par type d'entité

**Date** : 2026-08-10
**Statut** : Approuvé

## Contexte

En prévision des clients Desktop/Mobile NativePHP offline-first (Phase 3/4, pas encore commencées), le système d'uid posé le 2026-08-06 (`docs/superpowers/specs/2026-08-06-uid-synchronisation-design.md`) doit évoluer. Ce système actuel a deux limites pour l'usage offline réel :
1. Un seul uid par enregistrement, affecté uniquement par le serveur (`UidAssigner`, compteur global `uid_counters`) — aucune notion d'identifiant généré côté client avant toute synchronisation.
2. Le compteur est global à toute la plateforme, sans distinction du type d'entité — un uid ne dit rien sur sa nature avant d'aller consulter la table d'origine.

Ce chantier remplace ce système par **deux identifiants distincts par enregistrement** : un `uid_local` généré immédiatement à la saisie (avant toute synchronisation, utilisable offline sans coordination), et un `uid_serveur` numérique préfixé par type d'entité (utilisable comme code-barres, ex. cartes d'élèves), affecté par le serveur.

## Décisions validées avec l'utilisateur

1. **Remplace complètement** l'ancien système (`uid`/`uid_counters`/`UidAssigner`) — pas de coexistence. Les 22 tables déjà `Syncable` migrent vers le nouveau schéma.
2. **`uid_local`** : `varchar(20)`, généré côté client à la création (aujourd'hui le serveur web joue ce rôle). Format "ULID compact" — décision explicite de ne pas dépendre d'un appareil déjà enregistré (pas besoin que `devices` soit peuplée).
3. **`uid_serveur`** : préfixe à 3 chiffres par type d'entité + séquence à 9 chiffres = 12 caractères, même principe code-barres que l'ancien `uid` mais avec le type d'entité identifiable dès les 3 premiers chiffres. Compteur par préfixe (pas global, pas par établissement).
4. **Nouvelle table `teachers`** — jusqu'ici un enseignant n'est qu'un `User` avec un rôle `enseignant` sur `establishment_user`, sans fiche dédiée. `teachers` est une fiche compagnon **du même principe que `Guardian`** : ne remplace aucune référence existante (`teacher_classroom_subject.user_id` continue de pointer `users.id`, RBAC inchangé), sert uniquement de support d'identité/uid pour l'usage offline et code-barres.
5. **Table des préfixes** validée par blocs de domaine (voir Design §3).
6. Projet greenfield : **pas de backfill**, `migrate:fresh --seed` suffit.

## Design

### 1. Génération de `uid_local`

`App\Domain\Sync\Services\LocalUidGenerator::generate(): string` — pure PHP, aucune dépendance base de données (doit pouvoir fonctionner hors-ligne une fois porté sur Desktop/Mobile) :
- 10 caractères d'horodatage en millisecondes depuis l'epoch, encodés en Base32 Crockford (alphabet `0123456789ABCDEFGHJKMNPQRSTVWXYZ`, sans `I`/`L`/`O`/`U` pour éviter les confusions de lecture — alphabet standard ULID).
- 10 caractères aléatoires (50 bits, `random_bytes`), même encodage.
- Concaténation = 20 caractères exactement. Probabilité de collision négligeable même sans coordination entre appareils (c'est le principe même d'un ULID).

### 2. Génération de `uid_serveur`

Nouvelle table technique `uid_server_counters` (`prefix` char(3) clé primaire, `next_value` unsigned int, défaut 0) — une ligne par préfixe, seedée dans la migration de création (23 lignes, voir tableau §3).

`App\Domain\Sync\Services\UidServerAssigner::assign(string $prefix): string` :
```php
$affected = DB::table('uid_server_counters')
    ->where('prefix', $prefix)
    ->update(['next_value' => DB::raw('LAST_INSERT_ID(next_value + 1)')]);

if ($affected === 0) {
    throw new \RuntimeException("Préfixe uid inconnu : {$prefix}");
}

$value = (int) DB::getPdo()->lastInsertId();

return $prefix.str_pad((string) $value, 9, '0', STR_PAD_LEFT);
```
`UPDATE ... SET col = LAST_INSERT_ID(col + 1)` est l'idiome MySQL natif pour un compteur atomique par clé sans verrou explicite (même rigueur que l'actuel `insertGetId()` sur `uid_counters` — à ne pas confondre avec `PaymentService::nextReceiptNumber()`, non atomique). `LAST_INSERT_ID()` sur `DB::getPdo()` reste valide juste après un `UPDATE` utilisant cette fonction dans la même connexion.

Remplace entièrement `uid_counters`/`UidAssigner` (supprimés).

### 3. Table des préfixes

| Préfixe | Table |
|---|---|
| 210 | foundations |
| 211 | establishments |
| 212 | classrooms |
| 213 | school_years |
| 214 | terms |
| 215 | subjects |
| 216 | subject_coefficients |
| 220 | users |
| 221 | students |
| 222 | teachers (nouvelle) |
| 223 | guardians |
| 230 | enrollments |
| 231 | grade_sheets |
| 232 | grades |
| 233 | attendance_sessions |
| 234 | attendance_records |
| 240 | invoices |
| 241 | payments |
| 242 | discounts |
| 243 | installments |
| 244 | level_fees |
| 245 | expenses |
| 250 | sms_templates |

### 4. Trait `Syncable`

```php
trait Syncable
{
    protected static function bootSyncable(): void
    {
        static::creating(function ($model): void {
            if (! $model->uid_local) {
                $model->uid_local = app(LocalUidGenerator::class)->generate();
            }
            if (! $model->uid_serveur) {
                $model->uid_serveur = app(UidServerAssigner::class)->assign(static::uidPrefix());
            }
        });
    }

    abstract protected static function uidPrefix(): string;
}
```
`abstract protected static function uidPrefix(): string` dans le trait force chacun des 23 modèles consommateurs à déclarer son préfixe (erreur PHP au chargement de la classe si oublié — contrainte plus sûre qu'une simple propriété statique documentée par convention).

### 5. Colonnes

Sur les 22 tables déjà `Syncable` : `uid` (char12, nullable, unique) est remplacé par `uid_local` (varchar20, **not null**, unique) et `uid_serveur` (char12, **nullable**, unique — nullable pour permettre plus tard une création offline en attente de première synchronisation). `device_id`/`client_updated_at` inchangés.

Nouvelle table `teachers` : `id`, `establishment_id` (FK, cascade, `TenantScoped` — contrairement à `Guardian`, un enseignant est rattaché à un seul établissement comme le reste du RBAC), `user_id` (FK, cascade, **not null, unique** — chaque création de staff dans `Staff\Index::create()` crée un `User` neuf, jamais de réutilisation d'un compte existant, donc relation 1:1 garantie), `name` (string, copié du formulaire existant, pas de split prénom/nom — aucun précédent de nom composé sur ce flux), `phone` (nullable, pas encore collecté par le formulaire — champ prêt pour un futur écran d'édition, hors périmètre ici), colonnes Syncable (`uid_local`, `uid_serveur`, `device_id`, `client_updated_at`).

### 6. Point d'intégration `teachers`

`Livewire\Staff\Index::create()` (`app/Livewire/Staff/Index.php:40-71`) : quand `staff_role === 'enseignant'`, créer la fiche `Teacher` dans la même transaction que le `User` + le pivot `establishment_user` (`DB::transaction()` — pas encore utilisé dans cette méthode aujourd'hui, à ajouter). Aucun nouvel écran de gestion `teachers`.

### 7. Sites d'appel impactés (recherche de `uid` dans le code applicatif)

Au-delà des 22 modèles + `Syncable`/`UidAssigner` déjà cités, deux écrans lisent `uid` pour une recherche utilisateur — les deux doivent être basculés sur `uid_serveur` (c'est la valeur imprimable/physique, pas `uid_local`) :
- `Livewire\Staff\Register.php` + `resources/views/livewire/staff/register.blade.php` : auto-inscription staff par uid d'établissement.
- `Livewire\GuardianPortal\LinkChild.php` + `resources/views/livewire/guardian-portal/link-child.blade.php` : recherche d'élève par uid (`Student::withoutTenant()->where('uid', ...)`).

### 8. Migrations

Une migration qui, pour les 22 tables existantes : `dropColumn('uid')`, ajoute `uid_local` (varchar20 not null unique) et `uid_serveur` (char12 nullable unique). Une migration séparée `create_teachers_table`. Une migration séparée `create_uid_server_counters_table` (avec seed des 23 lignes dans son `up()`, pas dans `DatabaseSeeder` — même raison que la table `roles` : `RefreshDatabase` n'exécute jamais `DatabaseSeeder`, et l'affectation de uid doit fonctionner dans tous les tests dès la création du premier enregistrement Syncable). Suppression de la migration logique `uid_counters` (nouvelle migration `drop_uid_counters_table`, pas d'édition des migrations déjà commitées). Toutes les factories des 22 modèles + la nouvelle `TeacherFactory` mises à jour (retirent `'uid' => ...` s'il y était forcé, sinon rien à faire — génération automatique par le trait).

## Erreurs / cas limites

- Préfixe inconnu passé à `UidServerAssigner::assign()` : `RuntimeException` (bug de programmation, pas un cas utilisateur — un modèle qui existe forcément dans la table des préfixes).
- Dépassement de 9 chiffres de séquence par préfixe (999 999 999 enregistrements du même type) : hors de portée réaliste, non traité — même position que l'ancien système sur 12 chiffres globaux.
- Collision `uid_local` : non traitée activement (probabilité négligeable par construction ULID), mais la contrainte `unique` sur la colonne fera échouer l'insertion avec une erreur explicite plutôt qu'une corruption silencieuse si jamais elle se produisait.

## Tests

- `LocalUidGenerator::generate()` : longueur exacte 20, alphabet Crockford uniquement, deux appels successifs différents.
- `UidServerAssigner::assign()` : formatte bien `{prefixe}{9 chiffres}`, incrémente à chaque appel pour un même préfixe, préfixes différents ont des séquences indépendantes, préfixe inconnu lève `RuntimeException`.
- Test feature : créer un enregistrement sur un modèle `Syncable` (ex. `Classroom`) déclenche l'affectation automatique de `uid_local` et `uid_serveur` non nuls, avec le bon préfixe.
- `Staff\Index::create()` avec `staff_role = 'enseignant'` crée bien une fiche `Teacher` liée au `User` créé ; avec `staff_role = 'caissier'`/`'educateur'`, aucune fiche `Teacher` n'est créée.
- `Staff\Register`/`GuardianPortal\LinkChild` : recherche par `uid_serveur` fonctionne (tests existants à adapter, pas de nouveau scénario).

## Hors périmètre (explicitement écarté)

- Tout moteur de sync réel Desktop/Mobile (Phase 3/4, pas commencées) — ce chantier pose uniquement les colonnes et les mécanismes d'affectation, réutilisables tel quel plus tard.
- Écran de gestion dédié pour `teachers` (édition, téléphone, etc.) — seule la fiche auto-créée à l'inscription est construite.
- Génération/affichage effectif de codes-barres (impression de cartes) — seul `uid_serveur` est posé.
