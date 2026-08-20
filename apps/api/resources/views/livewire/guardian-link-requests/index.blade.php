<div>
    <h1 class="text-2xl font-semibold text-stone-900">Demandes de liaison</h1>
    <p class="mt-1 text-sm text-stone-500">Parents ayant demandé à être liés à un élève, en attente de validation.</p>

    @if ($successMessage)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ $successMessage }}
        </div>
    @endif

    @if ($errorMessage)
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            {{ $errorMessage }}
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Parent</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Vérification</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Élève</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Rôle demandé</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($pendingLinks as $link)
                        @php
                            $roleFilled = $roleAlreadyFilled->contains("{$link->student_id}:{$link->relationship?->value}");
                            $ref = $references[$link->id];
                            $confirmMessage = match (true) {
                                $roleFilled => "Un autre parent est déjà approuvé pour ce rôle sur cet élève. L'approuver le remplacera. Continuer ?",
                                $ref['match'] === 'missing' => 'Aucune référence en dossier pour vérifier cette identité. Approuver quand même ? Le tuteur aura accès au portail et aux notifications SMS.',
                                default => 'Approuver cette demande ? Le tuteur aura accès au portail et aux notifications SMS.',
                            };
                        @endphp
                        <tr wire:key="link-request-{{ $link->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">
                                {{ $link->guardian->last_name }} {{ $link->guardian->first_name }}
                                @if ($link->guardian->email)
                                    <br><span class="text-xs text-stone-400">{{ $link->guardian->email }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-stone-600">
                                <div>
                                    <span class="text-xs uppercase tracking-wide text-stone-400">Téléphone déclaré</span>
                                    <div class="whitespace-nowrap text-stone-900">{{ $link->guardian->phone ?: '—' }}</div>
                                </div>
                                <div class="mt-2">
                                    <span class="text-xs uppercase tracking-wide text-stone-400">Référence école ({{ $link->relationship?->label() }})</span>
                                    @if ($ref['match'] === 'missing')
                                        <div class="whitespace-nowrap font-medium text-amber-700">Aucune référence en dossier</div>
                                    @else
                                        <div class="whitespace-nowrap text-stone-900">{{ $ref['name'] ?: '—' }}</div>
                                        <div class="whitespace-nowrap text-xs text-stone-500">{{ $ref['phone'] ?: '—' }}</div>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    @if ($ref['match'] === 'match')
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">✓ Correspond</span>
                                    @elseif ($ref['match'] === 'mismatch')
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">⚠ Ne correspond pas</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">⚠ Invérifiable</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $link->student->last_name }} {{ $link->student->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">
                                {{ $link->relationship?->label() }}
                                @if ($roleFilled)
                                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Déjà pourvu</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($link->guardian->user_id === null)
                                        <span class="text-xs text-amber-700" title="Ce tuteur n'a pas de compte utilisateur — contactez le support.">Compte manquant</span>
                                        <button disabled class="inline-flex min-h-11 cursor-not-allowed items-center gap-1 rounded-lg px-2 text-stone-400">
                                            <span aria-hidden="true">✓</span> Approuver
                                        </button>
                                    @else
                                        <button
                                            wire:click="approve({{ $link->id }})"
                                            wire:confirm="{{ $confirmMessage }}"
                                            class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-emerald-700 hover:bg-emerald-50"
                                        >
                                            <span aria-hidden="true">✓</span> Approuver
                                        </button>
                                    @endif
                                    <button
                                        wire:click="reject({{ $link->id }})"
                                        wire:confirm="Rejeter cette demande de liaison ?"
                                        class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-red-600 hover:bg-red-50"
                                    >
                                        <span aria-hidden="true">✕</span> Rejeter
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">Aucune demande en attente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($recentlyRejected->isNotEmpty())
        <div class="mt-6">
            <h2 class="text-sm font-medium text-stone-500">Rejetées récemment</h2>
            <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-100 text-sm">
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($recentlyRejected as $link)
                                <tr wire:key="rejected-{{ $link->id }}">
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $link->guardian->last_name }} {{ $link->guardian->first_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-500">{{ $link->student->last_name }} {{ $link->student->first_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-500">{{ $link->relationship?->label() }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <button
                                            wire:click="reconsider({{ $link->id }})"
                                            wire:confirm="Remettre cette demande en attente ?"
                                            class="inline-flex min-h-11 items-center text-orange-700 hover:text-orange-900"
                                        >
                                            Réexaminer
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
