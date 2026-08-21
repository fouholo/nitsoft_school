<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Expense;
use App\Domain\Billing\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Agrège les encaissements (Payment) et dépenses (Expense) par utilisateur
 * et par rôle, sur une période donnée — voir
 * docs/superpowers/specs/2026-08-21-bilan-financier-par-profil-design.md.
 *
 * Travaille sur des tableaux PHP simples plutôt que sur des Collection
 * chaînées : les génériques invariants d'Illuminate\Support\Collection
 * rendent ce type d'agrégation imbriquée fragile côté analyse statique.
 */
final class FinancialSummaryService
{
    /**
     * @var list<string>
     */
    private const ROLE_ORDER = ['fondateur', 'directeur', 'gestionnaire', 'caissier', 'educateur'];

    /**
     * @return list<array{user_id: int, user_name: string, role: string|null, collected: float, spent: float, net: float}>
     */
    public function summaryByUser(CarbonInterface $start, CarbonInterface $end, ?int $ownerId = null, ?int $establishmentId = null): array
    {
        $establishmentId ??= (int) app('currentEstablishmentId');

        $collected = Payment::query()
            ->whereBetween('paid_at', [$start, $end])
            ->when($ownerId, fn ($query) => $query->where('received_by', $ownerId))
            ->selectRaw('received_by as user_id, SUM(amount) as total')
            ->groupBy('received_by')
            ->pluck('total', 'user_id')
            ->all();

        $spent = Expense::query()
            ->whereBetween('spent_at', [$start, $end])
            ->when($ownerId, fn ($query) => $query->where('recorded_by', $ownerId))
            ->selectRaw('recorded_by as user_id, SUM(amount) as total')
            ->groupBy('recorded_by')
            ->pluck('total', 'user_id')
            ->all();

        $userIds = array_values(array_unique(array_merge(array_keys($collected), array_keys($spent))));

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $rows = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $user = $users->get($userId);
            $collectedAmount = (float) ($collected[$userId] ?? 0);
            $spentAmount = (float) ($spent[$userId] ?? 0);

            $rows[] = [
                'user_id' => $userId,
                'user_name' => $user->name,
                'role' => $user->roleFor($establishmentId),
                'collected' => $collectedAmount,
                'spent' => $spentAmount,
                'net' => $collectedAmount - $spentAmount,
            ];
        }

        return $rows;
    }

    /**
     * Regroupe le résultat de summaryByUser() par rôle, dans l'ordre
     * hiérarchique usuel de l'application, les rôles non reconnus ou
     * sans rattachement actif (role === null) toujours en dernier
     * sous le libellé « Autre ».
     *
     * @param  list<array{user_id: int, user_name: string, role: string|null, collected: float, spent: float, net: float}>  $summary
     * @return list<array{role: string|null, roleLabel: string, rows: list<array{user_id: int, user_name: string, role: string|null, collected: float, spent: float, net: float}>, collected: float, spent: float, net: float}>
     */
    public function groupByRole(array $summary): array
    {
        $byRole = [];
        $withoutRole = [];

        foreach ($summary as $row) {
            if ($row['role'] === null) {
                $withoutRole[] = $row;
            } else {
                $byRole[$row['role']][] = $row;
            }
        }

        $orderedRoles = array_values(array_filter(self::ROLE_ORDER, fn (string $role): bool => isset($byRole[$role])));

        foreach (array_keys($byRole) as $role) {
            if (! in_array($role, $orderedRoles, true)) {
                $orderedRoles[] = $role;
            }
        }

        $groups = [];

        foreach ($orderedRoles as $role) {
            $groups[] = $this->buildRoleGroup($role, $byRole[$role]);
        }

        if ($withoutRole !== []) {
            $groups[] = $this->buildRoleGroup(null, $withoutRole);
        }

        return $groups;
    }

    /**
     * @param  list<array{user_id: int, user_name: string, role: string|null, collected: float, spent: float, net: float}>  $rows
     * @return array{role: string|null, roleLabel: string, rows: list<array{user_id: int, user_name: string, role: string|null, collected: float, spent: float, net: float}>, collected: float, spent: float, net: float}
     */
    private function buildRoleGroup(?string $role, array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => $a['user_name'] <=> $b['user_name']);

        $collected = (float) array_sum(array_column($rows, 'collected'));
        $spent = (float) array_sum(array_column($rows, 'spent'));

        return [
            'role' => $role,
            'roleLabel' => $role !== null ? User::roleLabel($role) : 'Autre',
            'rows' => $rows,
            'collected' => $collected,
            'spent' => $spent,
            'net' => $collected - $spent,
        ];
    }
}
