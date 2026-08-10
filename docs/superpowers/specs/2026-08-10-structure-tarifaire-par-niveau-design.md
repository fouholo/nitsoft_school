# Structure tarifaire par niveau

## Contexte

Ce chantier ouvre la gestion des paiements, découpée en 4 sous-chantiers séquentiels : (1) structure tarifaire par niveau — objet de cette spec, (2) échéancier/génération des factures à partir de cette structure, (3) suivi des retards/avances de paiement, (4) réductions de scolarité. Les sous-chantiers 2 à 4 ne sont pas spécifiés ici et restent hors périmètre.

Le modèle actuel (`FeeSchedule`) définit un tarif unique par classe (`classroom_id`) et par échéance (`due_date`), saisi manuellement grille par grille. Il ne peut pas représenter le besoin réel de l'établissement :

- un tarif **par niveau** (`Level` — CP1, CE2... table de référence globale existante, cf. `docs/superpowers/specs` précédentes sur les niveaux/séries), pas par classe individuelle — deux classes du même niveau (CE2-A, CE2-B) partagent toujours le même tarif ;
- deux natures de frais distinctes : des **frais d'inscription** (montant unique, dus par tout élève inscrit dans l'année, y compris les redoublants) et une **scolarité échelonnée** sur des **tranches mensuelles communes à tout l'établissement** (octobre à avril, soit 7 tranches, mêmes dates pour tous les niveaux) ;
- un montant de scolarité qui **varie par tranche et par niveau** — une tranche peut être vide pour un niveau donné (non comptée dans les sommes dues, cf. sous-chantier 3).

Ce sous-chantier remplace entièrement `FeeSchedule` (modèle, migration, policy, écran `Grilles tarifaires`) par cette nouvelle structure. Il ne construit **pas** encore la génération des factures à partir de cette structure (sous-chantier 2) : `Invoice.fee_schedule_id` est simplement retiré sans remplacement pour l'instant.

## 1. Modèle de données

