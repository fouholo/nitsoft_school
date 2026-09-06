<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffManualPdfController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path('manuals/manuel-personnel.pdf'));
    }
}
