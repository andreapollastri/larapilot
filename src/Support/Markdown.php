<?php

declare(strict_types=1);

namespace Larapilot\Support;

use Illuminate\Support\Str;

class Markdown
{
    public static function toHtml(string $markdown): string
    {
        if (class_exists(\League\CommonMark\CommonMarkConverter::class)) {
            $converter = new \League\CommonMark\CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);

            return (string) $converter->convert($markdown);
        }

        try {
            return Str::markdown($markdown);
        } catch (\Throwable) {
            return self::basicToHtml($markdown);
        }
    }

    /**
     * @return array<int, array{id: string, level: int, title: string}>
     */
    public static function headings(string $markdown): array
    {
        $headings = [];

        foreach (preg_split('/\r?\n/', $markdown) as $line) {
            if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $matches) !== 1) {
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

    protected static function basicToHtml(string $markdown): string
    {
        $lines = preg_split('/\r?\n/', $markdown) ?: [];
        $html = [];
        $inList = false;
        $inParagraph = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

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
