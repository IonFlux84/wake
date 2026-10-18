<?php

namespace Wake\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use Wake\Content\FrontMatter;
use ZipArchive;

/**
 * Moves a site from the first folder layout to the current one (decision 0011):
 *
 *     site/config.php, settings.yml …   → settings/config.php, general.yml …
 *     site/themes/, site/plugins/       → themes/, plugins/
 *     storage/accounts, stats …         → data/   (revisions → data/versions)
 *     storage/cache, framework …        → temp/
 *     content/site.en.md                → content/website.md
 *     content/pages/1_about/index.en.md → content/pages/about/content.md (order: 1, menu: true)
 *     content/posts/2026-10-18-x/       → content/posts/x/ (published: 2026-10-18)
 *     content/tags/travel.en.md         → content/tags/travel/content.md
 *     site/categories/events.yml        → content/categories/events/content.md
 *     site/blueprints/event.yml         → content/events/type.yml
 *     files next to the text            → content/media/<entry id>/ (references rewritten)
 *
 * Menus, redirects and versions are pointed at the new ids. Without $apply,
 * nothing is changed and the steps are only listed.
 */
final class LayoutMigration
{
    /** @var list<string> what was done, or would be done */
    public array $steps = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<string, string> old entry id => new entry id */
    protected array $ids = [];

    /** @var array<string, string> old file below content/ => new file */
    protected array $files = [];

    /** @var list<string> folders below content/media already taken */
    protected array $mediaFolders = [];

    protected string $default = 'en';

    /** @var list<string> */
    protected array $languages = ['en'];

    protected bool $apply = false;

    public function __construct(protected string $root) {}

    /**
     * Zips content/, site/ and storage/ (without caches) as they are now, into
     * the backups folder, before anything moves. Wake's own backups only know
     * the new layout, so this one is unzipped by hand to go back.
     *
     * @return string|null the ZIP file, or null without the zip extension
     */
    public function backup(): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $folder = is_dir($this->root.'/storage') ? $this->root.'/storage/backups' : $this->root.'/data/backups';
        $file = $folder.'/before-layout-'.date('Y-m-d-His').'.zip';
        @mkdir($folder, 0775, true);

        $archive = new ZipArchive;
        $archive->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $skip = '#^storage/(cache|framework|logs|locks|updates|backups)/#';

        foreach (['content', 'site', 'storage', 'settings', 'data'] as $area) {
            if (! is_dir($this->root.'/'.$area)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root.'/'.$area, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $item) {
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($this->root) + 1));

