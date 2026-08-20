<div>
    <h1 class="text-2xl font-semibold text-stone-900">Envoyer un SMS</h1>

    @if ($confirmation)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ $confirmation }}
        </div>
    @endif

    <form wire:submit="send" class="mt-6 max-w-lg space-y-4 rounded-lg border border-stone-200 bg-white p-4">
        <div>
            <label class="block text-sm font-medium text-stone-700">Élève</label>
            <select wire:model.live="student_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                <option value="">Sélectionner…</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->last_name }} {{ $student->first_name }}</option>
                @endforeach
            </select>
            @error('student_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @if ($student_id)
            <div class="rounded-lg bg-stone-50 p-3 text-sm text-stone-600">
                @if ($this->guardians->isEmpty())
                    Aucun tuteur approuvé avec numéro de téléphone pour cet élève.
                @else
                    Destinataires : {{ $this->guardians->pluck('first_name')->implode(', ') }}
                @endif
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-stone-700">Message</label>
            <textarea wire:model="body" rows="4" class="mt-1 block w-full rounded-lg border-stone-300 text-sm" maxlength="480"></textarea>
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            Envoyer
        </button>
    </form>
</div>
