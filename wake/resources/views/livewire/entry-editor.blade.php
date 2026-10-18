@php
    $statusClass = $entry && $entry->hasTranslation($language) ? ($entry->isScheduled() ? 'scheduled' : ($entry->isPublished() ? 'published' : 'draft')) : 'draft';
    $statusText = ['published' => __('Published'), 'scheduled' => __('Scheduled'), 'draft' => __('Draft')][$statusClass];
    $isPublished = $statusClass === 'published';
    $labels = [
        'h2' => __('Heading'), 'h3' => __('Subheading'), 'ul' => __('List'), 'ol' => __('Numbered list'), 'quote' => __('Quote'),
        'code' => __('Code'), 'divider' => __('Divider'), 'image' => __('Image'), 'callout' => __('Callout'), 'button' => __('Button'),
        'embed' => __('Embed (YouTube, map …)'), 'link' => __('Link address'),
    ];
    $duration = fn (int $s) => $s >= 60 ? intdiv($s, 60).' min '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).' s' : $s.' s';
@endphp
<div style="display:flex;flex-direction:column;gap:20px" @if ($id) wire:poll.30s="keepalive" @endif
     x-data="{ saved: '' }" x-on:wake-saved.window="saved = $event.detail.at"
     @keydown.window.prevent.cmd.s="$wire.save()" @keydown.window.prevent.ctrl.s="$wire.save()">

    <header class="page-head" style="align-items:center">
        <div>
            <div class="eyebrow">
                <a href="{{ route('wake.entries', ['type' => $type]) }}" style="color:inherit;text-decoration:none">← {{ ['post' => __('Posts'), 'page' => __('Pages'), 'tag' => __('Tags')][$type] ?? $contentType->title }}</a>
                · <span class="status {{ $statusClass }}">{{ $statusText }}</span>
                <span x-show="saved" x-cloak> · {{ __('saved at') }} <span x-text="saved"></span></span>
            </div>
        </div>
        <div class="actions">
            @if ($languages->isMultilingual())
                <div class="segmented" role="group" aria-label="{{ __('Language') }}">
                    @foreach ($languages->all() as $code => $name)
                        <button type="button" wire:click="switchLanguage('{{ $code }}')" @class(['on' => $language === $code]) title="{{ $name }}">
                            {{ strtoupper($code) }}@if ($entry && ! $entry->hasTranslation($code)) <span class="muted">+</span>@endif
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($entry?->hasTranslation($language) && $entry->isPublished())
                <a class="btn" href="{{ $entry->translation($language)->url() }}" target="_blank" rel="noopener"><x-wake::icon name="eye" />{{ __('View') }}</a>
            @endif
            <button type="button" class="btn" wire:click="save" wire:loading.attr="disabled">{{ $isPublished ? __('Update') : __('Save draft') }}</button>
            @if ($mayPublish && ! $isPublished)
                <button type="button" class="btn btn-primary" wire:click="publish">{{ $scheduleAt ? __('Schedule') : __('Publish') }}</button>
            @endif
        </div>
    </header>

    @if ($otherEditor)
        <div class="flash error" role="alert">{{ __(':name is editing this entry right now. Changes made at the same time may overwrite each other.', ['name' => $otherEditor]) }}</div>
    @endif
    @if ($entry?->get('review'))
        <div class="flash" role="status">{{ __('An assistant asked to publish this entry. Check it and click Publish to approve.') }}</div>
    @endif
    @if ($entry && ! $entry->hasTranslation($language))
        <div class="flash" role="status">{{ __('This entry has no :language version yet. Write it here and save to add the translation.', ['language' => $languages->name($language)]) }}</div>
    @endif

    <div class="editor-grid">
        <div class="editor-paper">
            <textarea class="editor-title" rows="1" wire:model.blur="title" placeholder="{{ __('Title') }}"
                x-init="$el.style.height = $el.scrollHeight + 'px'" @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"></textarea>
            @error('title')<p class="error" style="color:var(--danger);margin:-10px 0 16px">{{ $message }}</p>@enderror

            <div wire:ignore
                 x-data="{
                    api: null,
                    init() {
                        const start = () => {
                            this.api = window.WakeEditor.create(this.$refs.editor, {
                                markdown: @js($body),
                                media: @js($mediaMap),
                                labels: @js($labels),
                                placeholder: @js(__('Start writing, or type / for blocks …')),
                                onChange: Alpine.debounce(md => $wire.set('body', md, false), 400),
                                onUpload: file => new Promise(resolve => $wire.upload('upload', file, () => $wire.storeUpload().then(resolve), () => resolve(null))),
                            })
                        }
                        window.WakeEditor ? start() : document.addEventListener('wake-editor-ready', start, { once: true })
                    },
                 }"
                 x-on:wake-editor-load.window="api && api.load($event.detail.markdown)">
                <div class="editor-toolbar" role="toolbar" aria-label="{{ __('Formatting') }}">
                    <button type="button" @click="api.command('bold')" title="{{ __('Bold') }}"><b>B</b></button>
                    <button type="button" @click="api.command('italic')" title="{{ __('Italic') }}"><i>I</i></button>
                    <button type="button" @click="api.command('h2')" title="{{ __('Heading') }}">H2</button>
                    <button type="button" @click="api.command('quote')" title="{{ __('Quote') }}">“</button>
                    <button type="button" @click="api.command('ul')" title="{{ __('List') }}">•</button>
                    <button type="button" @click="api.command('link')" title="{{ __('Link') }}">🔗</button>
                </div>
                <div x-ref="editor"></div>
            </div>
        </div>

        <aside class="side">
            <div class="segmented" role="tablist">
                <button type="button" role="tab" wire:click="$set('tab', 'entry')" @class(['on' => $tab === 'entry'])>{{ __('Entry') }}</button>
                <button type="button" role="tab" wire:click="$set('tab', 'stats')" @class(['on' => $tab === 'stats']) @disabled(! $entry)>{{ __('Statistics') }}</button>
                <button type="button" role="tab" wire:click="$set('tab', 'history')" @class(['on' => $tab === 'history']) @disabled(! $entry)>{{ __('Versions') }}</button>
            </div>

            @if ($tab === 'history')
                <div class="card" style="gap:10px">
                    <h2 class="h2">{{ __('Earlier versions') }}</h2>
                    @forelse ($revisions as $revision)
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px" wire:key="rev-{{ $revision['id'] }}">
                            <span class="mono">{{ $revision['time']->locale(app()->getLocale())->isoFormat('D. MMM, H:mm') }}</span>
                            <button type="button" class="btn btn-small" wire:click="restore('{{ $revision['id'] }}')" wire:confirm="{{ __('Put this version back? The current text is kept as a revision.') }}">{{ __('Restore') }}</button>
                        </div>
                    @empty
                        <p class="muted" style="margin:0">{{ __('No earlier versions yet. Wake keeps one each time you save.') }}</p>
                    @endforelse
                </div>
                <div class="card" style="gap:8px">
                    <h2 class="h2">{{ __('Changes') }}</h2>
                    @forelse ($history as $item)
                        <div style="font-size:13px"><b>{{ __('wake::history.'.$item['action']) }}</b>@if (! empty($item['language'])) <span class="chip current">{{ $item['language'] }}</span>@endif
                            <span class="muted">· {{ $item['account'] ?? '' }} · {{ \Carbon\CarbonImmutable::parse($item['time'])->locale(app()->getLocale())->diffForHumans() }}</span>
                            @if (($item['via'] ?? '') === 'mcp')<span class="badge mcp">MCP</span>@endif</div>
                    @empty
                        <p class="muted" style="margin:0">{{ __('Nothing changed yet.') }}</p>
                    @endforelse
                </div>
            @elseif ($tab === 'stats' && $stats)
                <div class="grid grid-2" style="gap:10px">
                    <div class="card" style="padding:16px;gap:6px"><div class="kpi-label">{{ __('Visitors') }}</div><div class="kpi-value" style="font-size:26px">{{ $stats['page']['visitors'] }}</div></div>
                    <div class="card" style="padding:16px;gap:6px"><div class="kpi-label">{{ __('Page views') }}</div><div class="kpi-value" style="font-size:26px">{{ $stats['page']['pageviews'] }}</div></div>
                    <div class="card" style="padding:16px;gap:6px"><div class="kpi-label">{{ __('Reading time') }}</div><div class="kpi-value" style="font-size:22px">{{ $duration($stats['page']['duration']) }}</div></div>
                    <div class="card" style="padding:16px;gap:6px"><div class="kpi-label">{{ __('Read to the end') }}</div><div class="kpi-value" style="font-size:26px">{{ $stats['page']['read_rate'] === null ? '–' : round($stats['page']['read_rate'] * 100).' %' }}</div></div>
                </div>
                <div class="night" style="padding:18px">
                    <h2 class="h2" style="margin-bottom:12px">{{ __('Visitors per day') }}</h2>
                    <x-wake::chart.bars :series="$stats['series']" :height="110" />
                </div>
                <div class="card"><h2 class="h2">{{ __('Sources') }}</h2><x-wake::top-list :items="array_slice($stats['page']['sources'], 0, 5, true)" /></div>
                <a class="btn" href="{{ route('wake.stats.journey', ['path' => $stats['path']]) }}"><x-wake::icon name="wake" />{{ __('See the wake') }}</a>
            @elseif ($tab === 'stats')
                <p class="muted">{{ __('Statistics appear once this translation is saved.') }}</p>
            @else
                @if ($mayPublish && ! $isPublished)
                    <div class="card" style="gap:10px">
                        <h2 class="h2">{{ __('Publish') }}</h2>
                        <div class="field"><label for="schedule">{{ __('Later, at') }}</label><input id="schedule" type="datetime-local" class="input" wire:model.live="scheduleAt"></div>
                        <p class="muted" style="margin:0;font-size:13px">{{ __('Leave empty to publish right away.') }}</p>
                    </div>
                @endif

                <div class="card" style="gap:14px">
                    <h2 class="h2">{{ __('Details') }}</h2>
                    <div class="field"><label for="slug">{{ __('Address') }}</label><input id="slug" class="input mono" wire:model.blur="slug" placeholder="{{ \Illuminate\Support\Str::slug($title ?: 'my-post') }}">
                        @error('slug')<span class="error">{{ __('Only letters, numbers and dashes.') }}</span>@enderror</div>
                    @if ($type === 'post')
                        <div class="field"><label for="tags">{{ __('Tags') }}</label><input id="tags" class="input" wire:model.blur="tags" placeholder="{{ __('travel, ruhr') }}"></div>
                    @endif
                    @if ($type === 'page' && ! $id)
                        <div class="field"><label for="parent">{{ __('Below page') }}</label>
                            <select id="parent" class="select" wire:model="parent"><option value="">{{ __('Top level') }}</option>@foreach ($pages as $p)<option value="{{ $p->id() }}">{{ $p->title() }}</option>@endforeach</select></div>
                    @endif
                    <div class="field"><label for="excerpt">{{ __('Excerpt') }}</label><textarea id="excerpt" class="textarea" style="min-height:80px" wire:model.blur="excerpt" placeholder="{{ __('Short summary for lists and search engines') }}"></textarea></div>
                    @foreach ($blueprint as $key => $field)
                        @continue(in_array($key, \Wake\Livewire\EntryEditor::BASE_FIELDS, true))
                        @php($fieldType = $field['type'] ?? 'text')
                        <div class="field">
                            <label for="f-{{ $key }}">{{ $field['label'] ?? \Illuminate\Support\Str::headline($key) }}</label>
                            @if ($fieldType === 'textarea' || $fieldType === 'markdown')
                                <textarea id="f-{{ $key }}" class="textarea" wire:model.blur="custom.{{ $key }}"></textarea>
                            @elseif ($fieldType === 'toggle')
                                <label class="check"><input id="f-{{ $key }}" type="checkbox" wire:model="custom.{{ $key }}"> {{ __('Yes') }}</label>
                            @elseif ($fieldType === 'select')
                                <select id="f-{{ $key }}" class="select" wire:model="custom.{{ $key }}"><option value=""></option>@foreach ((array) ($field['options'] ?? []) as $value => $text)<option value="{{ is_int($value) ? $text : $value }}">{{ $text }}</option>@endforeach</select>
                            @else
                                <input id="f-{{ $key }}" class="input" wire:model.blur="custom.{{ $key }}"
                                       type="{{ ['number' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', 'url' => 'url'][$fieldType] ?? 'text' }}">
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($entry && $type !== 'tag')
                    <div class="card" style="gap:12px">
                        <div class="card-head"><h2 class="h2">{{ __('Files') }}</h2><span class="muted" style="font-size:12px">{{ __('click an image to make it the cover') }}</span></div>
                        <div class="media-grid">
                            @foreach ($media as $file)
                                <button type="button" wire:click="setCover('{{ $file->name() }}')" @class(['is-cover' => $cover === $file->name()]) title="{{ $file->name() }}">
                                    @if ($file->isImage())<img src="{{ $file->url(320) }}" alt="" loading="lazy">@else<span>{{ $file->name() }}</span>@endif
                                </button>
                            @endforeach
                        </div>
                        <label class="btn btn-small" style="cursor:pointer"><x-wake::icon name="upload" />{{ __('Upload') }}<input type="file" wire:model="upload" x-on:change="$nextTick(() => setTimeout(() => $wire.storeUpload(), 400))" hidden></label>
                        @error('upload')<span class="error">{{ $message }}</span>@enderror
                    </div>
                @endif

                <details class="card" style="gap:12px">
                    <summary class="h2" style="cursor:pointer">{{ __('Search engines') }}</summary>
                    <div class="field"><label for="seo-title">{{ __('Title for search engines') }}</label><input id="seo-title" class="input" wire:model.blur="seoTitle" placeholder="{{ $title }}"></div>
                    <div class="field"><label for="seo-desc">{{ __('Description') }}</label><textarea id="seo-desc" class="textarea" style="min-height:80px" wire:model.blur="seoDescription"></textarea></div>
                    <div class="field"><label for="template">{{ __('Template') }}</label><input id="template" class="input mono" wire:model.blur="template" placeholder="{{ $type }}"></div>
                </details>

                @if ($entry)
                    <div class="actions">
                        @if ($isPublished && $mayPublish)
                            <button type="button" class="btn btn-small" wire:click="unpublish">{{ __('Unpublish') }}</button>
                        @endif
                        <button type="button" class="btn btn-small btn-danger" wire:click="trash" wire:confirm="{{ __('Move this entry with all languages and files to the trash?') }}"><x-wake::icon name="trash" />{{ __('Delete') }}</button>
                    </div>
                @endif
            @endif
        </aside>
    </div>
</div>

@push('scripts')
    <script src="{{ app(\Wake\Panel\Panel::class)->asset('editor.js') }}" defer onload="document.dispatchEvent(new Event('wake-editor-ready'))"></script>
@endpush
