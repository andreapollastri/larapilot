<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * Writes the diagram of DatabaseDiagramService as a PDF: one page as large
 * as the drawing, in vectors, with the boxes and the lines where the page
 * of the dashboard puts them. The file is written by hand — a page, a
 * stream of drawing operators, two of the fonts every reader has — so the
 * package needs no PDF library.
 */
class DatabaseDiagramPdfWriter
{
    /** A pixel of the drawing in points: the size it has on screen. */
    protected const POINTS = 0.75;

    /** The longest side a PDF page may have, in points. */
    protected const LIMIT = 14400;

    /** Room above the drawing for the line that says what it is. */
    protected const BAND = 44;

    /** How far a Courier glyph advances, for a font of size 1. */
    protected const ADVANCE = 0.6;

    /** The same places as the page: the padding of a box, and where a column name starts. */
    protected const PAD = 12;

    protected const NAME = 38;

    protected const RADIUS = 8;

    protected const TEXT = '17212b';

    protected const TEXT_2 = '3b4957';

    protected const MUTED = '586776';

    protected const ACCENT = '2e6f8e';

    protected const BORDER = 'cdd5dc';

    protected const HEAD = 'edf1f4';

    protected const WHITE = 'ffffff';

    /**
     * @param  array{width: int, height: int, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, labels: list<array{x: int, y: int, text: string}>}  $diagram
     */
    public function write(array $diagram, string $title, string $note = ''): string
    {
        $width = max(1, (int) $diagram['width']);
        $height = max(1, (int) $diagram['height']) + self::BAND;
        $scale = min(self::POINTS, self::LIMIT / max($width, $height));

        // Drawn as the page draws it: pixels, with y going down.
        $ops = [
            'q '.$this->n($scale).' 0 0 '.$this->n(-$scale).' 0 '.$this->n($height * $scale).' cm',
            '1 J 1 j',
            $this->text($title, 24, 28, 13, true, self::TEXT),
        ];

        if ($note !== '') {
            $ops[] = $this->text($note, 24 + (mb_strlen($title) + 2) * 13 * self::ADVANCE, 28, 11, false, self::MUTED);
        }

        $ops[] = 'q 1 0 0 1 0 '.self::BAND.' cm';
        $ops[] = $this->color(self::MUTED).' RG '.$this->color(self::MUTED).' rg 1.4 w';

        // The lines first, so a box covers the ones that pass behind it.
        foreach ($diagram['edges'] as $edge) {
            [$x1, $y1, $c1, $c2, $x2, $y2] = $edge['points'];
            $back = $x2 >= $c2 ? -9 : 9;

            $ops[] = $this->n($x1).' '.$this->n($y1).' m '.$this->n($c1).' '.$this->n($y1).' '.$this->n($c2).' '.$this->n($y2).' '.$this->n($x2).' '.$this->n($y2).' c S';
            $ops[] = $this->n($x2).' '.$this->n($y2).' m '.$this->n($x2 + $back).' '.$this->n($y2 - 3.6).' l '.$this->n($x2 + $back).' '.$this->n($y2 + 3.6).' l h f';
        }

        foreach ($diagram['labels'] as $label) {
            $ops[] = $this->text(mb_strtoupper($label['text']), $label['x'], $label['y'], 9.5, true, self::MUTED);
        }

        foreach ($diagram['nodes'] as $node) {
            array_push($ops, ...$this->box($node));
        }

        // Where a line leaves its column, above the boxes.
        $ops[] = $this->color(self::MUTED).' RG '.$this->color(self::WHITE).' rg 1.4 w';

        foreach ($diagram['edges'] as $edge) {
            $ops[] = $this->circle($edge['points'][0], $edge['points'][1], 3).' B';
        }

        $ops[] = 'Q Q';

        return $this->document(implode("\n", $ops), $width * $scale, $height * $scale, $title);
    }

