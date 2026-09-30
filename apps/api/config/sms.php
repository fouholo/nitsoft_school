<?php

declare(strict_types=1);

use App\Domain\Notifications\Providers\LogSmsProvider;
use App\Domain\Notifications\Providers\OrangeSmsProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Provider SMS par défaut
    |--------------------------------------------------------------------------
    |
    | "log" écrit les envois dans les logs applicatifs (dev/local, tests).
    | "orange" envoie réellement via l'API SMS Orange Afrique et Moyen-Orient
    | — voir docs/superpowers/specs/2026-09-30-sms-orange-design.md.
    |
    */
    'default' => env('SMS_PROVIDER', 'log'),

    'providers' => [
        'log' => LogSmsProvider::class,
        'orange' => OrangeSmsProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode d'exécution des envois
    |--------------------------------------------------------------------------
    |
    | "after_response" : le SMS part juste après la réponse HTTP, dans le même
    | processus PHP — pour un hébergement sans tâche cron ni worker de file
    | d'attente. "queue" : mise en file classique (file "sms"), à n'utiliser
    | qu'avec un worker (queue:work) actif.
    |
    */
    'dispatch' => env('SMS_DISPATCH', 'after_response'),

    'orange' => [
        'base_url' => env('ORANGE_SMS_BASE_URL', 'https://api.orange.com'),
        'client_id' => env('ORANGE_SMS_CLIENT_ID'),
        'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),

        // Adresse d'expéditeur imposée par Orange pour le pays du contrat.
        'sender_address' => env('ORANGE_SMS_SENDER_ADDRESS', 'tel:+2250000'),

        // Nom d'expéditeur (11 caractères max) validé au préalable par Orange.
        // Vide : l'expéditeur par défaut d'Orange est utilisé.
        'sender_name' => env('ORANGE_SMS_SENDER_NAME'),

        'country' => env('ORANGE_SMS_COUNTRY', 'CIV'),
        'low_balance_threshold' => (int) env('ORANGE_SMS_LOW_BALANCE_THRESHOLD', 100),

        // Secondes.
        'timeout' => 15,

        // Pause après chaque envoi : Orange limite à 5 SMS par seconde.
        'pause_between_sends_ms' => 200,
    ],
];
