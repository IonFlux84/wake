<?php

namespace Wake\Stats;

use Wake\Support\Files;
use Wake\Support\Paths;

/**
 * public/hit.php runs without Laravel, so Wake writes the rules it needs
 * into data/stats/.config.php whenever they change.
 */
class TrackingConfig
{
    /** Crawlers, monitors and preview fetchers that never count as visitors. */
    public const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|embedly|quora link preview|showyoubot|outbrain|pinterest|vkshare|w3c_validator|whatsapp|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python-requests|go-http-client|java\/|httpclient|axios|node-fetch|gptbot|chatgpt|claudebot|anthropic|perplexity|bytespider|ccbot|amazonbot|applebot|google-extended|cohere/i';

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return [
            'enabled' => (bool) config('wake.tracking.enabled') && ! config('wake.closed'),
            'level1' => config('wake.tracking.level1') === 'consent' ? 'consent' : 'always',
            'respect_privacy_signals' => (bool) config('wake.tracking.respect_privacy_signals'),
            'session_cookie' => (string) config('session.cookie'),
            'host' => strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            'key' => hash('sha256', 'wake-visitor|'.config('app.key')),
            'timezone' => (string) config('app.timezone'),
            'geo' => (string) config('wake.tracking.geo'),
            'bots' => self::BOTS,
            'consent_categories' => ['necessary', 'statistics', 'media', 'marketing'],
        ];
    }

    public function file(): string
    {
        return Paths::data('stats/.config.php');
    }

    /**
     * Write the file if it is missing or outdated.
     */
    public function sync(): void
    {
        $values = $this->values();

        if (! is_file($this->file()) || (@include $this->file()) !== $values) {
            Files::writePhp($this->file(), $values);
        }
    }
}
