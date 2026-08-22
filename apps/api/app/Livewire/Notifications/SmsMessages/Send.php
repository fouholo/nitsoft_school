<?php

declare(strict_types=1);

namespace App\Livewire\Notifications\SmsMessages;

use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Notifications\Jobs\SendSmsJob;
use App\Domain\Notifications\Models\SmsMessage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Send extends Component
{
    public ?int $student_id = null;

    public string $body = '';

    public ?string $confirmation = null;

    public function mount(): void
    {
        $this->authorize('create', SmsMessage::class);
    }

    #[Computed]
    public function guardians()
    {
        if ($this->student_id === null) {
            return collect();
        }

        $student = Student::find($this->student_id);

        if ($student === null) {
            return collect();
        }

        return $student->guardians()
            ->wherePivot('status', GuardianLinkStatus::Approved)
            ->whereNotNull('phone')
            ->get();
    }

    public function send(): void
    {
        $this->authorize('create', SmsMessage::class);

        $data = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'body' => ['required', 'string', 'max:480'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $establishmentId = $student->establishment_id;

        $guardians = $student->guardians()
            ->wherePivot('status', GuardianLinkStatus::Approved)
            ->whereNotNull('phone')
            ->get();

        foreach ($guardians as $guardian) {
            $smsMessage = SmsMessage::create([
                'establishment_id' => $establishmentId,
                'guardian_id' => $guardian->id,
                'phone' => $guardian->phone,
                'body_rendered' => $this->body,
                'status' => 'queued',
                'related_type' => Student::class,
                'related_id' => $student->id,
            ]);

            SendSmsJob::dispatch($smsMessage->id, $establishmentId);
        }

        $this->confirmation = $guardians->isEmpty()
            ? __('Aucun tuteur approuvé avec numéro de téléphone pour cet élève.')
            : __('SMS envoyé à :count tuteur(s).', ['count' => $guardians->count()]);

        $this->reset(['student_id', 'body']);
    }

    public function render()
    {
        return view('livewire.notifications.sms-messages.send', [
            'students' => Student::orderBy('last_name')->orderBy('first_name')->get(),
        ])->title(__('Envoyer un SMS'));
    }
}
