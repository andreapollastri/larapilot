<?php

declare(strict_types=1);

namespace Larapilot\Services;

/**
 * Draws the database as a diagram: one box for each table, one line for
 * each foreign key. The places are worked out here, in pixels, so the page
 * is plain SVG and needs no library: a table stands to the right of the
 * tables it points at, and the ones with no foreign key at all sit below.
 */
class DatabaseDiagramService
{
    /** Above this many tables and views the diagram is not drawn. */
    public const LIMIT = 300;

    /** The width of one character of the monospace text, at 12px. */
    protected const CHAR = 7.3;

    protected const HEAD = 30;

    protected const ROW = 22;

    protected const PAD = 12;

    /** Room for the PK / FK mark in front of a column. */
    protected const MARK = 26;

    protected const MIN_WIDTH = 170;

    protected const MAX_WIDTH = 340;

    protected const TYPE_CHARS = 18;

    protected const GAP_X = 96;

    protected const GAP_Y = 26;

    /** A column of boxes taller than this is continued beside itself. */
    protected const COLUMN_HEIGHT = 1500;

    protected const MARGIN = 24;

    public function __construct(protected DatabaseViewerService $database) {}

    /**
     * The database of the viewer, drawn.
     *
     * @return array<string, mixed>
     */
    public function diagram(bool $keysOnly = false): array
    {
        $overview = $this->database->overview();

        if ($overview['error'] !== null || count($overview['objects']) > self::LIMIT) {
            return $overview + ['diagram' => null];
        }

        $schema = $this->database->schema();

        // One schema: the name alone, as the list shows it.
        $tables = array_map(static fn (array $table): array => $table + [
            'label' => $schema['schemas'] ? $table['key'] : $table['name'],
        ], $schema['schema']);

        return $schema + ['diagram' => $schema['error'] === null ? $this->layout($tables, $keysOnly) : null];
    }

    /**
     * Where every box and every line goes.
     *
     * @param  list<array{key: string, name: string, label?: string, schema?: string|null, kind: string, columns: list<array{name: string, type: string, primary: bool, foreign: bool}>, foreign_keys: list<array<string, mixed>>}>  $tables
     * @return array{width: int, height: int, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, labels: list<array{x: int, y: int, text: string}>, related: int}
     */
    public function layout(array $tables, bool $keysOnly = false): array
    {
        $tables = array_column($tables, null, 'key');
        ksort($tables, SORT_NATURAL | SORT_FLAG_CASE);

        $links = $this->links($tables);
        $nodes = [];

        foreach ($tables as $key => $table) {
            $nodes[$key] = $this->node($table, $links, $keysOnly);
        }

        $related = [];
        $points = [];

        foreach ($links as $link) {
            $related[$link['from']] = true;
            $related[$link['to']] = true;

            if ($link['from'] !== $link['to']) {
                $points[$link['from']][$link['to']] = true;
            }
        }

        $ranks = $this->ranks(array_keys($related), $points);
        $columns = [];

        foreach ($ranks as $key => $rank) {
            $columns[$rank][] = $key;
        }

        ksort($columns);

        $x = self::MARGIN;
        $bottom = self::MARGIN;

        foreach ($columns as $keys) {
            // A table goes as near as it can to the ones it points at.
            $near = [];

            foreach ($keys as $key) {
                $centers = [];

                foreach (array_keys($points[$key] ?? []) as $target) {
                    if (isset($nodes[$target]['y'])) {
                        $centers[] = $nodes[$target]['y'] + $nodes[$target]['h'] / 2;
                    }
                }

                $near[$key] = $centers === [] ? 0 : array_sum($centers) / count($centers);
            }

            usort($keys, static fn (string $a, string $b): int => [$near[$a], strtolower($a)] <=> [$near[$b], strtolower($b)]);

            $stack = [];
            $height = 0;

            foreach ($keys as $key) {
                if ($stack !== [] && $height + $nodes[$key]['h'] > self::COLUMN_HEIGHT) {
                    [$x, $bottom] = $this->place($nodes, $stack, $x, $bottom);
                    $stack = [];
                    $height = 0;
                }

                $stack[] = $key;
                $height += $nodes[$key]['h'] + self::GAP_Y;
            }

            [$x, $bottom] = $this->place($nodes, $stack, $x, $bottom);
        }

        $right = $x === self::MARGIN ? self::MARGIN : $x - self::GAP_X;
        $labels = [];

        // The tables no foreign key touches, in rows under the rest.
        $alone = array_values(array_diff(array_keys($nodes), array_keys($related)));

        if ($alone !== []) {
            $y = $bottom;

            if ($related !== []) {
                $y += self::GAP_Y;
                $labels[] = ['x' => self::MARGIN, 'y' => $y + 4, 'text' => 'No foreign key'];
                $y += 22;
            }

            $wrap = max($right, 1100);
            $x = self::MARGIN;
            $tallest = 0;

            foreach ($alone as $key) {
                if ($x > self::MARGIN && $x + $nodes[$key]['w'] > $wrap) {
                    $x = self::MARGIN;
                    $y += $tallest + self::GAP_Y;
                    $tallest = 0;
                }

                $nodes[$key]['x'] = $x;
                $nodes[$key]['y'] = $y;
                $x += $nodes[$key]['w'] + self::MARGIN;
                $right = max($right, $x - self::MARGIN);
                $tallest = max($tallest, $nodes[$key]['h']);
            }

            $bottom = $y + $tallest + self::GAP_Y;
        }

        foreach ($nodes as $key => $node) {
            $nodes[$key]['head'] = self::HEAD;

            foreach ($node['rows'] as $index => $row) {
                $nodes[$key]['rows'][$index]['y'] = $this->rowY($nodes[$key], $row['name'], $index);
            }
        }

        $edges = [];

        foreach ($links as $link) {
            $edge = $this->edge($nodes[$link['from']], $nodes[$link['to']], $link);
            $right = max($right, $edge['reach']);
            unset($edge['reach']);
            $edges[] = $edge;
        }

        return [
            'width' => (int) ceil($right + self::MARGIN),
            'height' => (int) ceil(max($bottom - self::GAP_Y, self::MARGIN) + self::MARGIN),
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'labels' => $labels,
            'related' => count($related),
        ];
    }