**`installments`** (tranches, communes à l'établissement pour une année scolaire donnée) :

```php
Schema::create('installments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->date('due_date');
    $table->unsignedTinyInteger('position');

    $table->char('uid', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['establishment_id', 'school_year_id', 'position']);
});
```

**`level_fees`** (frais d'inscription + regroupement par niveau/année) :

```php
Schema::create('level_fees', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
    $table->foreignId('level_id')->constrained();
    $table->decimal('registration_amount', 12, 2)->default(0);

    $table->char('uid', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['establishment_id', 'school_year_id', 'level_id']);
});
```

**`level_fee_installments`** (montant de scolarité dû pour ce niveau à cette tranche) :

```php
Schema::create('level_fee_installments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('level_fee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 12, 2)->nullable();
    $table->timestamps();

    $table->unique(['level_fee_id', 'installment_id']);
});
```

`amount` nul (ou ligne absente) signifie que la tranche n'est pas due pour ce niveau — non comptée dans les calculs de sommes dues (sous-chantiers 3+). Traitée comme une table pivot avec attribut, même statut que `guardian_student`/`teacher_classroom_subject` dans ce projet : pas de colonnes de sync propres (`uid`/`device_id`/`client_updated_at`), toujours réécrite en bloc avec son `level_fee` parent au moment de la sauvegarde.

**Modèles** : `App\Domain\Billing\Models\Installment` (`HasFactory`, `SoftDeletes`, `Syncable`, `TenantScoped`), `App\Domain\Billing\Models\LevelFee` (idem), `App\Domain\Billing\Models\LevelFeeInstallment` (`Model` simple, sans trait spécial — accédé uniquement via `LevelFee::installmentAmounts(): HasMany`).

`Level`/`Serie` (tables de référence globales existantes) ne sont pas modifiées — `level_fees.level_id` pointe simplement dessus.

## 2. Retrait de `FeeSchedule`

- Migration DML/DDL : `Schema::dropIfExists('fee_schedules')`, retrait de la colonne `fee_schedule_id` sur `invoices` (`generateInvoices()` et tout ce qui en dépendait dans `FeeSchedules\Index` disparaît avec).
- Suppression : `App\Domain\Billing\Models\FeeSchedule`, `App\Policies\FeeSchedulePolicy`, `App\Livewire\Billing\FeeSchedules\Index` + vue, `database\factories\FeeScheduleFactory`, route `billing.fee-schedules.index`, lien nav "Grilles tarifaires", tests associés (`Feature/Livewire/Billing/FeeSchedules/*`, références dans `BillingPolicyTest`/`FounderAccessTest`/etc.).
- `RolePermissions::MATRIX` : l'ability `fee_schedules.write` est renommée `tuition_fees.write` (même liste de rôles : `fondateur`, `directeur`, `gestionnaire`, `caissier`, `educateur`) — cohérence de nommage avec le nouveau domaine, aucun changement de portée.

## 3. Policies

**`InstallmentPolicy`** et **`LevelFeePolicy`** (nouvelles, même gabarit que l'ancienne `FeeSchedulePolicy`) :

- `viewAny`/`view` → `RolePermissions::can($user->currentRole(), 'finance.access')` (+ `belongsToSameEstablishment` pour `view`).
- `create`/`update`/`delete` → `RolePermissions::can($user->currentRole(), 'tuition_fees.write')` (+ `belongsToSameEstablishment` pour `update`/`delete`).

Pas de notion de "propres saisies" ici (portée `finance.scope_own_only` de l'éducateur) : comme l'ancien `FeeSchedule`, cette structure est un référentiel partagé de l'établissement, jamais un enregistrement individuel — elle reste toujours visible en entier à qui a `finance.access`.

## 4. Écran

Remplace "Grilles tarifaires" dans la nav (groupe "Facturation") par **"Tarifs"**, route `billing.tuition-fees.index` (`App\Livewire\Billing\TuitionFees\Index`).

Sélecteur d'année scolaire en haut de l'écran (défaut : année marquée `is_current`, même pattern que `FeeSchedules\Index::create()`). Deux blocs, sous cette année scolaire sélectionnée :

1. **Tranches** — liste CRUD simple (libellé, date d'échéance, ordre), même gabarit formulaire que l'écran Dépenses (`Billing\Expenses\Index`) : `showForm`/`editingId`, pas de champ superflu.
2. **Tarifs par niveau** — tableau, une ligne par `Level` existant (`Level::orderBy('level_wording')->get()`, même tri que le sélecteur Niveau de l'écran Classes), colonnes : frais d'inscription actuel, total scolarité configuré (somme des `level_fee_installments.amount` non nuls), bouton "Configurer". Le formulaire "Configurer" (par niveau) affiche : montant d'inscription (un champ) + un champ montant par tranche définie à l'étape 1 (vide = tranche non due pour ce niveau) ; sauvegarde en une fois (`updateOrCreate` du `LevelFee`, puis upsert des `LevelFeeInstallment` un par un : suppression des lignes redevenues vides, upsert des autres).

Si aucune tranche n'est encore définie pour l'année scolaire sélectionnée, le bloc 2 affiche un message invitant à créer des tranches d'abord (pas de blocage dur, juste une aide contextuelle — un niveau peut n'avoir que des frais d'inscription sans aucune tranche de scolarité si l'établissement le souhaite).

## Hors périmètre

- Génération de factures à partir de cette structure (sous-chantier 2) — `Invoice.fee_schedule_id` retiré sans remplacement, aucune nouvelle colonne de rattachement ajoutée à `Invoice` dans ce sous-chantier.
- Calcul des retards/avances de paiement (sous-chantier 3).
- Réductions de scolarité (sous-chantier 4).
- Tarifs différenciés par classe au sein d'un même niveau (tranché : toujours par niveau, jamais d'exception par classe).
- Tranches propres à un niveau (tranché : toujours communes à tout l'établissement, mêmes dates pour tous les niveaux — seul le montant varie).