    /**
     * One table: its box, its head, its name, and a line for each column.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    protected function box(array $node): array
    {
        [$x, $y, $w, $h, $head] = [$node['x'], $node['y'], $node['w'], $node['h'], $node['head']];
        $right = $x + $w;
        $r = self::RADIUS;
        $k = $r * 0.5523;
        $view = $node['kind'] === 'view';

        $ops = [
            $this->color(self::BORDER).' RG 1 w'.($view ? ' [5 4] 0 d' : ''),
            $this->color(self::WHITE).' rg',
            implode(' ', [
                $this->n($x + $r), $this->n($y), 'm',
                $this->n($right - $r), $this->n($y), 'l',
                $this->n($right - $r + $k), $this->n($y), $this->n($right), $this->n($y + $r - $k), $this->n($right), $this->n($y + $r), 'c',
                $this->n($right), $this->n($y + $h - $r), 'l',
                $this->n($right), $this->n($y + $h - $r + $k), $this->n($right - $r + $k), $this->n($y + $h), $this->n($right - $r), $this->n($y + $h), 'c',
                $this->n($x + $r), $this->n($y + $h), 'l',
                $this->n($x + $r - $k), $this->n($y + $h), $this->n($x), $this->n($y + $h - $r + $k), $this->n($x), $this->n($y + $h - $r), 'c',
                $this->n($x), $this->n($y + $r), 'l',
                $this->n($x), $this->n($y + $r - $k), $this->n($x + $r - $k), $this->n($y), $this->n($x + $r), $this->n($y), 'c',
                'h B',
            ]),
            $this->color(self::HEAD).' rg',
            implode(' ', [
                $this->n($x), $this->n($y + $head), 'm',
                $this->n($x), $this->n($y + $r), 'l',
                $this->n($x), $this->n($y + $r - $k), $this->n($x + $r - $k), $this->n($y), $this->n($x + $r), $this->n($y), 'c',
                $this->n($right - $r), $this->n($y), 'l',
                $this->n($right - $r + $k), $this->n($y), $this->n($right), $this->n($y + $r - $k), $this->n($right), $this->n($y + $r), 'c',
                $this->n($right), $this->n($y + $head), 'l',
                'h B',
            ]),
        ];

        if ($view) {
            $ops[] = '[] 0 d';
            $ops[] = $this->text('VIEW', $right - self::PAD - 4 * 9.5 * self::ADVANCE, $y + $head / 2 + 3.5, 9.5, true, self::MUTED);
        }

        $ops[] = $this->text((string) $node['label'], $x + self::PAD, $y + $head / 2 + 4, 12, true, self::TEXT);

        foreach ($node['rows'] as $row) {
            if ($row['name'] === '') {
                $ops[] = $this->text((string) $row['label'], $x + self::PAD, $row['y'] + 4, 12, false, self::MUTED);

                continue;
            }

            $primary = $row['mark'] === 'PK';

            if ($row['mark'] !== '') {
                $ops[] = $this->text((string) $row['mark'], $x + self::PAD, $row['y'] + 3.5, 9.5, true, $row['foreign'] ? self::ACCENT : self::MUTED);
            }

            $ops[] = $this->text((string) $row['label'], $x + self::NAME, $row['y'] + 4, 12, $primary, $row['foreign'] ? self::ACCENT : ($primary ? self::TEXT : self::TEXT_2));
            $ops[] = $this->text((string) $row['type'], $right - self::PAD - mb_strlen((string) $row['type']) * 12 * self::ADVANCE, $row['y'] + 4, 12, false, self::MUTED);
        }

        return $ops;
    }

    /**
     * A line of text, standing on `$y`. The page is drawn upside down, so
     * the text is turned back the right way up.
     */
    protected function text(string $text, float|int $x, float|int $y, float|int $size, bool $bold, string $color): string
    {
        return 'BT /'.($bold ? 'F2' : 'F1').' '.$this->n($size).' Tf '.$this->color($color).' rg 1 0 0 -1 '.$this->n($x).' '.$this->n($y).' Tm ('.$this->escape($text).') Tj ET';
    }

    protected function circle(float|int $x, float|int $y, float|int $r): string
    {
        $k = $r * 0.5523;

        return implode(' ', [
            $this->n($x + $r), $this->n($y), 'm',
            $this->n($x + $r), $this->n($y + $k), $this->n($x + $k), $this->n($y + $r), $this->n($x), $this->n($y + $r), 'c',
            $this->n($x - $k), $this->n($y + $r), $this->n($x - $r), $this->n($y + $k), $this->n($x - $r), $this->n($y), 'c',
            $this->n($x - $r), $this->n($y - $k), $this->n($x - $k), $this->n($y - $r), $this->n($x), $this->n($y - $r), 'c',
            $this->n($x + $k), $this->n($y - $r), $this->n($x + $r), $this->n($y - $k), $this->n($x + $r), $this->n($y), 'c',
            'h',
        ]);
    }

    /**
     * The file around the drawing: the page, its two fonts, and the table
     * that says where each of them starts.
     */
    protected function document(string $drawing, float $width, float $height, string $title): string
    {
        $compressed = function_exists('gzcompress') ? gzcompress($drawing, 9) : false;
        $stream = is_string($compressed) ? $compressed : $drawing;

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$this->n($width).' '.$this->n($height).'] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream).(is_string($compressed) ? ' /Filter /FlateDecode' : '').' >>'."\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
            '<< /Title ('.$this->escape($title).') /Producer (Larapilot) /CreationDate (D:'.gmdate('YmdHis').'Z) >>',
        ];

        // A comment of four bytes above 127 tells a reader the file is binary.
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $start = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n".'<< /Size '.(count($objects) + 1).' /Root 1 0 R /Info '.count($objects).' 0 R >>'."\nstartxref\n".$start."\n%%EOF\n";
    }

    /**
     * Text as the standard fonts read it: Windows-1252, a `?` for what it
     * has no letter for, and the three characters a PDF string escapes.
     */
    protected function escape(string $text): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = (string) mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    protected function color(string $hex): string
    {
        return implode(' ', array_map(fn (string $part): string => $this->n(hexdec($part) / 255), str_split($hex, 2)));
    }

    /**
     * A number as a PDF writes it: no exponent, no trailing zeros.
     */
    protected function n(float|int $number): string
    {
        $text = rtrim(rtrim(number_format((float) $number, 3, '.', ''), '0'), '.');

        return $text === '' || $text === '-0' ? '0' : $text;
    }
}
