<div>
    <h1 class="text-2xl font-semibold text-slate-900">Lier un enfant</h1>
    <p class="mt-1 text-sm text-slate-500">
        Saisissez le code (uid) de l'élève, fourni par l'établissement, pour demander à être lié à son dossier.
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 max-w-md rounded-md border border-slate-200 bg-white p-4">
        <form wire:submit="search" class="flex items-end gap-2">
            <div class="flex-1">
                <label for="uid" class="block text-sm font-medium text-slate-700">Code de l'élève</label>
                <input
                    type="text"
                    id="uid"
                    wire:model="uid"
                    maxlength="12"
                    placeholder="000000001046"
                    class="mt-1 block w-full rounded-md border-slate-300 text-sm"
                >
            </div>
            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Rechercher
            </button>
        </form>
        @error('uid') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

        @if ($foundStudent)
            <div class="mt-4 rounded-md bg-slate-50 p-3 text-sm">
                <p class="font-medium text-slate-900">{{ $foundStudent->last_name }} {{ $foundStudent->first_name }}</p>
            </div>

            <form wire:submit="requestLink" class="mt-4 space-y-3">
                <div>
                    <label for="relationship" class="block text-sm font-medium text-slate-700">Votre rôle auprès de cet élève</label>
                    <select wire:model="relationship" id="relationship" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <option value="">—</option>
                        @foreach (\App\Domain\Enrollment\Enums\GuardianRelationship::cases() as $relationshipOption)
                            <option value="{{ $relationshipOption->value }}">{{ $relationshipOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('relationship') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Envoyer la demande
                </button>
            </form>
        @endif
    </div>
</div>
