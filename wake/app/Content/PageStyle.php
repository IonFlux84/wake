<?php

namespace Wake\Content;

use Wake\Media\Media;

/**
 * The design of a single entry, from style.css in its folder (decision 0011).
 *
 * Everyone who may edit the text may edit it, so it is cleaned on saving and
 * again on output: no @import, no addresses outside content/media/, no
 * expression() or bindings, nothing that can close the <style> element, and
 * braces are balanced so no rule escapes the @scope Wake wraps it in.
 *
 *     url(media/posts/x/wave.svg)   becomes the address of that media file
 *     url(https://…)                becomes none
 */
class PageStyle
{
    public const MAX_BYTES = 65536;

    /** CSS functions that load something, apart from url(). */
    protected const LOADERS = ['image-set', '-webkit-image-set', 'src', 'expression'];

    public static function clean(string $css): string
    {
        $css = substr(str_replace("\0", '', $css), 0, self::MAX_BYTES);
        $css = preg_replace('#/\*.*?(\*/|$)#s', '', $css) ?? '';
        $css = str_replace('<', '\3C ', $css);
        $css = preg_replace('/@(import|charset|namespace)\b[^;{}]*;?/i', '', $css) ?? '';
        $css = preg_replace('/(?:behavior|-moz-binding)\s*:[^;{}]*;?/i', '', $css) ?? '';
        $css = preg_replace('/(?<![\w-])(?:'.implode('|', array_map('preg_quote', self::LOADERS)).')\s*\([^)]*\)?/i', 'none', $css) ?? '';
        $css = preg_replace_callback('/url\(\s*([\'"]?)(.*?)\1\s*\)/is', fn (array $m) => self::url(trim($m[2])), $css) ?? '';
        $css = preg_replace('/url\s*\(/i', 'none(', $css) ?? ''; // whatever url( the pattern above did not take

        return trim(self::balance($css));
    }

    /**
     * The cleaned style, limited to the part of the page the theme marks as
     * its content (the <main> element unless the theme says otherwise).
     */
    public static function scoped(string $css, string $root = 'main'): string
    {
        $css = self::clean($css);

        return $css === '' ? '' : '@scope ('.$root.") {\n".$css."\n}";
    }

    protected static function url(string $value): string
    {
        $value = rawurldecode($value);

        if (str_starts_with($value, 'media/') && ! str_contains($value, '..') && ($media = Media::find(substr($value, 6)))) {
            return 'url("'.$media->url().'")';
        }

        return 'none';
    }

    /**
     * Drops closing braces without an opening one and closes what is left
     * open. Braces inside strings do not count.
     */
    protected static function balance(string $css): string
    {
        $out = '';
        $depth = 0;
        $quote = null;
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($quote !== null) {
                $out .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $out .= $css[++$i];
                } elseif ($char === $quote || $char === "\n") {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                if ($depth === 0) {
                    continue;
                }

                $depth--;
            }

            $out .= $char;
        }

        if ($quote !== null) {
            $out .= $quote;
        }

        return $out.str_repeat('}', $depth);
    }
}
