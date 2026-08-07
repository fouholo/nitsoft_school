<div>
    <h1 class="text-2xl font-semibold text-slate-900">Demandes de liaison</h1>
    <p class="mt-1 text-sm text-slate-500">Parents ayant demandé à être liés à un élève, en attente de validation.</p>

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Parent</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Contact</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Rôle demandé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Référence école</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($pendingLinks as $link)
                    @php
                        $roleFilled = $roleAlreadyFilled->contains("{$link->student_id}:{$link->relationship?->value}");
                        $reference = match ($link->relationship?->value) {
                            'pere' => ['name' => $link->student->father_name, 'phone' => $link->student->father_phone],
                            'mere' => ['name' => $link->student->mother_name, 'phone' => $link->student->mother_phone],
                            'tuteur' => ['name' => $link->student->tutor_name, 'phone' => $link->student->tutor_phone],
                            default => ['name' => null, 'phone' => null],
                        };
                    @endphp
                    <tr wire:key="link-request-{{ $link->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $link->guardian->last_name }} {{ $link->guardian->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $link->guardian->phone }}
                            @if ($link->guardian->email)
                                <br><span class="text-xs text-slate-400">{{ $link->guardian->email }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $link->student->last_name }} {{ $link->student->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $link->relationship?->label() }}
                            @if ($roleFilled)
                                <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Déjà pourvu</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            @if ($reference['name'] || $reference['phone'])
                                {{ $reference['name'] ?: '—' }}
                                <br><span class="text-xs text-slate-400">{{ $reference['phone'] ?: '—' }}</span>
                            @else
                                <span class="text-xs text-slate-400">Non renseigné</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button
                                wire:click="approve({{ $link->id }})"
                                @if ($roleFilled) wire:confirm="Un autre parent est déjà approuvé pour ce rôle sur cet élève. L'approuver le remplacera. Continuer ?" @endif
                                class="text-emerald-600 hover:text-emerald-800"
                            >
                                Approuver
                            </button>
                            <button
                                wire:click="reject({{ $link->id }})"
                                wire:confirm="Rejeter cette demande de liaison ?"
                                class="ml-3 text-red-500 hover:text-red-700"
                            >
                                Rejeter
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucune demande en attente.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
