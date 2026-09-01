<?php

declare(strict_types=1);

namespace App\Livewire\Backup;

use App\Domain\Backup\Services\BackupImportService;
use App\Domain\Backup\Services\BackupTableRegistry;
use App\Domain\Backup\Services\BackupWipeService;
use App\Domain\Backup\Support\BackupOperation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    public string $wipeScope = 'all';

    public ?string $wipeTable = null;

    public string $wipeConfirmationWord = '';

    public string $importScope = 'all';

    public ?string $importTable = null;

    public string $importConfirmationWord = '';

    public ?TemporaryUploadedFile $archive = null;

    /** @var array<string, int>|null */
    public ?array $lastWipeResult = null;

    /** @var array{tables: array<string, array<string, mixed>>, orphans: list<string>}|null */
    public ?array $lastImportResult = null;

    public function mount(): void
    {
        $this->authorize('export', BackupOperation::class);
    }

    public function wipe(BackupTableRegistry $registry, BackupWipeService $wiper): void
    {
        $this->authorize('wipe', BackupOperation::class);

        $this->validate([
            'wipeConfirmationWord' => ['required', 'in:VIDER'],
        ], [], ['wipeConfirmationWord' => __('mot de confirmation')]);

        $tables = $this->wipeScope === 'all'
            ? $registry->wipeableTables()
            : array_filter([$this->wipeTable]);

        set_time_limit(300);

        $this->lastWipeResult = $wiper->wipe($tables, (string) Auth::user()?->email);

        $this->reset(['wipeConfirmationWord']);
    }

    public function import(BackupTableRegistry $registry, BackupImportService $importer): void
    {
        $this->authorize('import', BackupOperation::class);

        $this->validate([
            'archive' => ['required', 'file', 'mimes:zip', 'max:'.config('backup.max_upload_size_kb')],
            'importConfirmationWord' => ['required', 'in:RESTAURER'],
        ], [], ['importConfirmationWord' => __('mot de confirmation')]);

        $tables = $this->importScope === 'all'
            ? $registry->tables()
            : array_filter([$this->importTable]);

        $relativePath = $this->archive->store((string) config('backup.upload_directory'), (string) config('backup.disk'));

        set_time_limit(300);

        try {
            $this->lastImportResult = $importer->import($relativePath, $tables, (string) Auth::user()?->email);
        } finally {
            Storage::disk((string) config('backup.disk'))->delete($relativePath);
        }

        $this->reset(['importConfirmationWord', 'archive']);
    }

    public function render()
    {
        $registry = app(BackupTableRegistry::class);

        return view('livewire.backup.index', [
            'tables' => $registry->tables(),
            'canWipe' => Auth::user()?->can('wipe', BackupOperation::class) ?? false,
            'canImport' => Auth::user()?->can('import', BackupOperation::class) ?? false,
        ])->title(__('Sauvegarde'));
    }
}
