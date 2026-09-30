# Fournisseur SMS réel : API SMS Orange (Afrique et Moyen-Orient)

*(Validé par l'utilisateur le 2026-09-30.)*

## Contexte

- Les SMS aux tuteurs existent déjà de bout en bout, mais avec un fournisseur factice : `SmsProviderInterface` (`send(string $toPhoneE164, string $body): SmsSendResult`), implémenté par `LogSmsProvider` qui écrit dans les journaux. Le fournisseur est choisi par `config('sms.default')` (liaison dans `AppServiceProvider`).
- Deux points de départ créent un `SmsMessage` (statut `queued`) puis `SendSmsJob::dispatch()` : `NotifyGuardiansOfAbsence` (absence signalée) et `Livewire\Notifications\SmsMessages\Send` (envoi manuel). Le job, sur la file `sms`, appelle le fournisseur puis passe le message en `sent` ou `failed`.
- Les téléphones sont stockés tels que saisis, au format local ivoirien (`0101010102`).
- **Le serveur de production (hébergement mutualisé LWS) n'a ni tâche cron ni processus permanent** : aucun worker ne traite la file d'attente. Des jobs mis en file n'y seraient jamais exécutés.

Documentation Orange : https://developer.orange.com/apis/sms/getting-started

## Décisions validées

| Sujet | Décision |
|---|---|
| Compte Orange | Un seul compte pour toute la plateforme, identifiants dans le `.env` du serveur |
| Expéditeur | Expéditeur par défaut d'Orange tant qu'aucun nom d'expéditeur n'a été validé par Orange ; nom réglable ensuite par `.env` |
| Accusés de réception | Non gérés pour l'instant (statut final : « Envoyé ») |
| Solde et consommation | Écran « SMS » pour l'administrateur SaaS : solde Orange en direct, consommation par école, alerte de solde bas |
| Numéros | Conversion au format international au moment de l'envoi, sans modifier les données |
| Implémentation | Client maison sur le client HTTP de Laravel, sans dépendance tierce |
| Exécution | Envoi juste après la réponse HTTP (`afterResponse`), bouton de renvoi pour les SMS restés en attente ; retour possible à la file d'attente par configuration |

## API Orange utilisée

- **Jeton** : `POST https://api.orange.com/oauth/v3/token`, authentification HTTP Basic (`client_id:client_secret`), corps `grant_type=client_credentials` (form-urlencoded), en-tête `Accept: application/json`. Réponse : `access_token`, `expires_in` (3600 s).
- **Envoi** : `POST https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests`, en-têtes `Authorization: Bearer <jeton>` et `Content-Type: application/json`, corps :

```json
{
  "outboundSMSMessageRequest": {
    "address": "tel:+2250101010102",
    "senderAddress": "tel:+2250000",
    "senderName": "NOM",
    "outboundSMSTextMessage": { "message": "Texte" }
  }
}
```

  `senderName` n'est envoyé que s'il est configuré. Réponse `201 Created` avec `outboundSMSMessageRequest.resourceURL` dont le dernier segment est l'identifiant Orange du message.
- **Solde** : `GET https://api.orange.com/sms/admin/v1/contracts?country=CIV`, `Authorization: Bearer <jeton>`. Réponse : tableau de contrats (`country`, `offerName`, `availableUnits`, `status`, `expirationDate`).
- Débit maximal : 5 SMS par seconde.

## Configuration

`config/sms.php` et `.env` :

| Clé `.env` | Rôle | Défaut |
|---|---|---|
| `SMS_PROVIDER` | `log` (développement, tests) ou `orange` | `log` |
| `SMS_DISPATCH` | `after_response` ou `queue` | `after_response` |
| `ORANGE_SMS_CLIENT_ID` / `ORANGE_SMS_CLIENT_SECRET` | identifiants « MyApps » d'Orange | vide |
| `ORANGE_SMS_SENDER_ADDRESS` | adresse d'expéditeur du pays | `tel:+2250000` |
| `ORANGE_SMS_SENDER_NAME` | nom d'expéditeur validé par Orange (11 caractères max) ; vide = expéditeur par défaut | vide |
| `ORANGE_SMS_COUNTRY` | code pays des contrats consultés | `CIV` |
| `ORANGE_SMS_LOW_BALANCE_THRESHOLD` | seuil d'alerte de solde bas | `100` |

`ORANGE_SMS_BASE_URL` (`https://api.orange.com`) et un délai d'attente HTTP (15 s) complètent la configuration. `.env.example` reçoit ces clés, sans valeur secrète.

## Composants (`app/Domain/Notifications/`)

- **`Support/PhoneNumberNormalizer::toE164(string $raw): ?string`** : retire espaces, points, tirets et parenthèses ; `+225XXXXXXXXXX` et `00225XXXXXXXXXX` sont gardés (sous la forme `+225…`) ; un numéro local de 10 chiffres commençant par `0` devient `+225` suivi des 10 chiffres (le `0` est conservé, numérotation ivoirienne depuis 2021) ; tout autre numéro international `+XXX…` (8 à 15 chiffres) est gardé tel quel ; le reste renvoie `null`.
- **`Orange/OrangeTokenProvider`** : `token(): string` lit le cache (`orange_sms.token`, durée = `expires_in` − 300 s) ou en demande un nouveau ; `forget(): void` vide le cache. Lève `OrangeSmsException` (retryable) si Orange refuse ou ne répond pas.
- **`Providers/OrangeSmsProvider`** (implémente `SmsProviderInterface`) : construit et envoie la requête d'envoi, gère un seul renouvellement du jeton sur 401, traduit la réponse en `SmsSendResult`, marque une pause de 200 ms après chaque envoi.
- **`Orange/OrangeSmsAccountClient::contracts(): list<array>`** : lit les contrats (solde), mis en cache 5 minutes (`orange_sms.contracts`), avec `refresh()` pour forcer la relecture.
- **`Services/SmsDispatcher::dispatch(SmsMessage $message): void`** : selon `sms.dispatch`, `SendSmsJob::dispatchAfterResponse()` ou `SendSmsJob::dispatch()`. `NotifyGuardiansOfAbsence` et `SmsMessages\Send` l'utilisent à la place de `SendSmsJob::dispatch()`.
- **`ValueObjects/SmsSendResult`** reçoit un champ `retryable` (faux par défaut).

## Déroulé d'un envoi

1. Le `SmsMessage` est créé en `queued`, puis `SmsDispatcher::dispatch()`.
2. `SendSmsJob::handle()` (inchangé dans son principe, idempotent) : si le message n'est plus `queued`, il s'arrête ; sinon il normalise le numéro. Numéro invalide → `failed`, `error_message` « Numéro de téléphone invalide ». Sinon appel du fournisseur avec le numéro normalisé.
3. Résultat :
   - succès → `sent`, `sent_at`, `provider`, `provider_message_id` ;
   - échec non réessayable → `failed` + `error_message` ;
   - échec réessayable → reste `queued`, `error_message` mis à jour.

## Traitement des réponses d'Orange

| Situation | Résultat | Statut du SMS |
|---|---|---|
| 201 | succès, identifiant tiré de `resourceURL` | `sent` |
| 401 à l'envoi | oubli du jeton, nouveau jeton, un seul nouvel essai ; si nouvel échec : réessayable | `sent` ou `queued` |
| 400 | échec définitif (message d'Orange repris) | `failed` |
| Jeton impossible à obtenir (identifiants invalides, Orange injoignable) | réessayable | `queued` |
| 403 (forfait épuisé/expiré, politique) | réessayable | `queued` |
| 429, 5xx, délai dépassé, erreur réseau | réessayable | `queued` |

Les identifiants ne sont jamais écrits dans les journaux ni en base. Le jeton (valable une heure) vit uniquement dans le cache de l'application — qui est la table `cache` avec `CACHE_STORE=database`, le réglage actuel — jamais dans les tables métier ni les journaux. Les erreurs sont journalisées avec le statut HTTP et le message d'Orange uniquement.

## Écran « SMS » (administrateur SaaS)

Composant `App\Livewire\Notifications\SmsOverview`, route `GET /sms` (`sms.overview`), dans le groupe `auth`. Accès : `SmsMessagePolicy::overview` → réservé aux administrateurs SaaS (retour `false` ; accordé par le `Gate::before` global). Entrée « SMS » dans le menu SaaS.

- **Solde Orange** : une carte par contrat (pays, offre, SMS restants, statut, date d'expiration) ; bouton « Actualiser ». Bandeau d'alerte si le total disponible est sous le seuil, ou si un contrat est inactif ou expiré. Erreur d'Orange → message « Solde indisponible : … » sans bloquer l'écran. Fournisseur `log` → « Fournisseur de test : aucun solde ».
- **Consommation par école** sur un mois (sélecteur `AAAA-MM`, mois courant par défaut) : par établissement, nombre de SMS `sent`, `failed`, `queued` créés dans le mois, plus un total. Requête groupée unique sur `sms_messages` sans scope tenant (`withoutGlobalScope`), noms d'établissement chargés en une requête.
- **SMS en attente** : nombre total de `queued` toutes écoles confondues ; bouton « Renvoyer les SMS en attente » qui passe chacun par `SmsDispatcher` (même contexte d'établissement que le job).

Historique côté école (`sms-messages/index`) : le libellé `queued` devient « En attente » ; `error_message` est affiché en infobulle (`title`) sur les statuts « En attente » et « Échoué ».

## Traductions

Nouveaux textes via `__()` avec la phrase française comme clé, traduits dans `lang/en.json` et `lang/ar.json`.

## Tests (Pest, `Http::fake()`, aucun appel réel à Orange)

- `PhoneNumberNormalizer` : local, `+225`, `00225`, séparateurs, international étranger, invalides.
- `OrangeTokenProvider` : requête de jeton (Basic, grant_type), mise en cache, `forget()`, échec → exception réessayable.
- `OrangeSmsProvider` : requête exacte (URL encodée, en-têtes, corps), `senderName` absent/présent, identifiant extrait, chaque ligne du tableau des réponses, renouvellement unique du jeton sur 401.
- `SendSmsJob` : numéro invalide, succès, échec définitif, échec réessayable (reste `queued`).
- `SmsDispatcher` : `after_response` → `Bus::assertDispatchedAfterResponse`, `queue` → mise en file. Tests existants de l'envoi manuel et des absences adaptés.
- `SmsOverview` : accès réservé au SaaS, affichage du solde et de l'alerte, message d'erreur d'Orange, fournisseur `log`, consommation par école sur le mois, bouton de renvoi.

## Déploiement

- Aucune migration.
- `.env` du serveur : `SMS_PROVIDER=orange`, `SMS_DISPATCH=after_response`, `ORANGE_SMS_CLIENT_ID`, `ORANGE_SMS_CLIENT_SECRET` ; puis `php artisan config:clear`.
- Un forfait SMS Orange actif pour la Côte d'Ivoire est requis.

## Hors périmètre

- Accusés de réception (callback `deliveryInfoNotification`).
- Statistiques et historique d'achats Orange (`/statistics`, `/purchaseorders`).
- Comptes Orange par école.
- Validation des numéros à la saisie.
- Nouvelles tentatives automatiques différées (impossibles sans tâche planifiée ; reviennent avec `SMS_DISPATCH=queue` et un worker).
