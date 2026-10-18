<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Wake\Content\Entry;
use Wake\Content\Index;
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

        return view('wake::livewire.media-library', [
            'files' => $files->sortByDesc('mtime')->take(200)->values(),
        ]);
    }
}