    /**
     * One line for each foreign key that points at a table of the diagram,
     * drawn from its first column to the column it references.
     *
     * @param  array<string, array<string, mixed>>  $tables
     * @return list<array{from: string, to: string, column: string, target: string, label: string, points_at: string}>
     */
    protected function links(array $tables): array
    {
        $links = [];

        foreach ($tables as $key => $table) {
            foreach ($table['foreign_keys'] as $foreignKey) {
                $to = $foreignKey['key'] ?? null;
                $columns = array_values((array) ($foreignKey['columns'] ?? []));
                $targets = array_values((array) ($foreignKey['foreign_columns'] ?? []));

                if (! is_string($to) || ! isset($tables[$to]) || $columns === []) {
                    continue;
                }

                $links[] = [
                    'from' => (string) $key,
                    'to' => $to,
                    'column' => (string) $columns[0],
                    'target' => (string) ($targets[0] ?? ''),
                    'label' => ($table['label'] ?? $key).'.'.implode(', ', $columns).' → '.($tables[$to]['label'] ?? $to).'.'.implode(', ', $targets),
                    'points_at' => ($tables[$to]['label'] ?? $to).'.'.($targets[0] ?? ''),
                ];
            }
        }

        return $links;
    }

    /**
     * The box of one table, not yet placed: its size and its lines.
     *
     * @param  array<string, mixed>  $table
     * @param  list<array{from: string, to: string, column: string, target: string, label: string, points_at: string}>  $links
     * @return array<string, mixed>
     */
    protected function node(array $table, array $links, bool $keysOnly): array
    {
        $key = (string) $table['key'];
        $referenced = [];
        $references = [];

        foreach ($links as $link) {
            if ($link['to'] === $key) {
                $referenced[$link['target']] = true;
            }

            if ($link['from'] === $key) {
                $references[$link['column']] = $link['points_at'];
            }
        }

        $room = (int) floor((self::MAX_WIDTH - 2 * self::PAD - self::MARK) / self::CHAR);
        $title = $this->short((string) ($table['label'] ?? $key), (int) floor((self::MAX_WIDTH - 2 * self::PAD) / self::CHAR) - ($table['kind'] === 'view' ? 5 : 0));
        $chars = mb_strlen($title) + ($table['kind'] === 'view' ? 5 : 0);
        $rows = [];
        $hidden = 0;

        foreach ($table['columns'] as $column) {
            $name = (string) $column['name'];
            $isKey = $column['primary'] || $column['foreign'] || isset($referenced[$name]);

            if ($keysOnly && ! $isKey) {
                $hidden++;

                continue;
            }

            $type = $this->short((string) $column['type'], self::TYPE_CHARS);
            $label = $this->short($name, max(8, $room - mb_strlen($type) - 2));

            $rows[] = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'mark' => $column['primary'] ? 'PK' : ($column['foreign'] ? 'FK' : ''),
                'foreign' => (bool) $column['foreign'],
                'title' => $name.' · '.$column['type'].(isset($references[$name]) ? ' → '.$references[$name] : ''),
            ];

            $chars = max($chars, (int) ceil(self::MARK / self::CHAR) + mb_strlen($label) + 2 + mb_strlen($type));
        }

        if ($hidden > 0) {
            $rows[] = [
                'name' => '',
                'label' => '+ '.$hidden.' '.($hidden === 1 ? 'column' : 'columns'),
                'type' => '',
                'mark' => '',
                'foreign' => false,
                'title' => '',
            ];
        }

