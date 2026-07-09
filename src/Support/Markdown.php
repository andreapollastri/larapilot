<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class Markdown
{
    public static function toHtml(string $markdown): string
    {
        if (class_exists(CommonMarkConverter::class)) {
            try {
                $converter = new CommonMarkConverter([
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]);

                return self::withHeadingIds((string) $converter->convert($markdown));
            } catch (\Throwable) {
                // fall through to the dependency-free renderer
            }
        }

        return self::basicToHtml($markdown);
    }

    /**
     * @return array<int, array{id: string, level: int, title: string}>
     */
    public static function headings(string $markdown): array
    {
        $headings = [];
        $inFence = false;

        foreach (preg_split('/\r?\n/', $markdown) as $line) {
            if (preg_match('/^\s*(?:```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if ($inFence || preg_match('/^(#{2,3})\s+(.+)$/', $line, $matches) !== 1) {
                continue;
            }

            $title = trim($matches[2]);

            $headings[] = [
                'level' => strlen($matches[1]),
                'title' => $title,
                'id' => self::slug($title),
            ];
        }

        return $headings;
    }

    /**
     * CommonMark emits headings without ids; inject slug ids (matching
     * headings()) so table-of-contents anchors resolve.
     */
    protected static function withHeadingIds(string $html): string
    {
        return preg_replace_callback(
            '/<h([1-6])>(.*?)<\/h\1>/s',
            function (array $matches): string {
                $id = self::slug(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5));

                return $id === ''
                    ? $matches[0]
                    : "<h{$matches[1]} id=\"{$id}\">{$matches[2]}</h{$matches[1]}>";
            },
            $html
        ) ?? $html;
    }

    protected static function basicToHtml(string $markdown): string
    {
        $lines = preg_split('/\r?\n/', $markdown) ?: [];
        $html = [];
        $inList = false;
        $inParagraph = false;
        $inCode = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^(?:```|~~~)/', $trimmed) === 1) {
                if ($inCode) {
                    $html[] = '</code></pre>';
                    $inCode = false;

                    continue;
                }

                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }

                if ($inParagraph) {
                    $html[] = '</p>';
                    $inParagraph = false;
                }

                $html[] = '<pre><code>';
                $inCode = true;

                continue;
            }

            if ($inCode) {
                $html[] = e($line);

                continue;
            }

            if ($trimmed === '') {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }

                if ($inParagraph) {
                    $html[] = '</p>';
                    $inParagraph = false;
                }

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches) === 1) {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }

                if ($inParagraph) {
                    $html[] = '</p>';
                    $inParagraph = false;
                }

                $level = strlen($matches[1]);
                $title = self::escapeAndInline($matches[2]);
                $id = self::slug($matches[2]);
                $html[] = "<h{$level} id=\"{$id}\">{$title}</h{$level}>";

                continue;
            }

            if (preg_match('/^[-*]\s+\[( |x|X)\]\s+(.+)$/', $trimmed, $matches) === 1) {
                if (! $inList) {
                    $html[] = '<ul class="checklist">';
                    $inList = true;
                }

                $checked = strtolower($matches[1]) === 'x' ? ' checked' : '';
                $label = self::escapeAndInline($matches[2]);
                $html[] = "<li><label><input type=\"checkbox\" disabled{$checked}> {$label}</label></li>";

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches) === 1) {
                if (! $inList) {
                    $html[] = '<ul>';
                    $inList = true;
                }

                $html[] = '<li>'.self::escapeAndInline($matches[1]).'</li>';

                continue;
            }

            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }

            if (! $inParagraph) {
                $html[] = '<p>';
                $inParagraph = true;
            } else {
                $html[] = '<br>';
            }

            $html[] = self::escapeAndInline($trimmed);
        }

        if ($inCode) {
            $html[] = '</code></pre>';
        }

        if ($inList) {
            $html[] = '</ul>';
        }

        if ($inParagraph) {
            $html[] = '</p>';
        }

        return implode("\n", $html);
    }

    protected static function escapeAndInline(string $text): string
    {
        return self::inline(e($text));
    }

    protected static function inline(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;

        return $text;
    }

    protected static function slug(string $text): string
    {
        return Str::slug($text);
    }
}
