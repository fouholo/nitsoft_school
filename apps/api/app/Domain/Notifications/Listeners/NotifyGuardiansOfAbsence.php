<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Attendance\Events\StudentMarkedAbsent;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Models\SmsTemplate;

/**
 * Crée une entrée SmsMessage (statut "queued") pour le seul contact
 * principal approuvé de l'élève, puis délègue l'envoi effectif à
 * SendSmsJob — voir docs/superpowers/specs/2026-08-06-parents-autoinscription-design.md.
 * Aucun envoi si aucun contact principal n'est désigné (pas de repli vers
 * l'ensemble des tuteurs). Écouteur synchrone (l'écriture en base est peu
 * coûteuse) ; seul l'envoi réseau est mis en file d'attente.
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

        $primaryGuardian = $student->guardians()
            ->wherePivot('status', GuardianLinkStatus::Approved)
            ->wherePivot('is_primary_contact', true)
            ->first();

        if (! $primaryGuardian || ! $primaryGuardian->phone) {
            return;
        }

        $template = SmsTemplate::where('code', 'attendance_absence')
            ->where('is_active', true)
            ->first();

        $body = $template instanceof SmsTemplate
            ? $template->body
            : "{{guardian_name}}, votre enfant {{student_name}} a été marqué(e) absent(e) aujourd'hui.";

        $rendered = str_replace(
            ['{{guardian_name}}', '{{student_name}}'],
            [trim("{$primaryGuardian->first_name} {$primaryGuardian->last_name}"), trim("{$student->first_name} {$student->last_name}")],
            $body,
        );

        $smsMessage = SmsMessage::create([
            'establishment_id' => $establishmentId,
            'guardian_id' => $primaryGuardian->id,
            'template_code' => 'attendance_absence',
            'phone' => $primaryGuardian->phone,
            'body_rendered' => $rendered,
            'status' => 'queued',
            'related_type' => $event->record::class,
            'related_id' => $event->record->id,
        ]);

        SendSmsJob::dispatch($smsMessage->id, $establishmentId);
    }
}
