<?php

declare(strict_types=1);

namespace App\Domain\Arabic\ValueObjects;

use App\Domain\Arabic\Models\ArabicSubject;

final class ArabicSubjectAverage
{
    public function __construct(
        public readonly ArabicSubject $subject,
        public readonly ?float $average,
        public readonly ?int $rank = null,
        public readonly ?string $appreciation = null,
        public readonly ?string $teacherName = null,
    ) {}
}
