<?php

namespace Wake\Livewire;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Wake\Content\Categories;
use Wake\Content\Category;
use Wake\Content\Index;
use Wake\Content\Query;
use Wake\Localization\Languages;

/**
 * Post categories: name and address per language, fields for the editor,
 * template and sort order. Owners and administrators only.
 */
class CategoryManager extends Component
{
    /** Key of the category being edited, "" for a new one, null for none. */
    public ?string $editing = null;

    public string $key = '';

    /** @var array<string, string> per site language */
    public array $titles = [];

    /** @var array<string, string> per site language */
    public array $slugs = [];

    /** @var array<string, string> per site language */
    public array $descriptions = [];

    public string $template = '';

    public bool $ownUrl = false;

    public string $list = 'newest';

    public string $dateField = '';

    public string $icon = 'tag';

    /**
     * @var list<array{key: string, label_de: string, label_en: string, type: string, required: bool, translate: bool, options: string}>
     */
    public array $fields = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('categories.manage'), 403);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->editing = '';
        $this->fields = [$this->emptyField()];
    }

    public function edit(string $key, Categories $categories): void
    {
        $category = $categories->get($key) ?? abort(404);
        $data = $category->toArray();
        $codes = app(Languages::class)->codes();

        $this->resetForm();
        $this->editing = $key;
        $this->key = $key;

        foreach ($codes as $code) {
            $this->titles[$code] = $category->title($code);
            $this->slugs[$code] = $category->slug($code);
            $this->descriptions[$code] = $category->description($code);
        }

        $this->template = (string) ($data['template'] ?? '');
        $this->ownUrl = $category->urlPattern() !== null;
        $this->list = $category->listMode();
        $this->dateField = (string) ($data['date_field'] ?? '');
        $this->icon = $category->icon();

        foreach ($category->fields() as $fieldKey => $field) {
            $label = $field['label'] ?? $fieldKey;
            $this->fields[] = [
                'key' => $fieldKey,
                'label_de' => is_array($label) ? (string) ($label['de'] ?? '') : (string) $label,
                'label_en' => is_array($label) ? (string) ($label['en'] ?? '') : (string) $label,
                'type' => (string) ($field['type'] ?? 'text'),
                'required' => (bool) ($field['required'] ?? false),
                'translate' => ($field['translate'] ?? true) !== false,
                'options' => collect((array) ($field['options'] ?? []))->map(fn ($text, $value) => $value.' = '.(is_array($text) ? ($text[app()->getLocale()] ?? reset($text)) : $text))->implode("\n"),
                'raw' => $field,
            ];
        }
    }

    public function addField(): void
    {
        $this->fields[] = $this->emptyField();
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function moveField(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (isset($this->fields[$index], $this->fields[$target])) {
            [$this->fields[$index], $this->fields[$target]] = [$this->fields[$target], $this->fields[$index]];
        }
    }

    public function save(Categories $categories): void
    {
        $codes = app(Languages::class)->codes();
        $isNew = $this->editing === '';

        $this->key = Str::slug($this->key ?: ($this->titles[$codes[0]] ?? ''));

        $this->validate([
            'key' => ['required', 'alpha_dash', 'max:40', $isNew ? Rule::notIn(array_keys($categories->all())) : 'nullable'],
            'titles.'.$codes[0] => 'required|string|max:60',
            'slugs.*' => ['nullable', 'regex:/^[a-z0-9-]*$/'],
            'fields.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', Rule::notIn(EntryEditor::BASE_FIELDS)],
            'fields.*.type' => ['required', Rule::in(Category::fieldTypes())],
        ], [
            'fields.*.key.regex' => __('Field names use small letters, digits and _, starting with a letter.'),
            'fields.*.key.not_in' => __('This field name is used by Wake itself.'),
        ]);

        $fields = [];

        foreach ($this->fields as $field) {
            $definition = array_filter([
                'type' => $field['type'],
                'label' => array_filter(['de' => trim($field['label_de']), 'en' => trim($field['label_en'])]) ?: null,
                'required' => $field['required'] ?: null,
                'translate' => $field['translate'] ? null : false,
            ], fn ($v) => $v !== null);

            // Keep placeholders, hints and list fields written in the file by hand.
            foreach (['placeholder', 'hint', 'list'] as $extra) {
                if (isset($field['raw'][$extra])) {
                    $definition[$extra] = $field['raw'][$extra];
                }
            }

            if ($field['type'] === 'select') {
                $definition['options'] = $this->parseOptions($field['options'], $field['raw']['options'] ?? []);
            }

            $fields[$field['key']] = $definition;
        }

        $data = array_filter([
            'title' => array_filter($this->titles),
            'slug' => array_filter(array_map(fn ($s) => Str::slug((string) $s), $this->slugs)),
            'description' => array_filter($this->descriptions) ?: null,
            'icon' => $this->icon,
            'template' => $this->template !== '' ? Str::slug($this->template) : null,
            'url' => $this->ownUrl ? '{category}/{slug}' : null,
            'list' => $this->list,
            'date_field' => $this->list === 'upcoming' && $this->dateField !== '' ? $this->dateField : null,
            'fields' => $fields,
        ], fn ($v) => $v !== null && $v !== []);

        $categories->save($this->key, $data);
        app(Index::class)->refresh(force: true);

        session()->flash('status', __('Category “:name” saved.', ['name' => $this->titles[$codes[0]]]));
        $this->editing = null;
    }

    public function delete(string $key, Categories $categories): void
    {
        $categories->trash($key);
        app(Index::class)->refresh(force: true);
        session()->flash('status', __('Category removed. Its posts keep their texts and fields.'));
        $this->editing = null;
    }

    public function cancel(): void
    {
        $this->editing = null;
    }

    protected function parseOptions(string $text, array $previous): array
    {
        $options = [];

        foreach (preg_split('/\R/', $text) as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$value, $label] = array_pad(array_map('trim', explode('=', $line, 2)), 2, null);
            $value = Str::slug($value, '_') ?: $value;
            $label ??= $value;

            // Keep translations of option labels from the file.
            $old = $previous[$value] ?? null;
            $options[$value] = is_array($old) ? [...$old, app()->getLocale() => $label] : $label;
        }

        return $options;
    }

    protected function emptyField(): array
    {
        return ['key' => '', 'label_de' => '', 'label_en' => '', 'type' => 'text', 'required' => false, 'translate' => true, 'options' => '', 'raw' => []];
    }

    protected function resetForm(): void
    {
        $this->reset('key', 'titles', 'slugs', 'descriptions', 'template', 'ownUrl', 'list', 'dateField', 'icon', 'fields');
        $this->resetErrorBag();
    }

    public function render(Categories $categories): View
    {
        return view('wake::livewire.category-manager', [
            'categories' => $categories->all(),
            'counts' => collect($categories->all())->map(fn ($c) => (new Query('post'))->withFallback(true)->category($c->key)->count()),
            'languages' => app(Languages::class),
            'types' => Category::fieldTypes(),
        ]);
    }
}
