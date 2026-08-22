# Messagerie interne — personnel ↔ personnel (sous-chantier 1/2)

*(Validé par l'utilisateur le 2026-08-22.)*

## Contexte

L'utilisateur veut un canal de communication interne par internet, distinct du module SMS existant (`App\Domain\Notifications\*`, payant, réservé aux communications officielles vers les tuteurs). Aucune messagerie interne n'existe aujourd'hui dans le projet (vérifié : aucun modèle/composant `Message`/`Conversation`/`Chat`).

Le chantier est décomposé en deux sous-chantiers indépendants :
- **Sous-chantier 1 (ce document)** : messagerie personnel ↔ personnel, au sein des établissements accessibles à l'utilisateur.
- **Sous-chantier 2 (ultérieur)** : extension vers le portail parent (personnel ↔ tuteurs), hors périmètre ici.

## Décisions de périmètre (validées avec l'utilisateur)

- **Format** : conversations directes (2 personnes) **et** groupes (3+ personnes ou nom donné).
- **Rafraîchissement** : polling Livewire (`wire:poll`), pas de websockets/Reverb pour ce sous-chantier.
- **Pièces jointes** : hors périmètre — texte seul.
- **Notification** : badge/compteur in-app uniquement — pas d'e-mail.
- **Droits d'envoi** : tout membre du personnel actif peut écrire à tout autre membre actif visible dans son périmètre (pas de restriction par rôle).
- **Périmètre multi-établissement** : une conversation n'est pas cloisonnée à un seul établissement. La liste de contacts proposée pour démarrer une conversation = tout le personnel actif des établissements retournés par `User::accessibleEstablishments()` (méthode déjà en place pour le switcher d'établissement — inclut les écoles directes ET celles du groupe si fondateur). Une fois la conversation créée, elle reste valable même si l'accès d'un participant change ensuite (comme le reste de l'appli, pas de re-vérification permanente).

## Modèle de données

Nouveau domaine `App\Domain\Messaging`, indépendant du domaine `Notifications` (SMS).

**Migration 1** — `conversations` :
```php
$table->id();
$table->string('type'); // 'direct' | 'group'
$table->string('name')->nullable(); // uniquement pour les groupes nommés
$table->foreignId('created_by')->constrained('users');
$table->timestamps();
```

**Migration 2** — `conversation_user` (pivot) :
```php
$table->id();
$table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->timestamp('last_read_at')->nullable();
$table->timestamps();

$table->unique(['conversation_id', 'user_id']);
```

**Migration 3** — `messages` :
```php
$table->id();
$table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
$table->foreignId('sender_id')->constrained('users');
$table->text('body');
$table->timestamps();

$table->index(['conversation_id', 'created_at']);
```

**Modèles** : `Conversation` (`hasMany` Message, `belongsToMany` User via `conversation_user` avec pivot `last_read_at`), `Message` (`belongsTo` Conversation, `belongsTo` User as `sender`).

`Conversation::displayName(User $viewer): string` — pour un groupe nommé, retourne `name` ; sinon (direct ou groupe sans nom), retourne la liste des noms des autres participants (hors `$viewer`), jointe par virgule.

`Conversation::unreadCountFor(User $user): int` (scope/méthode statique utilitaire) — nombre de messages dans la conversation postérieurs au `last_read_at` du participant (ou tous si `null`), excluant les messages envoyés par `$user` lui-même.

## Recherche/déduplication d'une conversation directe

Avant de créer une conversation `direct` entre deux personnes, chercher une conversation `direct` existante ayant exactement ces deux participants (aucune autre) :
```php
Conversation::query()
    ->where('type', 'direct')
    ->whereHas('participants', fn ($q) => $q->where('user_id', $userA->id))
    ->whereHas('participants', fn ($q) => $q->where('user_id', $userB->id))
    ->withCount('participants')
    ->having('participants_count', 2)
    ->first();
```
Si trouvée, on la réutilise (redirige vers elle) plutôt que d'en créer une nouvelle.

## Autorisation

`ConversationPolicy` :
- `view(User $user, Conversation $conversation)` : `$user` doit être participant (`$conversation->participants()->where('user_id', $user->id)->exists()`).

Pas de policy `create` au sens Laravel classique (pas de ressource cible avant création) : la validation se fait dans le composant Livewire, en vérifiant que chaque participant sélectionné appartient bien à l'ensemble « personnel actif des établissements accessibles à l'utilisateur courant » (même requête que celle qui alimente la liste de contacts — on ne fait pas confiance aux ID postés sans revalidation).

L'envoi d'un message vérifie que l'expéditeur est un participant actif de la conversation (`authorize('view', $conversation)` suffit, réutilisée).

## Interface

**Route** : `GET /messagerie` et `GET /messagerie/{conversation?}` → `App\Livewire\Messaging\Index` (layout `layouts.app`). Fichier `routes/messaging.php`, inclus dans `web.php` comme les autres modules.

**Composant `Messaging\Index`** — deux colonnes :
- **Gauche** : liste des conversations de l'utilisateur (triées par dernier message), nom via `displayName()`, aperçu du dernier message, badge de non-lus par conversation, barre de recherche de contact + bouton « Nouvelle conversation ».
- **Droite** : fil de la conversation sélectionnée (`wire:poll.5s` pour les nouveaux messages), zone de saisie + envoi (`wire:submit`).

« Nouvelle conversation » : sélection multiple de contacts (recherche dans le personnel actif accessible), champ nom optionnel (actif seulement si 2+ contacts sélectionnés). Validation : au moins 1 contact sélectionné. 1 contact + pas de nom → recherche/crée une conversation directe. 2+ contacts ou nom renseigné → crée un groupe.

À la sélection d'une conversation (`selectConversation`), mise à jour de `last_read_at` du participant courant à `now()`.

**Badge non-lus dans la sidebar** — nouveau composant `App\Livewire\Messaging\UnreadBadge`, embarqué dans `layouts/app.blade.php` à côté de l'entrée de nav « Messagerie », `wire:poll.20s`. Affiche le nombre de conversations ayant au moins un message non lu pour l'utilisateur courant (0 → badge masqué).

## Validation

```php
// Nouvelle conversation
'participantIds' => ['required', 'array', 'min:1'],
'participantIds.*' => ['integer'],
'groupName' => ['nullable', 'string', 'max:255'],

// Envoi de message
'body' => ['required', 'string', 'max:5000'],
```

## i18n

Ce sous-chantier suit la stratégie établie (mémoire `i18n_json_key_translation_pattern`) : `__('Phrase française exacte')`, clés ajoutées à `lang/en.json`/`lang/ar.json`, classes directionnelles RTL sur les nouvelles vues.

## Tests

`tests/Feature/Livewire/Messaging/IndexTest.php` :
- Deux membres actifs du même établissement peuvent démarrer une conversation directe et échanger des messages.
- Redémarrer une conversation directe avec la même personne réutilise la conversation existante (pas de doublon).
- Création d'un groupe à 3+ participants, nom personnalisé et nom auto-généré (liste des participants).
- Un tiers non-participant ne peut pas accéder à une conversation (`assertForbidden`).
- Un fondateur peut démarrer une conversation avec du personnel d'une autre école de son groupe (via `accessibleEstablishments()`), un enseignant sans accès à cette école ne le peut pas.
- `last_read_at` mis à jour à l'ouverture ; le badge de non-lus reflète les messages postérieurs.
- Validation : message vide rejeté, conversation sans participant rejetée.

`tests/Feature/Livewire/Messaging/UnreadBadgeTest.php` : compteur correct après réception d'un message, retombe à 0 après lecture.

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle Playwright : conversation directe entre deux comptes seedés, groupe à 3, badge de non-lus qui évolue.
5. Commit puis mise à jour de la mémoire projet.
