# Infrastructure multilingue (i18n) — sous-chantier 1/N

## Contexte

L'application (Laravel 12 + Livewire 3, `apps/api`) est entièrement en français en dur : aucun `__()`/`@lang()` n'est utilisé dans les 91 vues Blade ni dans les composants Livewire, il n'existe aucune colonne `locale` sur `User`/`Establishment`, et aucun middleware de locale. Seuls les fichiers Laravel par défaut existent (`lang/{fr,en}/{auth,pagination,passwords,validation}.php`).

Le projet a par ailleurs une « filière arabe » (`Establishment.is_arabe`, `ArabicLevel`/`ArabicSerie`/`ArabicSubject`, bulletins RTL en PDF via dompdf) qui est un domaine métier (contenu pédagogique arabe) totalement distinct de ce chantier : l'internationalisation de l'interface elle-même.

Objectif final (au-delà de ce sous-chantier) : rendre l'intégralité de l'interface disponible en français, anglais et arabe (avec miroir RTL complet pour l'arabe). Vu le volume (91 vues + composants Livewire), le chantier est découpé :

- **Sous-chantier 1 (ce document)** : infrastructure technique complète (résolution de locale, sélecteur, stockage des traductions, mécanique RTL) + conversion pilote d'un périmètre représentatif (connexion + tableau de bord) qui établit le patron réutilisable.
- **Sous-chantiers suivants** : traduction en masse du reste des écrans, module par module, en réutilisant le patron établi ici.

## A. Résolution de la locale

- Migration : colonne `locale` (string, nullable) sur `users`. `null` retombe sur `config('app.locale')` (= `fr`). Ajoutée à `User::$fillable`.
- Whitelist centralisée des locales autorisées : `['fr', 'en', 'ar']` (constante ou config dédiée, réutilisée partout où une locale est acceptée en entrée — middleware, route de changement, sélecteur).
- Middleware `SetLocale`, enregistré globalement dans `bootstrap/app.php` (s'applique à toutes les zones : app, portail parent, guest) :
  - Si l'utilisateur est authentifié : `auth()->user()->locale ?? config('app.locale')`.
  - Sinon : `session('locale', config('app.locale'))`.
  - Appelle `App::setLocale($locale)`.
- Route `GET /locale/{locale}` : valide `{locale}` contre la whitelist (404 sinon), stocke en session, redirige vers la page précédente (`back()`). Sert de mécanisme de changement de langue pour les visiteurs non connectés.

## B. Sélecteur de langue (UX)

- **Utilisateur connecté** (y compris un `Guardian` du portail parent, qui possède un `user_id` vers `User` — même colonne `locale`, aucune divergence à gérer) : petit menu langue dans l'en-tête de `layouts/app.blade.php` (à côté du lien « Mot de passe »), 3 options FR/EN/AR. La sélection déclenche une action qui met à jour `User.locale` en base puis force un **rechargement complet de la page** (navigation classique, pas `wire:navigate`) — nécessaire pour que `dir`/toutes les chaînes traduites se recalculent proprement dès le premier rendu.
- **Visiteur non connecté** (`layouts/guest.blade.php`) : 3 liens simples FR/EN/AR vers `GET /locale/{locale}` (section A). Un seul écran de connexion sert tous les profils, y compris les parents (`Livewire\Auth\Login`, redirection post-connexion vers `guardian-portal.dashboard` ou `dashboard` selon `$user->guardianProfile`) — pas d'écran de connexion distinct pour le portail parent.

## C. Stockage et convention de traduction

- `lang/en.json` et `lang/ar.json`, clé = phrase française exacte telle qu'écrite dans le code (`__('Enregistrer')`). **Pas de `lang/fr.json`** : en français, la clé introuvable retombe nativement sur elle-même, qui est déjà le texte correct.
- Convention pour toute conversion d'écran (ce sous-chantier et les suivants) : entourer chaque chaîne visible de `__('texte français exact')`, puis ajouter l'entrée correspondante dans `en.json` et `ar.json`. Les paramètres Laravel (`__('Bonjour :name', ['name' => $x])`) fonctionnent normalement.
- Fichiers-cadre Laravel : `lang/ar/{auth,pagination,passwords,validation}.php` créés (traductions arabes des messages de validation/auth par défaut du framework), complétant les versions fr/en déjà en place.
- Test léger : `en.json`/`ar.json` sont du JSON valide, sans clé vide ni doublon. Garde-fou minimal — pas une vérification de couverture d'extraction (impossible à garantir statiquement).

## D. Mécanique RTL

- `dir="rtl"` si `app()->getLocale() === 'ar'`, sinon `dir="ltr"`, calculé une fois et appliqué sur `<html>` dans les 3 layouts (`app`, `guest`, `guardian-portal`).
- Miroir complet via les variants natifs Tailwind `rtl:`/`ltr:` (aucun plugin — basés sur l'attribut `dir` d'un ancêtre) : menu latéral basculé à droite, paddings/marges directionnels (`ms-`/`me-` plutôt que `ml-`/`mr-` là où pertinent), icônes à sens visuel (chevrons, flèches) inversées.
- Le miroir RTL se fait **au fil de la conversion de chaque écran**, pas d'un seul coup a posteriori. Ce sous-chantier établit le patron sur `layouts/app.blade.php` (menu, en-tête) et `layouts/guest.blade.php` ; les sous-chantiers de traduction en masse suivants répètent ce réflexe écran par écran.
- Hors scope : composants tiers dont le RTL n'est pas garanti nativement (aucun cas dans le périmètre actuel) — à traiter au cas par cas si un futur composant JS l'exige.

## E. Périmètre de conversion pilote

Dans ce sous-chantier :

- Migration `add_locale_to_users_table` + `User::$fillable`.
- Middleware `SetLocale` + route `GET /locale/{locale}`.
- `layouts/guest.blade.php` : `dir` dynamique, sélecteur FR/EN/AR, conversion complète en `__()` — impacte par ricochet les vues qui l'utilisent (`Auth/Login`, `Auth/Register`, écrans de mot de passe).
- `layouts/app.blade.php` : `dir` dynamique, menu langue dans l'en-tête, conversion du **chrome partagé** (menu latéral, en-tête, libellés de navigation) en `__()` — présent sur chaque écran, donc naturellement dans le périmètre infrastructure plutôt que traduction en masse.
- `Livewire\Auth\Login` + vue, `Livewire\Dashboard` (route `/dashboard`) + vue : conversion complète en `__()`.
- `lang/ar/{auth,pagination,passwords,validation}.php`.

Hors périmètre explicite (sous-chantiers suivants) :

- Les ~89 autres vues Blade et leurs composants Livewire associés.
- `layouts/guardian-portal.blade.php` et les écrans du portail parent (même logique que le dashboard, mais reportés à une vague de traduction ultérieure).

## F. Plan de tests

- `SetLocale` : locale appliquée depuis `User.locale` (fallback `fr` si `null`) quand connecté, depuis la session quand invité ; `GET /locale/{invalide}` → 404.
- Sélecteur de langue connecté : `User.locale` mis à jour en base après sélection.
- `dir="rtl"`/`dir="ltr"` rendu correctement sur les 3 layouts selon la locale active (rendu direct de vue, comme `ClassroomStudentListPdfTest`).
- Login et Dashboard s'affichent sans erreur dans les 3 locales (fr/en/ar) — test Livewire par locale.
- `en.json`/`ar.json` : JSON valide, pas de clé vide/dupliquée.
- Larastan + suite Pest complète clean.
