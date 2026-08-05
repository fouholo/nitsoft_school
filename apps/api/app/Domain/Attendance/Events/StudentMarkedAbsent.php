<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Events;

use App\Domain\Attendance\Models\AttendanceRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentMarkedAbsent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public AttendanceRecord $record) {}
}
