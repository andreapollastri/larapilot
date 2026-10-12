<?php

/**
 * A local stand-in for a Hackly instance, for the Security page of the docs: the API shapes of
 * src/Services/Hackly/HacklyClient.php and tests/Feature/HacklyTest.php, with Fernway's findings.
 *
 *   php -S 127.0.0.1:8843 tools/docs-demo/php/hackly-stub.php
 *
 * Read-only apart from triage, which it accepts and forgets. Findings are fictional: no CVE id.
 */
declare(strict_types=1);

const TOKEN = 'hk_demo_fernway_not_a_real_token';
const REPO = '3f9a7c21-5d4e-4b8a-9c1f-00000000f001';
const SITE = '3f9a7c21-5d4e-4b8a-9c1f-00000000f010';

header('Content-Type: application/json');
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $query);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function answer(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if (! str_starts_with($path, '/api/v1/')) {
    answer(['message' => 'Not Found'], 404);
}
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer '.TOKEN) {
    answer(['message' => 'Unauthenticated.'], 401);
}

function finding(string $n, string $title, string $severity, string $status, string $category, string $source, array $more = []): array
{
    return array_merge([
        'id' => substr(md5('fernway'.$n), 0, 8).'-7e3f-4a61-b2d9-0000000000'.$n,
        'title' => $title, 'severity' => $severity, 'status' => $status, 'category' => $category,
        'category_label' => ucfirst(str_replace('_', ' ', $category)), 'source' => $source, 'cve' => null,
        'reachability' => null, 'confidence' => 0.85, 'noise_filtered' => false, 'description' => $title.'.',
        'false_positive_note' => null, 'false_positive_noted_at' => null, 'evidence_lines' => [],
        'subject' => 'fernway/fernway', 'repository' => ['id' => REPO, 'full_name' => 'fernway/fernway'],
        'created_at' => '2026-10-05T11:12:00+00:00', 'updated_at' => '2026-10-05T11:12:00+00:00',
    ], $more);
}

$site = ['subject' => 'staging.fernway.app', 'repository' => null, 'asset' => ['id' => SITE, 'domain' => 'staging.fernway.app']];
$findings = [
    'repo:open' => [
        finding('01', 'Booking endpoint without a rate limit', 'high', 'open', 'laravel', 'laravel_checks', ['reachability' => 'reachable', 'evidence_lines' => ['routes/web.php:13'], 'description' => 'POST /sessions/{session}/bookings has no throttle middleware: a script can book and cancel in a loop.']),
        finding('02', 'Stripe test key committed in .env.example', 'medium', 'open', 'secrets', 'gitleaks', ['evidence_lines' => ['.env.example:31']]),
        finding('03', 'Webhook signature not verified', 'high', 'open', 'sast', 'semgrep', ['reachability' => 'reachable', 'evidence_lines' => ['app/Http/Controllers/StripeWebhookController.php:14']]),
    ],
    'repo:ack' => [
        finding('04', 'Session cookie without SameSite=strict', 'low', 'ack', 'laravel', 'laravel_checks', ['evidence_lines' => ['config/session.php:202']]),
    ],
    'repo:fixed' => [
        finding('05', 'APP_DEBUG on in the staging environment', 'high', 'fixed', 'laravel', 'checkpoint', ['updated_at' => '2026-10-02T09:30:00+00:00']),
    ],
    'repo:false_positive' => [
        finding('06', 'MD5 used for hashing', 'low', 'false_positive', 'sast', 'semgrep', ['evidence_lines' => ['tests/Feature/StripeWebhookTest.php:9'], 'false_positive_note' => 'A fixture id in a test, never a password or a token.', 'false_positive_noted_at' => '2026-10-05T11:30:00+00:00']),
    ],
    'target:open' => [
        finding('07', 'Content-Security-Policy header missing', 'medium', 'open', 'headers', 'headers_check', $site),
        finding('08', 'HSTS max-age below six months', 'low', 'open', 'tls', 'tls_check', $site),
    ],
];
$repositories = [
    ['id' => REPO, 'owner' => 'fernway', 'name' => 'fernway', 'full_name' => 'fernway/fernway', 'default_branch' => 'main', 'status' => 'active',
        'html_url' => 'https://github.com/fernway/fernway', 'last_scanned_at' => '2026-10-11T03:00:00+00:00', 'targets' => [['id' => SITE, 'domain' => 'staging.fernway.app']]],
    ['id' => '3f9a7c21-5d4e-4b8a-9c1f-00000000f002', 'owner' => 'fernway', 'name' => 'fernway-website', 'full_name' => 'fernway/fernway-website', 'default_branch' => 'main', 'status' => 'active',
        'html_url' => 'https://github.com/fernway/fernway-website', 'last_scanned_at' => '2026-10-11T03:00:00+00:00', 'targets' => []],
];

switch (true) {
    case $path === '/api/v1/me':
        answer(['data' => ['name' => 'Maya Chen', 'email' => 'maya@fernway.example']]);
    case $path === '/api/v1/repositories':
        answer(['data' => $repositories, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($repositories)]]);
    case $path === '/api/v1/repositories/'.REPO:
        answer(['data' => $repositories[0]]);
    case $path === '/api/v1/findings':
        $key = (isset($query['target_id']) ? 'target' : 'repo').':'.($query['status'] ?? 'open');
        answer(['data' => $findings[$key] ?? [], 'meta' => ['current_page' => 1, 'last_page' => 1]]);
    case $path === '/api/v1/findings/triage':
        answer(['data' => []]);
    case str_ends_with($path, '/markdown'):
        header('Content-Type: text/markdown');
        echo "# Booking endpoint without a rate limit\n\nAdd `throttle:bookings` to the booking routes.";
        exit;
    case str_ends_with($path, '/prompt'):
        header('Content-Type: text/markdown');
        echo 'Add a named rate limiter for bookings (10 a minute per member) and apply it to the booking routes.';
        exit;
    case str_starts_with($path, '/api/v1/findings/') && $method === 'PATCH':
        answer(['data' => ['id' => basename($path), 'status' => 'open']]);
    case $path === '/api/v1/repositories/'.REPO.'/scans':
        answer(['data' => ['id' => 'scan-1', 'status' => 'queued']], 201);
}
answer(['message' => 'Not Found'], 404);
