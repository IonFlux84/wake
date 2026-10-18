<?php

namespace Wake\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Wake\Accounts\Permissions;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Content\Writer;
use Wake\Localization\Languages;
use Wake\Media\Media;

/**
 * The media library: every file in content/media/, in folders. Shows where
 * a file is used, lists unused ones and warns before deleting used files.
 */
class MediaLibrary extends Component
{
    use WithFileUploads;

    public const FILE_RULE = 'required|file|max:51200|mimes:jpg,jpeg,png,gif,webp,avif,svg,pdf,zip,mp3,mp4,docx,xlsx,pptx,odt,ods,txt,csv,epub';

    #[Url]
    public string $kind = 'images';

    #[Url]
    public string $search = '';

    /** A folder below media/, "" for all. */
    #[Url]
    public string $folder = '';

    #[Url]
    public bool $unused = false;

    /** The file being edited: its path below media/. */
    #[Url]
    public string $file = '';

    /** @var array<string, string> per site language */
    public array $alt = [];

    /** @var array<string, string> per site language */
    public array $caption = [];

    public string $credit = '';

    public string $focus = '50% 50%';

    /** @var mixed */
    public $upload = null;

    /** Where uploads go, below media/. */
    public string $uploadFolder = 'uploads';

    public function select(string $path): void
    {
        $this->file = $path;
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
        abort_unless(Media::find($this->file) && Permissions::mayEditMedia(auth()->user(), $this->file), 403);

        $this->validate(['focus' => ['required', 'regex:/^\d{1,3}% \d{1,3}%$/'], 'credit' => 'nullable|string|max:200', 'alt.*' => 'nullable|string|max:500', 'caption.*' => 'nullable|string|max:1000']);

        $languages = app(Languages::class);
        $values = fn (array $byLanguage) => $languages->isMultilingual() ? $byLanguage : ($byLanguage[$languages->default()] ?? '');

        $writer->saveMediaMeta('media/'.$this->file, [
            'alt' => $values($this->alt),
            'caption' => $values($this->caption),
            'credit' => $this->credit,
            'focus' => $this->focus === '50% 50%' ? '' : $this->focus,
        ]);

        session()->flash('status', __('Details saved.'));
    }

    public function deleteFile(Writer $writer): void
    {
        abort_unless(Media::find($this->file) && Permissions::mayDeleteMedia(auth()->user(), $this->file), 403);

        $writer->deleteMedia('media/'.$this->file);
        $this->file = '';
        session()->flash('status', __('File moved to the trash.'));
    }

    public function uploadFile(Writer $writer): void
    {
        $account = auth()->user();
        abort_unless($account->can('content.write') || $account->can('content.publish') || $account->can('content.edit_all'), 403);

        $this->validate(['upload' => self::FILE_RULE, 'uploadFolder' => ['required', 'string', 'max:120', 'regex:#^[a-z0-9][a-z0-9/_-]*$#i', 'not_regex:#\.\.#']]);

        $reference = $writer->storeMedia($this->uploadFolder, $this->upload->getClientOriginalName(), $this->upload->getRealPath());
        $this->upload = null;
        $this->select(substr($reference, 6));
        session()->flash('status', __('File uploaded.'));
    }

    protected function loadDetails(): void
    {
        $media = Media::find($this->file);

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
        $all = $index->allMedia();
        $folders = collect(array_keys($all))->map(fn (string $path) => dirname($path))->reject(fn (string $dir) => $dir === '.')
            ->flatMap(function (string $dir) {
                $parts = explode('/', $dir);

                return array_map(fn (int $i) => implode('/', array_slice($parts, 0, $i)), range(1, count($parts)));
            })->unique()->sort()->values();
        $search = mb_strtolower($this->search);
        $files = collect();

        foreach ($all as $path => $mtime) {
            $media = new Media($path, $mtime);

            if (($this->kind === 'images') !== $media->isImage()
                || ($this->folder !== '' && ! str_starts_with($path, $this->folder.'/'))
                || ($this->unused && $index->mediaUsage($path) !== [])
                || ($search !== '' && ! str_contains(mb_strtolower($path), $search))) {
                continue;
            }

            $files->push(['file' => $media, 'mtime' => $mtime, 'used' => count($index->mediaUsage($path))]);
        }

        $selected = $this->file !== '' ? Media::find($this->file) : null;
        $query = new Query;
        $account = auth()->user();

        return view('wake::livewire.media-library', [
            'files' => $files->sortByDesc('mtime')->take(200)->values(),
            'total' => $files->count(),
            'folders' => $folders,
            'selected' => $selected,
            'usedBy' => $selected ? collect($index->mediaUsage($this->file))->map(fn (string $id) => $query->find($id))->filter()->values() : collect(),
            'mayEdit' => $selected && Permissions::mayEditMedia($account, $this->file),
            'mayDelete' => $selected && Permissions::mayDeleteMedia($account, $this->file),
            'languages' => $languages,
        ]);
    }
}
