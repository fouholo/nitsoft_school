<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Attendance\Events\StudentMarkedAbsent;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsTemplate;

/**
 * Crée une entrée SmsMessage (statut "queued") par tuteur joignable, puis
 * délègue l'envoi effectif à SendSmsJob — voir plan d'architecture, section 6.
 * Écouteur synchrone (l'écriture en base est peu coûteuse) ; seul l'envoi
 * réseau est mis en file d'attente.
 */
class NotifyGuardiansOfAbsence
{
    public function handle(StudentMarkedAbsent $event): void
    {
        if ($event->record->status !== 'absent') {
            return;
        }

        $student = $event->record->student;
        $establishmentId = $event->record->establishment_id;

        $template = SmsTemplate::where('code', 'attendance_absence')
            ->where('is_active', true)
            ->first();

        $body = $template instanceof SmsTemplate
            ? $template->body
            : "{{guardian_name}}, votre enfant {{student_name}} a été marqué(e) absent(e) aujourd'hui.";

        foreach ($student->guardians as $guardian) {
            if (! $guardian->phone) {
                continue;
            }

            $rendered = str_replace(
                ['{{guardian_name}}', '{{student_name}}'],
                [trim("{$guardian->first_name} {$guardian->last_name}"), trim("{$student->first_name} {$student->last_name}")],
                $body,
            );

            $smsMessage = SmsMessage::create([
                'establishment_id' => $establishmentId,
                'guardian_id' => $guardian->id,
                'template_code' => 'attendance_absence',
                'phone' => $guardian->phone,
                'body_rendered' => $rendered,
                'status' => 'queued',
                'related_type' => $event->record::class,
                'related_id' => $event->record->id,
            ]);

            SendSmsJob::dispatch($smsMessage->id, $establishmentId);
        }
    }
}
