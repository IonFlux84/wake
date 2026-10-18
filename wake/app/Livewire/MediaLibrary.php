<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Accounts\Permissions;
use Wake\Content\Entry;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;

/**
 * All files of all entries in one place. Files belong to an entry, so
 * uploading happens in the editor.
 */
class MediaLibrary extends Component
{
    #[Url]
    public string $kind = 'images';

    #[Url]
    public string $search = '';

    /** The file being edited: "{entry id}|{file name}". */
    #[Url]
    public string $file = '';

    /** @var array<string, string> per site language */
    public array $alt = [];

    /** @var array<string, string> per site language */
    public array $caption = [];

    public string $credit = '';

    public string $focus = '50% 50%';

    public function select(string $id, string $name): void
    {
        $this->file = $id.'|'.$name;
        $this->loadDetails();
    }

    public function mount(): void
    {
        if ($this->file !== '') {
            $this->loadDetails();
        }
    }

    public function setFocus(int $x, int $y): void
    {
        $this->focus = max(0, min(100, $x)).'% '.max(0, min(100, $y)).'%';
    }

    public function saveDetails(Writer $writer): void
    {
        [$id, $name] = explode('|', $this->file, 2);
        $entry = (new Query)->find($id);
        abort_unless($entry && Permissions::mayEdit(auth()->user(), $entry), 403);

        $this->validate(['focus' => ['required', 'regex:/^\d{1,3}% \d{1,3}%$/'], 'credit' => 'nullable|string|max:200', 'alt.*' => 'nullable|string|max:500', 'caption.*' => 'nullable|string|max:1000']);

        $languages = app(Languages::class);
        $values = fn (array $byLanguage) => $languages->isMultilingual() ? $byLanguage : ($byLanguage[$languages->default()] ?? '');

        $writer->saveMediaMeta($id, $name, [
            'alt' => $values($this->alt),
            'caption' => $values($this->caption),
            'credit' => $this->credit,
            'focus' => $this->focus === '50% 50%' ? '' : $this->focus,
        ]);

        session()->flash('status', __('Details saved.'));
    }

    public function deleteFile(Writer $writer): void
    {
        [$id, $name] = explode('|', $this->file, 2);
        $entry = (new Query)->find($id);
        abort_unless($entry && Permissions::mayEdit(auth()->user(), $entry), 403);

        $writer->deleteMedia($id, $name);
        $this->file = '';
        session()->flash('status', __('File moved to the trash.'));
    }

    protected function loadDetails(): void
    {
        [$id, $name] = array_pad(explode('|', $this->file, 2), 2, '');
        $media = (new Query)->find($id)?->file($name);

        if (! $media) {
            $this->file = '';

            return;
        }

        $meta = $media->meta();
        $languages = app(Languages::class);

        foreach ($languages->codes() as $code) {
            $this->alt[$code] = (string) (is_array($meta['alt'] ?? null) ? ($meta['alt'][$code] ?? '') : ($code === $languages->default() ? ($meta['alt'] ?? '') : ''));
            $this->caption[$code] = (string) (is_array($meta['caption'] ?? null) ? ($meta['caption'][$code] ?? '') : ($code === $languages->default() ? ($meta['caption'] ?? '') : ''));
        }

        $this->credit = (string) ($meta['credit'] ?? '');
        $this->focus = $media->focus();
    }

    public function render(Index $index, Languages $languages): View
    {
        $files = collect();

        foreach ($index->entries() as $row) {
            if ($row['media'] === []) {
                continue;
            }

            $entry = Entry::make($row, $languages->default());

            foreach ($entry->media() as $file) {
                if (($this->kind === 'images') !== $file->isImage()) {
                    continue;
                }

                if ($this->search !== '' && ! str_contains(mb_strtolower($file->name().' '.$entry->title()), mb_strtolower($this->search))) {
                    continue;
                }

                $files->push(['file' => $file, 'entry' => $entry, 'mtime' => $row['media'][$file->name()]]);
            }
        }

        [$id, $name] = array_pad(explode('|', $this->file, 2), 2, '');

        return view('wake::livewire.media-library', [
            'files' => $files->sortByDesc('mtime')->take(200)->values(),
            'selected' => $this->file !== '' ? (new Query)->find($id)?->file($name) : null,
            'selectedEntry' => $this->file !== '' ? (new Query)->find($id) : null,
            'languages' => $languages,
        ]);
    }
}