                if ($item->isFile() && ! preg_match($skip, $relative) && ! str_starts_with($relative, 'data/backups/')) {
                    $archive->addFile($item->getPathname(), $relative);
                }
            }
        }

        $archive->close();

        return $file;
    }

    /**
     * Whether anything of the old layout is left.
     */
    public function needed(): bool
    {
        $this->steps = [];
        $this->run(false);

        return $this->steps !== [];
    }

    /**
     * @return list<string> the steps
     */
    public function run(bool $apply): array
    {
        $this->apply = $apply;
        $this->steps = $this->warnings = $this->ids = $this->files = $this->mediaFolders = [];
        $this->readLanguages();

        $this->topLevel();
        $blueprints = $this->blueprints();
        $this->website();

        foreach ($this->typeFolders($blueprints) as $folder => $layout) {
            match ($layout) {
                'files' => $this->filesType($folder),
                'tree' => $this->treeType($folder, $folder, 0),
                default => $this->foldersType($folder),
            };
        }

        $this->categories();
        $this->media();
        $this->references();

        if ($apply) {
            foreach (['site', 'storage', 'content'] as $folder) {
                $this->prune($this->root.'/'.$folder);
            }
        }

        return $this->steps;
    }

    /**
     * Removes folders the move left empty, the given one included.
     */
    protected function prune(string $dir): void
    {
        if (! is_dir($dir) || is_link($dir)) {
            return;
        }

        foreach (glob($dir.'/*', GLOB_ONLYDIR) ?: [] as $child) {
            $this->prune($child);
        }

        if ((scandir($dir) ?: []) === ['.', '..'] && $dir !== $this->root.'/content') {
            @rmdir($dir);
        }
    }

    // Top level ------------------------------------------------------------

    protected function topLevel(): void
    {
        $site = $this->root.'/site';

        foreach (['config.php' => 'config.php', 'settings.yml' => 'general.yml', 'menus.yml' => 'menus.yml',
            'redirects.yml' => 'redirects.yml', 'roles.yml' => 'roles.yml', 'consent.yml' => 'consent.yml'] as $from => $to) {
            $this->move("{$site}/{$from}", $this->root."/settings/{$to}");
        }

        foreach (['themes', 'plugins'] as $folder) {
            foreach (glob("{$site}/{$folder}/*", GLOB_ONLYDIR) ?: [] as $dir) {
                $this->move($dir, $this->root."/{$folder}/".basename($dir));
            }
        }

        $storage = $this->root.'/storage';

        foreach (['accounts', 'consent', 'history', 'stats', 'trash', 'backups'] as $folder) {
            $this->move("{$storage}/{$folder}", $this->root."/data/{$folder}");
        }

        $this->move("{$storage}/revisions", $this->root.'/data/versions');
        $this->move("{$storage}/mail.yml", $this->root.'/data/secrets/mail.yml');

        foreach (['cache', 'framework', 'logs', 'locks', 'app', 'updates'] as $folder) {
            $this->move("{$storage}/{$folder}", $this->root."/temp/{$folder}");
        }
    }

    /**
     * Blueprints become type.yml files in their content folder.
     *
     * @return array<string, array<string, mixed>> name => blueprint
     */
    protected function blueprints(): array
    {
        $found = [];

        foreach ([$this->root.'/site/blueprints', $this->root.'/settings/blueprints'] as $dir) {
            foreach (glob("{$dir}/*.yml") ?: [] as $file) {
                $name = basename($file, '.yml');
                $data = $this->yaml($file);
                $folder = ['post' => 'posts', 'page' => 'pages', 'tag' => 'tags'][$name] ?? trim((string) ($data['folder'] ?? ''), '/');

                if ($folder === '') {
                    $this->warnings[] = "Blueprint {$name} names no folder; left where it is.";

                    continue;
                }

                $found[$name] = $data + ['folder' => $folder];
                unset($data['folder']);
                $this->writeFile($this->content("{$folder}/type.yml"), Yaml::dump(['name' => $name] + $data, 5, 2), "type of {$folder}/ from blueprints/{$name}.yml");
                $this->remove($file);
            }
        }

        return $found;
    }

    /**
     * @param  array<string, array<string, mixed>>  $blueprints
     * @return array<string, string> folder => old layout
     */
    protected function typeFolders(array $blueprints): array
    {
        $folders = ['posts' => 'folders', 'pages' => 'tree', 'tags' => 'files'];

        foreach ($blueprints as $blueprint) {
            $folders[$blueprint['folder']] ??= 'folders';
        }

        return array_filter($folders, fn ($layout, $folder) => is_dir($this->content($folder)), ARRAY_FILTER_USE_BOTH);
    }

    // Content --------------------------------------------------------------

    protected function website(): void
    {
        foreach (glob($this->content('site*.md')) ?: [] as $file) {
            if (preg_match('/^site(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$/i', basename($file), $m)) {
                $this->move($file, $this->content('website'.$this->suffix($m[1] ?? '').'.md'));
            }
        }
    }

    /**
     * posts/2026-10-18-hello/index.en.md → posts/hello/content.md
     */
    protected function foldersType(string $folder): void
    {
        foreach (glob($this->content("{$folder}/*"), GLOB_ONLYDIR) ?: [] as $dir) {
            $old = basename($dir);

            if (! $this->hasOldText($dir)) {
                continue;
            }

            $date = preg_match('/^(\d{4}-\d{2}-\d{2})-(.+)$/', $old, $m) ? $m[1] : null;
            $name = $this->freeName($this->content($folder), $date ? $m[2] : $old, $old);

            $this->entry($dir, $this->content("{$folder}/{$name}"), "{$folder}/{$old}", "{$folder}/{$name}",
                fn (array $fields) => $date && empty($fields['published']) ? ['published' => $date] : []);
        }
    }

    /**
     * pages/1_about/2_team/index.en.md → pages/about/team/content.md, order: 2, menu: true
     *
     * @param  string  $disk  where the parent is on disk right now
     * @param  string  $old  the parent's id in the old layout
     */
    protected function treeType(string $folder, string $disk, int $depth, ?string $old = null): void
    {
        $old ??= $disk;

        foreach (glob($this->content("{$disk}/*"), GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);

            if (! $this->hasText($dir)) {
                continue;
            }

            $number = preg_match('/^(\d+)_(.+)$/', $name, $m) ? (int) $m[1] : null;
            $new = $this->freeName(dirname($dir), $number !== null ? $m[2] : $name, $name);
            $oldId = "{$old}/{$name}";
            $newId = $this->newId("{$old}/{$name}", $new);

            if ($this->hasOldText($dir) || $number !== null) {
                $this->entry($dir, dirname($dir)."/{$new}", $oldId, $newId,
                    fn () => $number !== null ? ['order' => $number, 'menu' => true] : []);
            }

            $this->treeType($folder, $this->apply ? $this->relative(dirname($dir)."/{$new}") : "{$disk}/{$name}", $depth + 1, $oldId);
        }
    }

    /**
     * tags/travel.en.md → tags/travel/content.md
     */
    protected function filesType(string $folder): void
    {
        foreach (glob($this->content("{$folder}/*.md")) ?: [] as $file) {
            if (! preg_match('/^(.+?)(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$/i', basename($file), $m) || ! in_array(strtolower($m[2] ?? $this->default), $this->languages, true) && isset($m[2])) {
                continue;
            }

            $target = $this->content("{$folder}/{$m[1]}/content".$this->suffix($m[2] ?? '').'.md');
            $this->move($file, $target);
            $this->files["{$folder}/".basename($file)] = "{$folder}/{$m[1]}/".basename($target);
            $this->ids["{$folder}/{$m[1]}"] = "{$folder}/{$m[1]}";
        }
    }

    /**
     * Moves one entry folder and renames its text files.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $extra  fields to add to the default language
     */
    protected function entry(string $from, string $to, string $oldId, string $newId, callable $extra): void
    {
        $this->ids[$oldId] = $newId;

        foreach (glob("{$from}/index*.md") ?: [] as $file) {
            if (! preg_match('/^index(?:\.([a-z]{2}(?:-[a-z]{2})?))?\.md$/i', basename($file), $m)) {
                continue;
            }

            $suffix = $this->suffix($m[1] ?? '');
            $target = "{$from}/content{$suffix}.md";

            if ($suffix === '' && is_file($target) && $target !== $file) {
                $this->warnings[] = "{$oldId}: both index.md and index.{$this->default}.md exist; kept the first.";

                continue;
            }

            if ($suffix === '' && ($add = $extra($this->fields($file))) !== []) {
                $this->rewriteFields($file, $add);
            }

            $this->move($file, $target);
            $this->files[$oldId.'/'.basename($file)] = $newId.'/content'.$suffix.'.md';
        }

        if ($from !== $to) {
            $this->move($from, $to);
        }
    }

    /**
     * site/categories/events.yml → content/categories/events/content.md (+ content.<lang>.md)
     */
    protected function categories(): void
    {
        foreach ([$this->root.'/site/categories', $this->root.'/settings/categories'] as $dir) {
            foreach (glob("{$dir}/*.yml") ?: [] as $file) {
                $key = basename($file, '.yml');
                $data = $this->yaml($file);
                $byLanguage = [];

                foreach (['title', 'slug', 'description'] as $field) {
                    if (is_array($data[$field] ?? null)) {
                        foreach ($data[$field] as $code => $value) {
                            $byLanguage[$code][$field] = $value;
                        }
                        $data[$field] = $data[$field][$this->default] ?? reset($data[$field]);
                    }
                }

                $this->writeFile($this->content("categories/{$key}/content.md"), FrontMatter::dump($data, ''), "category {$key} from {$key}.yml");

                foreach ($byLanguage as $code => $fields) {
                    if ($code !== $this->default && in_array($code, $this->languages, true)) {
                        $this->writeFile($this->content("categories/{$key}/content.{$code}.md"), FrontMatter::dump($fields, ''), "category {$key} in {$code}");
                    }
                }

                $this->remove($file);
            }
        }
    }

    /**
     * Files next to the text move to content/media/<entry>/; references in
     * the text and in cover/thumbnail follow.
     */
    protected function media(): void
    {
        foreach ($this->ids as $oldId => $newId) {
            $dir = $this->apply ? $this->content($newId) : $this->content($oldId);
            $name = $newId;
            $moved = [];

            foreach (glob("{$dir}/*") ?: [] as $file) {
                $base = basename($file);

                if (is_dir($file) || preg_match('/^(content|index)(\.[a-z-]+)?\.md$/', $base) || $base === 'style.css' || str_ends_with($base, '.yml')) {
                    continue;
                }

                $target = $this->content("media/{$name}/{$base}");
                $this->move($file, $target);
                $this->move("{$file}.yml", "{$target}.yml");
                $moved[$base] = "{$name}/{$base}";
            }

            if ($moved !== []) {
                $this->mediaFolders[] = $name;
            }

            if ($moved !== [] && $this->apply) {
                foreach (glob("{$dir}/content*.md") ?: [] as $text) {
                    $this->pointToMedia($text, $moved);
                }
            }
        }
    }

    /**
     * Menus, redirects and versions follow the new ids and files.
     */
    protected function references(): void
    {
        $renamed = array_filter($this->ids, fn ($new, $old) => $new !== $old, ARRAY_FILTER_USE_BOTH);

        if ($renamed !== []) {
            $menus = $this->root.'/settings/menus.yml';
            $data = is_file($menus) || ! $this->apply ? $this->yaml($menus) : [];
            $walk = function (array $items) use (&$walk, $renamed): array {
                foreach ($items as $i => $item) {
                    if (is_array($item) && isset($item['page'], $renamed[$item['page']])) {
                        $items[$i]['page'] = $renamed[$item['page']];
                    }
                    if (is_array($item['children'] ?? null)) {
                        $items[$i]['children'] = $walk($item['children']);
                    }
                }

                return $items;
            };
            $updated = array_map(fn ($menu) => is_array($menu) ? $walk($menu) : $menu, $data);

            if ($updated !== $data) {
                $this->writeFile($menus, Yaml::dump($updated, 6, 2), 'menus point to the new ids');
            }

            $redirects = $this->root.'/settings/redirects.yml';
            $data = $this->yaml($redirects);
            $updated = array_map(fn ($paths) => is_array($paths) ? array_map(fn ($target) => $renamed[$target] ?? $target, $paths) : $paths, $data);

            if ($updated !== $data) {
                $this->writeFile($redirects, Yaml::dump($updated, 3, 2), 'redirects point to the new ids');
            }
        }

        foreach ($this->files as $old => $new) {
            $this->move($this->root.'/data/versions/'.md5($old), $this->root.'/data/versions/'.md5($new));
        }
    }

    // Helpers --------------------------------------------------------------

    protected function readLanguages(): void
    {
        foreach ([$this->root.'/site/config.php', $this->root.'/settings/config.php'] as $file) {
            if (is_file($file)) {
                try {
                    $config = require $file;
                } catch (Throwable) {
                    $config = [];
                }

                if (is_array($config['languages'] ?? null) && $config['languages'] !== []) {
                    $this->languages = array_map('strtolower', array_keys($config['languages']));
                    $this->default = $this->languages[0];

                    return;
                }
            }
        }

        $this->languages = array_keys((array) config('wake.languages', ['en' => 'English']));
        $this->default = $this->languages[0] ?? 'en';
    }

    /**
     * "" or the default language → "", other languages → ".de".
     */
    protected function suffix(string $code): string
    {
        $code = strtolower($code);

        return $code === '' || $code === $this->default ? '' : ".{$code}";
    }

    protected function hasOldText(string $dir): bool
    {
        return (glob("{$dir}/index*.md") ?: []) !== [];
    }

    protected function hasText(string $dir): bool
    {
        return $this->hasOldText($dir) || (glob("{$dir}/content*.md") ?: []) !== [] || (glob("{$dir}/*", GLOB_ONLYDIR) ?: []) !== [];
    }

    protected function freeName(string $parent, string $name, string $current): string
    {
        $candidate = $name;

        for ($i = 2; $candidate !== $current && file_exists("{$parent}/{$candidate}"); $i++) {
            $candidate = "{$name}-{$i}";
        }

        return $candidate;
    }

    /**
     * The new id of an old one: parents without their number, then the new name.
     */
    protected function newId(string $oldId, string $name): string
    {
        $parent = dirname($oldId);

        return ($this->ids[$parent] ?? $parent).'/'.$name;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fields(string $file): array
    {
        try {
            return FrontMatter::parse((string) file_get_contents($file), $file)[0];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $add
     */
    protected function rewriteFields(string $file, array $add): void
    {
        $this->steps[] = 'set '.http_build_query($add, '', ', ').' in '.$this->relative($file);

        if ($this->apply) {
            [$fields, $body] = FrontMatter::parse((string) file_get_contents($file), $file);
            Files::write($file, FrontMatter::dump(array_merge($fields, $add), $body));
        }
    }

    /**
     * @param  array<string, string>  $moved  file name => path below media/
     */
    protected function pointToMedia(string $text, array $moved): void
    {
        [$fields, $body] = FrontMatter::parse((string) file_get_contents($text), $text);

        foreach (['cover', 'thumbnail'] as $key) {
            if (is_string($fields[$key] ?? null) && isset($moved[$fields[$key]])) {
                $fields[$key] = 'media/'.$moved[$fields[$key]];
            }
        }

        $body = preg_replace_callback('/(!?\[[^\]]*\]\()([^)\s]+)(\))/', function ($m) use ($moved) {
            $name = rawurldecode($m[2]);

            return isset($moved[$name]) ? $m[1].'media/'.$moved[$name].$m[3] : $m[0];
        }, $body);

        Files::write($text, FrontMatter::dump($fields, $body));
        $this->steps[] = 'point '.$this->relative($text).' to content/media/';
    }

    protected function move(string $from, string $to): void
    {
        if (! file_exists($from) || $from === $to) {
            return;
        }

        if (file_exists($to)) {
            $this->warnings[] = 'Not moved, the target exists: '.$this->short($to);

            return;
        }

        $this->steps[] = 'move '.$this->short($from).' → '.$this->short($to);

        if ($this->apply) {
            @mkdir(dirname($to), 0775, true);
            rename($from, $to);
        }
    }

    protected function writeFile(string $file, string $contents, string $what): void
    {
        $this->steps[] = 'write '.$this->short($file)." ({$what})";

        if ($this->apply) {
            Files::write($file, $contents);
        }
    }

    protected function remove(string $file): void
    {
        $this->steps[] = 'remove '.$this->short($file);

        if ($this->apply) {
            @unlink($file);
            @rmdir(dirname($file));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function yaml(string $file): array
    {
        try {
            return is_file($file) ? (Yaml::parseFile($file) ?: []) : [];
        } catch (Throwable) {
            $this->warnings[] = 'Could not read '.$this->short($file);

            return [];
        }
    }

    protected function content(string $path = ''): string
    {
        return $this->root.'/content'.($path === '' ? '' : '/'.$path);
    }

    protected function relative(string $file): string
    {
        return ltrim(substr($file, strlen($this->content())), '/');
    }

    protected function short(string $path): string
    {
        return ltrim(substr($path, strlen($this->root)), '/');
    }
}
