<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backup;

use App\Domain\Backup\Services\BackupExportService;
use App\Domain\Backup\Services\BackupTableRegistry;
use App\Domain\Backup\Support\BackupOperation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupExportController extends Controller
{
    public function __invoke(BackupTableRegistry $registry, BackupExportService $exporter): BinaryFileResponse
    {
        Gate::authorize('export', BackupOperation::class);

        set_time_limit(300);

        $relativePath = $exporter->export($registry->tables(), (string) Auth::user()?->email);

        $absolutePath = Storage::disk((string) config('backup.disk'))->path($relativePath);

        return response()->download($absolutePath)->deleteFileAfterSend();
    }
}
