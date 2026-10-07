<?php

declare(strict_types=1);

use Larapilot\Support\Markdown;

it('adds heading ids to rendered markdown', function (): void {
    $html = Markdown::toHtml("## Elevator Pitch\n\nBody text.");

    expect($html)->toContain('id="elevator-pitch"')
        ->toContain('Body text.');
});

it('keeps heading ids consistent with extracted headings', function (): void {
    $markdown = "## Setup `config`\n\n### **Fast** start\n";

    $html = Markdown::toHtml($markdown);

    foreach (Markdown::headings($markdown) as $heading) {
        expect($html)->toContain('id="'.$heading['id'].'"');
    }
});

it('skips fenced code blocks when extracting headings', function (): void {
    $markdown = <<<'MD'
## Real heading

```bash
## not a heading
```

### Also real
MD;

    expect(Markdown::headings($markdown))->toBe([
        ['level' => 2, 'title' => 'Real heading', 'id' => 'real-heading'],
        ['level' => 3, 'title' => 'Also real', 'id' => 'also-real'],
    ]);
});

it('strips raw html from rendered markdown', function (): void {
    $html = Markdown::toHtml('Hello <script>alert(1)</script> world');

    expect($html)->not->toContain('<script>');
});

it('renders fenced code blocks in the fallback renderer', function (): void {
    $method = new ReflectionMethod(Markdown::class, 'basicToHtml');

    $html = $method->invoke(null, "## Title\n\n```bash\n## code line\n<tag>\n```\n\nAfter.");

    expect($html)->toContain('<h2 id="title">Title</h2>')
        ->toContain('<pre><code>')
        ->toContain('## code line')
        ->toContain('&lt;tag&gt;')
        ->toContain('</code></pre>')
        ->not->toContain('id="code-line"');
});

it('renders tables and task lists', function (): void {
    $html = Markdown::toHtml("| Layer | Choice |\n| --- | --- |\n| Payments | Stripe |\n\n- [x] Done\n- [ ] Open");

    expect($html)->toContain('<table>')
        ->toContain('<td>Stripe</td>')
        ->toContain('type="checkbox"')
        ->not->toContain('| --- |');
});

it('keeps every line of an issue-like text when asked to', function (): void {
    $markdown = "**Epic:** EP-001 | **Status:** TODO\n**Blocked by:** -\n\n**User Story**\nAs a member,\nI want to book a class";

    expect(Markdown::toHtml($markdown))->not->toContain('<br')
        ->and(Markdown::toHtml($markdown, true))
        ->toContain("TODO<br />\n<strong>Blocked by:</strong> -")
        ->toContain("As a member,<br />\nI want to book a class");
});
