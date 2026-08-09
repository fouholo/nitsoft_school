<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Academics\Models\TeacherAssignment;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\User;
use App\Policies\Concerns\ChecksEstablishmentMembership;

class AttendanceSessionPolicy
{
    use ChecksEstablishmentMembership;

    public function viewAny(User $user): bool
    {
        return $this->isMemberOfCurrentEstablishment($user);
    }

    public function view(User $user, AttendanceSession $attendanceSession): bool
    {
        return $this->belongsToSameEstablishment($user, $attendanceSession->establishment_id);
    }

    public function create(User $user): bool
    {
        if ($this->isAdminOfCurrentEstablishment($user)) {
            return true;
        }

        return $user->currentRole() === 'enseignant'
            && TeacherAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, AttendanceSession $attendanceSession): bool
    {
        if (! $this->belongsToSameEstablishment($user, $attendanceSession->establishment_id)) {
            return false;
        }

        if ($this->isAdminOfCurrentEstablishment($user)) {
            return true;
        }

        return $user->id === $attendanceSession->teacher_id
            && $user->isAssignedToClassroom($attendanceSession->classroom_id);
    }

    public function delete(User $user, AttendanceSession $attendanceSession): bool
    {
        return $this->update($user, $attendanceSession);
    }
}
