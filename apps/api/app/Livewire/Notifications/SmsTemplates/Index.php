<?php

declare(strict_types=1);

namespace App\Livewire\Notifications\SmsTemplates;

use App\Domain\Notifications\Models\SmsTemplate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Modèles SMS')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $body = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', SmsTemplate::class);
    }

    public function create(): void
    {
        $this->authorize('create', SmsTemplate::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $smsTemplateId): void
    {
        $smsTemplate = SmsTemplate::findOrFail($smsTemplateId);

        $this->authorize('update', $smsTemplate);

        $this->editingId = $smsTemplate->id;
        $this->code = $smsTemplate->code;
        $this->body = $smsTemplate->body;
        $this->is_active = $smsTemplate->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            $smsTemplate = SmsTemplate::findOrFail($this->editingId);
            $this->authorize('update', $smsTemplate);
        } else {
            $this->authorize('create', SmsTemplate::class);
            $smsTemplate = new SmsTemplate;
        }

        $smsTemplate->fill($data);
        $smsTemplate->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $smsTemplateId): void
    {
        $smsTemplate = SmsTemplate::findOrFail($smsTemplateId);

        $this->authorize('delete', $smsTemplate);

        $smsTemplate->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'body']);
        $this->is_active = true;
    }

    public function render()
    {
        return view('livewire.notifications.sms-templates.index', [
            'smsTemplates' => SmsTemplate::orderBy('code')->get(),
        ]);
    }
}
