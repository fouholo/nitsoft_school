<?php

declare(strict_types=1);

use App\Domain\Notifications\Providers\LogSmsProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Provider SMS par défaut
    |--------------------------------------------------------------------------
    |
    | "log" écrit les envois dans les logs applicatifs (dev/local). Basculer
    | vers un provider réel (Twilio, Africa's Talking, Orange SMS API...)
    | une fois le pays cible confirmé avec le client.
    |
    */
    'default' => env('SMS_PROVIDER', 'log'),

    'providers' => [
        'log' => LogSmsProvider::class,
    ],
];
