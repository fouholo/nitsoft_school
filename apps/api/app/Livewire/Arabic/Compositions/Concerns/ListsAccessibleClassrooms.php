<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Compositions\Concerns;

use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait ListsAccessibleClassrooms
{
    /**
     * Classes arabes primaires de l'année de la composition, limitées pour
     * un enseignant à celles auxquelles il est affecté.
     *
     * @return Collection<int, ArabicClassroom>
     */
    private function accessibleClassrooms(ArabicComposition $arabicComposition): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return ArabicClassroom::query()
            ->primary()
            ->where('school_year_id', $arabicComposition->school_year_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (ArabicClassroom $arabicClassroom) => $user->can('accessClassroom', [ArabicComposition::class, $arabicClassroom]))
            ->values()
            ->toBase();
    }
}
