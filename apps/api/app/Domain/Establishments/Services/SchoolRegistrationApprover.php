<?php

declare(strict_types=1);

namespace App\Domain\Establishments\Services;

use App\Domain\Establishments\Exceptions\SchoolRegistrationConflictException;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\EstablishmentUserPivot;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\FoundationUserPivot;
use App\Domain\Establishments\Models\SchoolRegistration;
use App\Domain\Establishments\Support\UniqueSlug;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transforme une demande d'inscription de fondateur en compte + école
 * (+ groupe scolaire) réels, ou la refuse. Voir
 * docs/superpowers/specs/2026-09-29-inscription-fondateur-design.md.
 */
class SchoolRegistrationApprover
{
    /**
     * @throws SchoolRegistrationConflictException
     */
    public function approve(SchoolRegistration $registration, User $author): Establishment
    {
        $establishment = DB::transaction(function () use ($registration): Establishment {
            $this->ensureCredentialsStillFree($registration);

            // Le mot de passe est déjà haché dans la demande : le cast
            // `hashed` de User le reconnaît (Hash::isHashed) et ne le
            // re-hache pas.
            $user = User::create([
                'name' => $registration->name,
                'first_name' => $registration->first_name,
                'email' => $registration->email,
                'pseudo' => $registration->pseudo,
                'password' => $registration->getAttributes()['password'],
            ]);

            $foundation = $registration->isForFoundation()
                ? Foundation::create([
                    'name' => $registration->foundation_name,
                    'slug' => UniqueSlug::for(Foundation::class, (string) $registration->foundation_name),
                    'is_active' => true,
                ])
                : null;

            $establishment = Establishment::create([
                'foundation_id' => $foundation?->id,
                'name' => $registration->establishment_name,
                'slug' => UniqueSlug::for(Establishment::class, $registration->establishment_name),
                'type' => $registration->establishment_type,
                'inspection_id' => $registration->inspection_id,
                'direction_id' => $registration->direction_id,
                'phone' => $registration->phone,
                'address' => $registration->address,
                'is_active' => true,
            ]);

            if ($foundation !== null) {
                FoundationUserPivot::create([
                    'foundation_id' => $foundation->id,
                    'user_id' => $user->id,
                    'role' => 'fondateur',
                    'is_active' => true,
                    'is_general_admin' => true,
                ]);
            } else {
                EstablishmentUserPivot::create([
                    'establishment_id' => $establishment->id,
                    'user_id' => $user->id,
                    'role' => 'fondateur',
                    'is_active' => true,
                    'is_general_admin' => true,
                ]);
            }

            $registration->delete();

            return $establishment;
        });

        Log::info('school_registration.approved', [
            'email' => $registration->email,
            'establishment_id' => $establishment->id,
            'foundation_id' => $establishment->foundation_id,
            'author' => $author->email,
        ]);

        return $establishment;
    }

    public function reject(SchoolRegistration $registration, User $author): void
    {
        $registration->delete();

        Log::info('school_registration.rejected', [
            'email' => $registration->email,
            'establishment_name' => $registration->establishment_name,
            'author' => $author->email,
        ]);
    }

    private function ensureCredentialsStillFree(SchoolRegistration $registration): void
    {
        if (User::where('email', $registration->email)->exists()) {
            throw SchoolRegistrationConflictException::emailTaken($registration->email);
        }

        if (User::whereRaw('LOWER(pseudo) = ?', [mb_strtolower($registration->pseudo)])->exists()) {
            throw SchoolRegistrationConflictException::pseudoTaken($registration->pseudo);
        }
    }
}
