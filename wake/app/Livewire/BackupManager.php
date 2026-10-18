<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Wake\Backup\Backups;

/**
 * Write, download, upload and restore backups.
 */
class BackupManager extends Component
{
    use WithFileUploads;

    public bool $withStats = false;

    /** @var TemporaryUploadedFile|null */
    public $zip = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
    }

    public function create(Backups $backups): void
    {
        $name = $backups->create($this->withStats);
        session()->flash('status', __('Backup :name written.', ['name' => $name]));
    }

    public function updatedZip(Backups $backups): void
    {
        $this->validate(['zip' => 'required|file|mimes:zip|max:512000']);

        try {
            $name = $backups->store($this->zip->getRealPath(), $this->zip->getClientOriginalName());
            session()->flash('status', __('Backup :name uploaded. Restore it from the list.', ['name' => $name]));
        } catch (InvalidArgumentException $e) {
            $this->addError('zip', $e->getMessage());
        }

        $this->zip = null;
    }

    public function restore(Backups $backups, string $name): void
    {
        $file = $backups->path($name) ?? abort(404);

        try {
            $safety = $backups->restore($file);
        } catch (InvalidArgumentException $e) {
            session()->flash('status', $e->getMessage());

            return;
        }

        session()->flash('status', __('Backup restored. The previous state is kept as :name.', ['name' => $safety]));
        $this->redirect(route('wake.backups'));
    }

    public function delete(Backups $backups, string $name): void
    {
        $backups->delete($name);
        session()->flash('status', __('Backup moved to the trash.'));
    }

    public function render(Backups $backups): View
    {
        return view('wake::livewire.backup-manager', [
            'backups' => Backups::available() ? $backups->all() : [],
            'available' => Backups::available(),
        ]);
    }
}
