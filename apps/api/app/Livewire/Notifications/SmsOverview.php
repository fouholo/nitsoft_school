<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Scopes\EstablishmentScope;
use App\Domain\Notifications\Models\SmsMessage;
use App\Domain\Notifications\Orange\OrangeSmsAccountClient;
use App\Domain\Notifications\Orange\OrangeSmsException;
use App\Domain\Notifications\Services\SmsDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use stdClass;

/**
 * Écran « Suivi des SMS » de l'administrateur SaaS : solde du compte Orange
 * de la plateforme, consommation par école (pour la refacturation) et
 * renvoi des SMS restés en attente — voir
 * docs/superpowers/specs/2026-09-30-sms-orange-design.md.
 */
#[Layout('layouts.app')]
class SmsOverview extends Component
{
    public string $month = '';

    public ?string $flash = null;

    public function mount(): void
    {
        $this->authorize('overview', SmsMessage::class);

        $this->month = now()->format('Y-m');
    }

    public function refreshBalance(OrangeSmsAccountClient $client): void
    {
        $this->authorize('overview', SmsMessage::class);

        try {
            $client->refresh();
        } catch (OrangeSmsException) {
            // L'erreur est affichée par render() à la relecture.
        }
    }

    public function resendPending(SmsDispatcher $dispatcher): void
    {
        $this->authorize('overview', SmsMessage::class);

        $pending = SmsMessage::withoutGlobalScope(EstablishmentScope::class)
            ->where('status', 'queued')
            ->orderBy('id')
            ->get();

        foreach ($pending as $message) {
            $dispatcher->dispatch($message);
        }

        $this->flash = __(':count SMS relancé(s). Ils partent dans quelques secondes.', ['count' => $pending->count()]);
    }

    public function render()
    {
        [$contracts, $balanceError] = $this->balance();

        return view('livewire.notifications.sms-overview', [
            'usesOrange' => config('sms.default') === 'orange',
            'contracts' => $contracts,
            'balanceError' => $balanceError,
            'lowBalance' => $this->isLowBalance($contracts),
            'threshold' => (int) config('sms.orange.low_balance_threshold'),
            'consumption' => $this->consumption(),
            'pendingCount' => SmsMessage::withoutGlobalScope(EstablishmentScope::class)->where('status', 'queued')->count(),
        ])->title(__('Suivi des SMS'));
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    private function balance(): array
    {
        if (config('sms.default') !== 'orange') {
            return [[], null];
        }

        try {
            return [app(OrangeSmsAccountClient::class)->contracts(), null];
        } catch (OrangeSmsException $e) {
            return [[], $e->getMessage()];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $contracts
     */
    private function isLowBalance(array $contracts): bool
    {
        if ($contracts === []) {
            return false;
        }

        $available = 0;

        foreach ($contracts as $contract) {
            $expired = isset($contract['expirationDate']) && CarbonImmutable::parse((string) $contract['expirationDate'])->isPast();

            if (($contract['status'] ?? null) !== 'ACTIVE' || $expired) {
                continue;
            }

            $available += (int) ($contract['availableUnits'] ?? 0);
        }

        return $available < (int) config('sms.orange.low_balance_threshold');
    }

    /**
     * SMS créés dans le mois choisi, par établissement, toutes écoles
     * confondues.
     *
     * @return Collection<int, array{name: string, sent: int, failed: int, queued: int}>
     */
    private function consumption(): Collection
    {
        $start = preg_match('/^\d{4}-\d{2}$/', $this->month) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $this->month)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $rows = SmsMessage::withoutGlobalScope(EstablishmentScope::class)
            ->toBase()
            ->whereBetween('created_at', [$start, $start->endOfMonth()])
            ->selectRaw("establishment_id,
                SUM(CASE WHEN status IN ('sent', 'delivered') THEN 1 ELSE 0 END) AS sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued_count")
            ->groupBy('establishment_id')
            ->get();

        $names = Establishment::withTrashed()
            ->whereIn('id', $rows->pluck('establishment_id'))
            ->pluck('name', 'id');

        return $rows
            ->map(fn (stdClass $row): array => [
                'name' => (string) ($names[$row->establishment_id] ?? '#'.$row->establishment_id),
                'sent' => (int) $row->sent_count,
                'failed' => (int) $row->failed_count,
                'queued' => (int) $row->queued_count,
            ])
            ->sortBy('name')
            ->values();
    }
}
