<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportedLivestreamUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $scheme = strtolower((string) parse_url((string) $value, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));

        if ($scheme !== 'https' || ! $this->supportedHost($host)) {
            $fail('Use an HTTPS YouTube or Facebook video URL.');

            return;
        }

        if ($this->isYouTube($host) && $this->youtubeVideoId((string) $value) === null) {
            $fail('Enter a valid YouTube watch, live, short, or embed URL.');
        }
    }

    public static function provider(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === 'youtu.be' || $host === 'www.youtu.be' || in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            return 'youtube';
        }

        if ($host === 'facebook.com' || $host === 'fb.watch' || str_ends_with($host, '.facebook.com')) {
            return 'facebook';
        }

        return null;
    }

    public static function youtubeVideoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! in_array($host, ['youtu.be', 'www.youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $candidate = explode('/', $path)[0] ?? '';
        } else {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $segments = explode('/', $path);
            $candidate = (string) ($query['v'] ?? (in_array($segments[0] ?? '', ['live', 'embed', 'shorts'], true) ? ($segments[1] ?? '') : ''));
        }

        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $candidate) === 1 ? $candidate : null;
    }

    private function supportedHost(string $host): bool
    {
        return self::provider('https://'.$host) !== null;
    }

    private function isYouTube(string $host): bool
    {
        return in_array($host, ['youtu.be', 'www.youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com'], true);
    }
}
