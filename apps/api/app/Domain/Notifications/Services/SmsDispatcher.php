<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;

/**
 * Point de départ unique de l'envoi d'un SMS enregistré en « queued ».
 *
 * Par défaut (sms.dispatch = after_response) le job s'exécute juste après
 * l'envoi de la réponse HTTP, dans le même processus : l'hébergement de
 * production n'a ni tâche cron ni worker de file d'attente. Avec « queue »,
 * le job part dans la file « sms » et exige un worker actif.
 */
class SmsDispatcher
{
    public function dispatch(SmsMessage $message): void
    {
        $job = new SendSmsJob($message->id, (int) $message->establishment_id);

        if (config('sms.dispatch') === 'queue') {
            dispatch($job);

            return;
        }

        dispatch($job)->afterResponse();
    }
}
