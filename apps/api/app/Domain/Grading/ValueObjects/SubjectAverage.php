<?php

declare(strict_types=1);

namespace App\Domain\Grading\ValueObjects;

use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Academics\Models\Subject;

final class SubjectAverage
{
    public function __construct(
        public readonly Subject|PrimarySubject $subject,
        public readonly ?float $average,
        public readonly ?float $coefficient = null,
    ) {}
}
