# Saisie de la fondation et des établissements

## Contexte

`Livewire\Foundations\Index` permet déjà de créer/modifier/supprimer une **Foundation**. En revanche, il n'existe aujourd'hui **aucun écran pour créer un Establishment** — `Foundations\Show.php` ne permet que de rattacher/détacher un établissement *déjà existant en base* (créé jusqu'ici uniquement par seeder/tinker). Ce chantier ajoute la création d'établissement, à deux points d'entrée, en s'appuyant sur les modèles/policies existants.

## 1. Type d'établissement normé

Nouvel enum backé `App\Domain\Establishments\Enums\EstablishmentType` (même pattern que `Cycle`, `SaasAdminType`) :

```php
enum EstablishmentType: string
{
    case PrescolairePrimaire = 'prescolaire_primaire';
    case Secondaire = 'secondaire';

    public function label(): string
    {
        return match ($this) {
            self::PrescolairePrimaire => 'Préscolaire/Primaire',
            self::Secondaire => 'Secondaire',
        };
    }
}
```

`Establishment.type` reste une colonne `string` nullable en base (pas de migration de schéma nécessaire — le cast enum se fait au niveau du modèle, `$casts = ['type' => EstablishmentType::class]`). **Nouvelle migration de nettoyage des données de seed** : les 3 valeurs libres actuelles (`college`, `lycee`, `préscolaire-primaire`) sont réécrites vers les deux valeurs normées avant que le cast enum ne soit activé (sinon `Establishment::find()` sur les lignes de seed existantes lèverait une erreur de cast) — mapping : `college`/`lycee` → `secondaire`, `préscolaire-primaire` → `prescolaire_primaire`. Projet greenfield : en pratique, un `migrate:fresh --seed` suffit aussi, mais la migration de données reste écrite pour la forme (cohérent avec la convention déjà en place dans ce projet de ne jamais supposer un environnement toujours vierge).

## 2. `EstablishmentPolicy` étendue

Ajout de `viewAny`, `view`, `create`, `update`, `delete` (les méthodes `manageStaff`/`manageOrganization`/`reclaimGeneralAdmin` existantes ne changent pas) :

- `viewAny`/`view` : Super Admin SaaS (bypass `Gate::before` existant, `AppServiceProvider`) — un fondateur n'a pas besoin de `viewAny` global, il consulte ses établissements via `Foundations\Show`, déjà fonctionnel.
- `create` : Super Admin SaaS **ou** `$user->isFondateurOf($foundation)` quand une fondation est fournie en contexte (création depuis `Foundations\Show`). Un fondateur ne peut jamais créer un établissement indépendant (sans fondation) — seul le Super Admin SaaS le peut, depuis l'écran global.
- `update`/`delete` : Super Admin SaaS **ou** fondateur de la fondation à laquelle l'établissement appartient actuellement (`$establishment->foundation_id` non nul et `isFondateurOf`).

`User::isFondateurOf(Foundation $foundation): bool` (nouvelle méthode, même famille que `isGeneralAdminOf`/`isLocalAdminOf`) : vérifie une ligne `foundation_user` active avec `role = 'fondateur'` pour cette fondation et cet utilisateur.

## 3. Écran global "Établissements" (Super Admin SaaS)

`Livewire\Establishments\Index` (route `establishments.index`, nav "Établissements" visible seulement si `auth()->user()->isSaasAdmin()` — même pattern que le lien "Groupes scolaires" déjà en place) :

- Liste **tous** les établissements (toutes fondations confondues + indépendants), colonne "Fondation" affichant le nom ou "—" si indépendant.
- Formulaire création/édition : nom (slug auto-généré unique, même génération que `Foundations\Index`), fondation (`<select>` optionnel, "Aucune (indépendant)" en premier choix), type (`<select>` sur `EstablishmentType::cases()`), adresse, téléphone, fuseau horaire (texte libre, défaut `UTC` comme aujourd'hui). Seuls nom et type sont requis.
- `authorize('viewAny'|'create'|'update'|'delete', Establishment::class)` via la Policy étendue ci-dessus.

## 4. Création depuis la fiche fondation (fondateur)

`Livewire\Foundations\Show.php` gagne un bouton "Créer un établissement" à côté de "Rattacher un établissement existant" (`linkEstablishment` existant, inchangé) :

- Formulaire identique à l'écran global, **sans** le sélecteur de fondation (implicite : la fondation courante de la fiche).
- `authorize('create', Establishment::class)` — pour un fondateur, passe par la branche `isFondateurOf($this->foundation)` de la Policy ; pour un Super Admin SaaS consultant la même fiche, passe par le bypass SaaS existant. Même méthode `create()` sous-jacente que l'écran global (`Establishment::create([...'foundation_id' => $this->foundation->id])`).

## Hors périmètre

- Pas de provisionnement automatique d'un fondateur/staff au moment de la création d'un établissement — les flux existants ("Ajouter un fondateur" sur `Foundations\Show`, auto-inscription staff `/staff/register`) restent les seuls chemins pour peupler un établissement fraîchement créé.
- Pas de changement au rattachement/détachement d'un établissement déjà existant (`linkEstablishment`/`unlinkEstablishment`) — ces méthodes restent telles quelles.
- Pas de suppression en cascade réfléchie ici au-delà du comportement `SoftDeletes` déjà en place sur `Establishment`.
