<?php

declare(strict_types=1);

namespace Larapilot\Services\Errors;

use Illuminate\Support\Carbon;
use Larapilot\Services\ConfigService;

/**
 * What every driver does to what a tracker answers, so that a person is
 * never read into Larapilot: messages scrubbed, paths of the server read
 * as files of the repository, routes without what names a record.
 */
class ErrorDataHelper
{
    /**
     * Where the code of an application starts, in a path of the server.
     */
    protected const ROOTS = 'app|bootstrap|config|database|lang|modules|packages|public|resources|routes|src|storage|tests|vendor';

    public function __construct(protected ConfigService $config) {}

    /**
     * A message without the addresses and the long secrets it may quote.
     */
    public function scrub(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));
        $message = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[address]', $message);
        $message = (string) preg_replace('/\b(?:Bearer\s+)?[A-Za-z0-9_\-]{40,}\b/', '[secret]', $message);

        return mb_strlen($message) > 400 ? mb_substr($message, 0, 400).'…' : $message;
    }

    /**
     * The path of a request, without the host and without the query
     * string, where a token or an address can be.
     */
    public function path(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        // What identifies a person or a record is not needed to know the route.
        $path = (string) preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?=/|$)#i', '/{id}', $path);
        $path = (string) preg_replace('#/[A-Za-z0-9_-]{32,}(?=/|$)#', '/{token}', $path);
        $path = (string) preg_replace('#/[^/]*@[^/]*(?=/|$)#', '/{address}', $path);

        return mb_substr($path, 0, 200);
    }

    /**
     * A file of the server as a file of the repository. What comes before
     * the application in the path is where it was deployed: the longest
     * end of the path that is a file here is the file that was meant.
     */
    public function place(string $file): string
    {
        $file = trim(str_replace('\\', '/', trim($file)), '/');

        if ($file === '') {
            return '';
        }

        $segments = explode('/', $file);

        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            return basename($file);
        }

        $root = rtrim($this->config->projectRoot(), '/\\');

        foreach (array_keys($segments) as $from) {
            $end = implode('/', array_slice($segments, $from));

            if (is_file($root.'/'.$end)) {
                return $end;
            }
        }

        // Not a file of this checkout: a package that is not installed
        // here, or code that was since moved. Cut at the first folder an
        // application has.
        if (preg_match('#(?:^|/)(vendor/.+)$#', $file, $match) === 1
            || preg_match('#(?:^|/)((?:'.self::ROOTS.')/.+)$#', $file, $match) === 1) {
            return $match[1];
        }

        return basename($file);
    }

    /**
     * The first file and line named in a piece of text: a stack trace
     * (`/var/www/app/X.php(88)`, `at /var/www/app/X.php:88`) or a place
     * (`app/X.php:88`).
     *
     * @return array{0: string, 1: int|null}
     */
    public function frame(string $text): array
    {
        if (preg_match('#(?P<file>[^\s():]+\.php)[:(](?P<line>\d+)\)?#', $text, $match) === 1) {
            return [$match['file'], (int) $match['line']];
        }

        return ['', null];
    }

    /**
     * A moment as ISO 8601, from what a tracker gives: a date, seconds or
     * milliseconds since the epoch, as a number or as text. Nothing means
     * now.
     */
    public function moment(mixed $value): string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^@?\d{9,13}(?:\.\d+)?$/', trim($value)) === 1)) {
            $number = (float) ltrim(trim((string) $value), '@');
            // Thirteen digits are milliseconds; ten are seconds.
            $seconds = $number > 1e11 ? $number / 1000 : $number;

            return Carbon::createFromTimestampUTC((int) floor($seconds))->toIso8601String();
        }

        try {
            return Carbon::parse(is_string($value) && trim($value) !== '' ? $value : 'now')->toIso8601String();
        } catch (\Throwable) {
            return now()->toIso8601String();
        }
    }

    /**
     * An address reduced to its host, so the same application reads the
     * same with and without `www.`, over http and https.
     */
    public function address(string $url): string
    {
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return is_string($host) ? (string) preg_replace('/^www\./', '', strtolower($host)) : '';
    }
}