        return [
            'key' => $key,
            'label' => $title,
            'kind' => (string) $table['kind'],
            'w' => (int) min(self::MAX_WIDTH, max(self::MIN_WIDTH, ceil($chars * self::CHAR) + 2 * self::PAD)),
            'h' => self::HEAD + count($rows) * self::ROW + ($rows === [] ? 0 : 6),
            'rows' => $rows,
        ];
    }

    /**
     * How far to the right each table stands: one step past the furthest
     * of the tables it points at. A foreign key that closes a circle does
     * not push its table any further.
     *
     * @param  list<string>  $keys
     * @param  array<string, array<string, bool>>  $points
     * @return array<string, int>
     */
    protected function ranks(array $keys, array $points): array
    {
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        $ranks = [];
        $open = [];

        $rank = function (string $key) use (&$rank, &$ranks, &$open, $points): int {
            if (isset($ranks[$key])) {
                return $ranks[$key];
            }

            $open[$key] = true;
            $depth = 0;

            foreach (array_keys($points[$key] ?? []) as $target) {
                if (! isset($open[$target])) {
                    $depth = max($depth, $rank((string) $target) + 1);
                }
            }

            unset($open[$key]);

            return $ranks[$key] = $depth;
        };

        foreach ($keys as $key) {
            $rank($key);
        }

        return $ranks;
    }

    /**
     * Stands a column of boxes at `$x`, one under the other and all as
     * wide as the widest. Returns where the next column starts and the
     * lowest point reached so far.
     *
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  list<string>  $keys
     * @return array{int, int}
     */
    protected function place(array &$nodes, array $keys, int $x, int $bottom): array
    {
        if ($keys === []) {
            return [$x, $bottom];
        }

        $width = max(array_map(static fn (string $key): int => $nodes[$key]['w'], $keys));
        $y = self::MARGIN;

        foreach ($keys as $key) {
            $nodes[$key]['x'] = $x;
            $nodes[$key]['y'] = $y;
            $nodes[$key]['w'] = $width;
            $y += $nodes[$key]['h'] + self::GAP_Y;
        }

        return [$x + $width + self::GAP_X, max($bottom, $y)];
    }

    /**
     * The line of one foreign key: from its column, round to the column
     * it references — or to the head of the table when that column is not
     * drawn.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @param  array{from: string, to: string, column: string, target: string, label: string, points_at: string}  $link
     * @return array{from: string, to: string, label: string, path: string, points: array{int, int, int, int, int, int}, x: int, y: int, reach: int}
     */
    protected function edge(array $from, array $to, array $link): array
    {
        $y1 = $this->rowY($from, $link['column']);
        $y2 = $this->rowY($to, $link['target']);

        if ($from['x'] >= $to['x'] + $to['w']) {
            $x1 = $from['x'];
            $x2 = $to['x'] + $to['w'];
            $bend = max(30, intdiv($x1 - $x2, 2));
            [$c1, $c2] = [$x1 - $bend, $x2 + $bend];
        } elseif ($to['x'] >= $from['x'] + $from['w']) {
            $x1 = $from['x'] + $from['w'];
            $x2 = $to['x'];
            $bend = max(30, intdiv($x2 - $x1, 2));
            [$c1, $c2] = [$x1 + $bend, $x2 - $bend];
        } else {
            // One above the other, or a table that points at itself: out to the right and back.
            $x1 = $from['x'] + $from['w'];
            $x2 = $to['x'] + $to['w'];
            $bend = 40 + min(60, intdiv(abs($y2 - $y1), 6));
            [$c1, $c2] = [max($x1, $x2) + $bend, max($x1, $x2) + $bend];
        }

        return [
            'from' => $link['from'],
            'to' => $link['to'],
            'label' => $link['label'],
            'path' => 'M'.$x1.' '.$y1.' C'.$c1.' '.$y1.' '.$c2.' '.$y2.' '.$x2.' '.$y2,
            'points' => [$x1, $y1, $c1, $c2, $x2, $y2],
            'x' => $x1,
            'y' => $y1,
            'reach' => max($x1, $x2, (int) ceil(max($c1, $c2) * 0.75 + max($x1, $x2) * 0.25)),
        ];
    }

    /**
     * The middle of the line of a column — of the head, when the box does
     * not show that column.
     *
     * @param  array<string, mixed>  $node
     */
    protected function rowY(array $node, string $column, ?int $at = null): int
    {
        foreach ($node['rows'] as $index => $row) {
            if ($at === $index || ($at === null && $column !== '' && $row['name'] === $column)) {
                return (int) ($node['y'] + self::HEAD + 3 + $index * self::ROW + self::ROW / 2);
            }
        }

        return (int) ($node['y'] + self::HEAD / 2);
    }

    protected function short(string $text, int $chars): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $chars ? mb_substr($text, 0, max(1, $chars - 1)).'…' : $text;
    }
}
