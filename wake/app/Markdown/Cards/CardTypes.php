<?php

namespace Wake\Markdown\Cards;

use Symfony\Component\Yaml\Yaml;
use Throwable;
use Wake\Plugins\Plugins;
use Wake\Support\Paths;

/**
 * The cards the website knows, from their descriptions next to their views:
 * Wake's resources/views/cards/{name}.yml, then each running plugin's
 * views/cards/{name}.yml, then the active theme's templates/cards/{name}.yml.
 * A later one replaces an earlier one with the same name.
 */
class CardTypes
{
    /** @var array<string, CardType>|null */
    protected ?array $cards = null;

    /** @var array<string, string> file => what is wrong with it */
    protected array $errors = [];

    /**
     * @return array<string, CardType> by name, sorted by title
     */
    public function all(): array
    {
        if ($this->cards !== null) {
            return $this->cards;
        }

        $folders = ['wake' => resource_path('views/cards')];
        $plugins = app(Plugins::class);

        foreach ($plugins->all() as $folder => $info) {
            if ($plugins->isRunning($folder)) {
                $folders['plugin:'.$folder] = $info->path.'/views/cards';
            }
        }

        $folders['theme'] = Paths::theme('templates/cards');
        $cards = [];

        foreach ($folders as $source => $folder) {
            foreach (glob($folder.'/*.yml') ?: [] as $file) {
                $name = basename($file, '.yml');

                if (! preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
                    continue;
                }

                try {
                    $data = Yaml::parseFile($file);
                } catch (Throwable $e) {
                    $this->errors[substr($file, strlen(Paths::root()) + 1)] = $e->getMessage();

                    continue;
                }

                $cards[$name] = new CardType($name, $source, is_array($data) ? $data : []);
            }
        }

        uasort($cards, fn (CardType $a, CardType $b) => strcasecmp($a->title(), $b->title()));

        return $this->cards = $cards;
    }

    /**
     * Descriptions that could not be read, so a theme author sees why a card
     * has no form.
     *
     * @return array<string, string> file => error
     */
    public function errors(): array
    {
        $this->all();

        return $this->errors;
    }

    public function get(string $name): ?CardType
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(?string $language = null): array
    {
        return array_values(array_map(fn (CardType $card) => $card->toArray($language), $this->all()));
    }

    public function forget(): void
    {
        $this->cards = null;
        $this->errors = [];
    }
}
