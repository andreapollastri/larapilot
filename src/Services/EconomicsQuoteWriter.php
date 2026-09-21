<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateInterval;
use DateTimeImmutable;
use Larapilot\Support\ArtifactLanguage;

class EconomicsQuoteWriter
{
    public function __construct(
        protected PrdService $prd,
        protected SpecService $specs,
        protected PlanService $plans,
        protected ChoicesService $choices,
        protected ConfigService $config,
    ) {}

    /**
     * Client-facing commercial proposal in the PRD language.
     *
     * This is a sales document, not an engineering one: what the client gets,
     * what it costs, when it lands, and what each side has to bring. Story
     * points, task lists, and architecture stay in the internal report.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function render(array $snapshot): string
    {
        $prd = $this->prd->read() ?? '';
        $lang = ArtifactLanguage::detect($prd !== '' ? $prd : null);
        $t = $this->strings($lang);
        $title = $this->projectTitle($prd);
        $quote = is_array($snapshot['quote'] ?? null) ? $snapshot['quote'] : [];
        $effort = is_array($snapshot['effort'] ?? null) ? $snapshot['effort'] : [];
        $profile = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];
        $inception = is_array($snapshot['inception'] ?? null) ? $snapshot['inception'] : [];
        $currency = (string) ($snapshot['country']['currency'] ?? $quote['currency'] ?? 'EUR');
        $today = new DateTimeImmutable('now');
        $months = max(0.5, (float) ($quote['calendar_months'] ?? $effort['calendar_months'] ?? 1));
        $hours = (float) ($quote['billable_hours'] ?? $effort['billable_hours'] ?? 0);
        $hoursPerDay = max(1.0, (float) ($profile['hours_per_day'] ?? 6));
        $days = (int) max(1, ceil($hours / $hoursPerDay));
        $gross = (float) ($quote['gross'] ?? 0);
        $vat = (float) ($quote['vat'] ?? 0);
        $total = (float) ($quote['client_total'] ?? ($gross + $vat));
        $maintenanceYear = (float) ($quote['maintenance_year'] ?? 0);
        $maintenanceMonth = (float) ($quote['maintenance_monthly'] ?? ($maintenanceYear / 12));
        $maintenancePct = (float) ($profile['maintenance_annual_pct'] ?? 15);
        $vatRate = (float) ($quote['vat_rate'] ?? 0);
        $vatMode = (string) ($quote['vat_mode'] ?? 'domestic');
        $reference = 'LP-'.$today->format('Ymd').'-'.strtoupper(substr($this->slug($title), 0, 8));
        $money = fn (float $amount): string => $this->money($amount, $currency, $lang);
        $sections = $this->prdSections($prd);
        $deliverables = $this->deliverables($sections);
        $objectives = $this->objectives($sections);
        $scope = $this->scopeLists($sections, $t);

        $lines = [
            '---',
            'title: '.$this->yamlScalar($t['document_title'].' — '.$title),
            'author: Larapilot',
            'date: '.$today->format('Y-m-d'),
            'lang: '.$lang,
            '---',
            '',
            '# '.$t['document_title'],
            '',
            '| | |',
            '| --- | --- |',
            '| **'.$t['project'].'** | '.$title.' |',
            '| **'.$t['reference'].'** | '.$reference.' |',
            '| **'.$t['date'].'** | '.$this->formatDate($today, $lang).' |',
            '| **'.$t['validity'].'** | '.$t['validity_value'].' |',
            '',
        ];

        $pitch = $this->firstParagraph($sections['elevator'] ?? $sections['pitch'] ?? '');
        $vision = $this->firstParagraph($sections['vision'] ?? '');

        $lines[] = '## '.$t['summary'];
        $lines[] = '';
        $lines[] = sprintf(
            $t['summary_body'],
            '**'.$title.'**',
            $this->daysLabel($days, $lang),
            $this->durationLabel($months, $lang),
            '**'.$money($gross).'**',
            $money($maintenanceYear)
        );
        $lines[] = '';

        if ($pitch !== '') {
            $lines[] = $pitch;
            $lines[] = '';
        }

        if ($vision !== '') {
            $lines[] = '> '.$vision;
            $lines[] = '';
        }

        if ($objectives !== []) {
            $lines[] = '## '.$t['objectives'];
            $lines[] = '';
            foreach ($objectives as $objective) {
                $lines[] = '- '.$objective;
            }
            $lines[] = '';
        }

        $lines[] = '## '.$t['deliverables'];
        $lines[] = '';

        if ($deliverables === []) {
            $lines[] = $t['deliverables_empty'];
        } else {
            $lines[] = $t['deliverables_intro'];
            $lines[] = '';
            foreach ($deliverables as $deliverable) {
                $lines[] = '- '.$deliverable;
            }
        }

        $lines[] = '';

        $techNote = $this->techNote($inception, $t);
        if ($techNote !== null) {
            $lines[] = $techNote;
            $lines[] = '';
        }

        array_push($lines, ...$this->infrastructureChapter($inception, $snapshot, $t, $money));
        array_push($lines, ...$this->assuranceChapter($prd, $t));

        $lines[] = '## '.$t['investment'];
        $lines[] = '';
        $lines[] = '| '.$t['item'].' | '.$t['amount'].' |';
        $lines[] = '| --- | ---: |';
        // A discount the client was given belongs on the offer, not hidden in a
        // lower number: list price, what came off, what is left.
        $discount = (float) ($quote['discount'] ?? 0);

        if ($discount > 0) {
            $lines[] = '| '.$t['build'].' | '.$money((float) ($quote['list_price'] ?? $gross)).' |';
            $lines[] = '| '.$t['discount'].' ('.$quote['discount_pct'].'%) | −'.$money($discount).' |';
            $lines[] = '| '.$t['discounted'].' | '.$money($gross).' |';
        } else {
            $lines[] = '| '.$t['build'].' | '.$money($gross).' |';
        }

        if ($vatMode === 'eu_b2b') {
            $lines[] = '| '.$t['vat_reverse'].' | '.$money(0).' |';
        } elseif ($vatRate > 0) {
            $lines[] = '| '.$t['vat'].' ('.$vatRate.'%) | '.$money($vat).' |';
        } else {
            $lines[] = '| '.$t['vat_exempt'].' | '.$money(0).' |';
        }

        $lines[] = '| **'.$t['total'].'** | **'.$money($total).'** |';
        $lines[] = '| '.$t['maintenance_year'].' | '.$money($maintenanceYear).' |';
        $lines[] = '';
        $lines[] = sprintf($t['effort_note'], $this->daysLabel($days, $lang), $this->durationLabel($months, $lang));
        $lines[] = '';

        $lines[] = '## '.$t['timeline'];
        $lines[] = '';
        $lines[] = sprintf($t['timeline_intro'], $this->durationLabel($months, $lang), $this->daysLabel($days, $lang));
        $lines[] = '';
        array_push($lines, ...$this->gantt($today, $months, $t));
        $lines[] = '';

        $lines[] = '## '.$t['payment'];
        $lines[] = '';
        $kickoff = round($total * 0.40, 2);
        $uat = round($total * 0.40, 2);
        $golive = round($total - $kickoff - $uat, 2);
        $lines[] = '| '.$t['milestone'].' | '.$t['share'].' | '.$t['amount'].' |';
        $lines[] = '| --- | ---: | ---: |';
        $lines[] = '| '.$t['pay_kickoff'].' | 40% | '.$money($kickoff).' |';
        $lines[] = '| '.$t['pay_uat'].' | 40% | '.$money($uat).' |';
        $lines[] = '| '.$t['pay_golive'].' | 20% | '.$money($golive).' |';
        $lines[] = '';
        $lines[] = $t['payment_note'];
        $lines[] = '';

        $lines[] = '## '.$t['maintenance_section'];
        $lines[] = '';
        $lines[] = sprintf(
            $t['maintenance_body'],
            $money($maintenanceYear),
            $money($maintenanceMonth),
            $this->number($maintenancePct, $lang)
        );
        $lines[] = '';

        $lines[] = '## '.$t['included'];
        $lines[] = '';
        foreach ($scope['included'] as $item) {
            $lines[] = '- '.$item;
        }
        $lines[] = '';

        $lines[] = '## '.$t['not_included'];
        $lines[] = '';
        foreach ($scope['excluded'] as $item) {
            $lines[] = '- '.$item;
        }
        $lines[] = '';

        $lines[] = '## '.$t['client_provides'];
        $lines[] = '';
        foreach ($scope['client'] as $item) {
            $lines[] = '- '.$item;
        }
        $lines[] = '';

        $lines[] = '## '.$t['next_steps'];
        $lines[] = '';
        foreach ($t['next_steps_items'] as $item) {
            $lines[] = '- '.$item;
        }
        $lines[] = '';

        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## '.$t['signoff'];
        $lines[] = '';
        $lines[] = '| '.$t['supplier'].' | '.$t['client'].' |';
        $lines[] = '| --- | --- |';
        $lines[] = '| | |';
        $lines[] = '| ______________________________ | ______________________________ |';
        $lines[] = '| '.$t['date_sign'].': _______________ | '.$t['date_sign'].': _______________ |';
        $lines[] = '';
        $lines[] = '_'.$t['disclaimer'].'_';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function filename(?string $prd, string $lang): string
    {
        $slug = $this->slug($this->projectTitle($prd ?? ''));
        $suffix = match (preg_replace('/-.*$/', '', strtolower($lang))) {
            'it' => 'preventivo',
            'es' => 'presupuesto',
            'fr' => 'devis',
            'de' => 'angebot',
            'pt' => 'orcamento',
            'nl' => 'offerte',
            'pl' => 'oferta',
            default => 'proposal',
        };

        return $slug.'-'.$suffix.'.md';
    }

    /**
     * @return array<string, string>
     */
    protected function prdSections(string $prd): array
    {
        $map = [
            'elevator' => ['Elevator Pitch', 'Pitch', 'Sintesi', 'Panoramica', 'Accroche', 'Resumen', 'Synthèse', 'Overview'],
            'vision' => ['Vision', 'Visione', 'Visión'],
            'goals' => ['Business Goals', 'Goals', 'Objectives', 'Obiettivi', 'Obiettivi di business', 'Objetivos', 'Objectifs'],
            'mvp' => ['MVP Scope', 'Ambito MVP', 'Alcance MVP', 'Périmètre MVP'],
            'architecture' => ['Technical Architecture', 'Architettura tecnica', 'Arquitectura técnica', 'Architecture technique'],
            'requirements' => ['Functional Requirements', 'Requisiti funzionali', 'Funzionalità', 'Funzionalità principali', 'Requisitos funcionales', 'Funcionalidades', 'Exigences fonctionnelles', 'Fonctionnalités'],
        ];

        $found = [];
        $parts = preg_split('/^##\s+(.+)$/m', $prd, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $heading = trim($parts[$i]);
            $body = trim($parts[$i + 1] ?? '');

            foreach ($map as $key => $labels) {
                foreach ($labels as $label) {
                    if (strcasecmp($heading, $label) === 0 && ! isset($found[$key])) {
                        $found[$key] = $body;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * What the client receives, in their own words: backlog titles first, PRD
     * requirements when the backlog is still empty.
     *
     * @param  array<string, string>  $sections
     * @return list<string>
     */
    protected function deliverables(array $sections): array
    {
        $items = [];

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $title = trim((string) ($spec['title'] ?? ''));

            if ($title !== '') {
                $items[$this->normalizeKey($title)] = $this->sentence($title);
            }
        }

        if ($items === []) {
            foreach ($this->bulletLines((string) ($sections['requirements'] ?? ''), 14) as $line) {
                $items[$this->normalizeKey($line)] = $this->sentence($line);
            }
        }

        if ($items === []) {
            foreach ($this->bulletLines((string) ($sections['mvp'] ?? ''), 10) as $line) {
                $items[$this->normalizeKey($line)] = $this->sentence($line);
            }
        }

        return array_slice(array_values($items), 0, 24);
    }

    /**
     * @param  array<string, string>  $sections
     * @return list<string>
     */
    protected function objectives(array $sections): array
    {
        $bullets = $this->bulletLines((string) ($sections['goals'] ?? ''), 6);

        if ($bullets !== []) {
            return array_map(fn (string $line): string => $this->sentence($line), $bullets);
        }

        return [];
    }

    /**
     * One commercial line about the platform — never a stack dump.
     *
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $t
     */
    protected function techNote(array $inception, array $t): ?string
    {
        $choices = $this->choices->read();
        $platform = $inception['deploy_platform'] ?? $choices['deploy_platform'] ?? null;
        $platform = is_string($platform) ? trim($platform) : '';

        if ($platform === '') {
            return '_'.$t['tech_note_generic'].'_';
        }

        return '_'.sprintf($t['tech_note'], $platform).'_';
    }

    /**
     * Where the software will run and who keeps it running — only when the
     * project actually decided. A buyer signs for a system, not for a zip file,
     * and hosting, backups, and who answers at 2am are part of what they buy.
     *
     * @param  array<string, mixed>  $inception
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $t
     * @return list<string>
     */
    protected function infrastructureChapter(array $inception, array $snapshot, array $t, callable $money): array
    {
        $strings = is_array($t['infrastructure'] ?? null) ? $t['infrastructure'] : [];

        if ($strings === []) {
            return [];
        }

        $choices = $this->choices->read();
        $pick = static function (mixed ...$candidates): ?string {
            foreach ($candidates as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $value = trim($candidate);

                    if (strcasecmp($value, 'Not decided') !== 0) {
                        return $value;
                    }
                }
            }

            return null;
        };

        $platform = $pick($inception['deploy_platform'] ?? null, $choices['deploy_platform'] ?? null);
        $management = $pick($inception['server_management'] ?? null, $choices['server_management'] ?? null);
        $ops = $pick($inception['ops_owner'] ?? null, $choices['ops_owner'] ?? null);
        $support = $pick($inception['support_window'] ?? null, $choices['support_window'] ?? null);

        // Nothing was decided about hosting: the quote stays silent rather than
        // promising an arrangement nobody agreed to.
        if ($platform === null && $management === null && $ops === null) {
            return [];
        }

        $operator = $this->infrastructureOperator($management, $ops, $platform);
        $rows = [];

        foreach ([
            [$strings['platform'], $platform],
            [$strings['management'], $management],
            [$strings['ops'], $ops],
        ] as [$label, $value]) {
            if ($value !== null) {
                $rows[] = [$label, $value];
            }
        }

        if ($operator !== null) {
            $rows[] = [$strings['backups'], $strings['backups_'.$operator]];
            $rows[] = [$strings['monitoring'], $strings['monitoring_'.$operator]];
            $rows[] = [$strings['tls'], $strings['tls_'.$operator]];
        }

        if ($support !== null) {
            $rows[] = [$strings['support'], $support];
        }

        $running = (float) ($snapshot['saas']['infrastructure_monthly'] ?? 0);

        if ($running > 0) {
            $rows[] = [$strings['cost'], sprintf($strings['cost_value'], $money($running))];
        }

        $lines = ['## '.$strings['title'], '', $strings['intro'], '', '| | |', '| --- | --- |'];

        foreach ($rows as [$label, $value]) {
            $lines[] = '| **'.$label.'** | '.$value.' |';
        }

        $lines[] = '';

        if ($running > 0) {
            $lines[] = $strings['cost_note'];
            $lines[] = '';
        }

        if ($operator === null) {
            $lines[] = $strings['open'];
            $lines[] = '';
        }

        $lines[] = $strings['ownership'];
        $lines[] = '';

        return $lines;
    }

    /**
     * Who actually operates the machine: us on a server we manage, us on a
     * managed platform, or the client's own IT. It decides which promises the
     * quote is allowed to make about backups, monitoring, and certificates.
     */
    protected function infrastructureOperator(?string $management, ?string $ops, ?string $platform): ?string
    {
        $haystack = strtolower(trim(($management ?? '').' '.($ops ?? '').' '.($platform ?? '')));

        if ($haystack === '') {
            return null;
        }

        if (str_contains($haystack, 'client') || str_contains($haystack, 'cliente')) {
            return 'client';
        }

        foreach (['self', 'vps', 'bare', 'kubernetes', 'k8s', 'hetzner', 'digitalocean', 'dedicated'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return 'us';
            }
        }

        foreach (['managed', 'forge', 'vapor', 'cloud', 'paas', 'heroku', 'shared', 'platform'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return 'platform';
            }
        }

        return null;
    }

    /**
     * The chapter that sells the part a demo cannot show. Every claim here is
     * something this project's settings actually do — testing mode, security
     * scan, git mode, release mode — so the document never promises a practice
     * the delivery does not follow.
     *
     * @param  array<string, mixed>  $t
     * @return list<string>
     */
    protected function assuranceChapter(string $prd, array $t): array
    {
        $strings = is_array($t['assurance'] ?? null) ? $t['assurance'] : [];

        if ($strings === []) {
            return [];
        }

        $settings = $this->config->settings();
        $on = static fn (mixed $value): bool => $value === true || strtoupper((string) $value) === 'YES';

        $items = [$strings['review'], $strings['criteria']];

        $items[] = match (strtoupper((string) ($settings['testing'] ?? 'NORMAL'))) {
            'BEST' => $strings['tests_best'],
            'MINIMAL' => $strings['tests_light'],
            default => $strings['tests_normal'],
        };

        if ($on($settings['security_scan'] ?? false)) {
            $items[] = $strings['scan'];
        }

        $items[] = $strings['owasp'];
        $items[] = $strings['secrets'];

        if (strtoupper((string) ($settings['git_mode'] ?? '')) === 'GITFLOW') {
            $items[] = $strings['gitflow'];
        }

        if ($on($settings['release_mode'] ?? false)) {
            $items[] = $strings['releases'];
        }

        $items[] = $strings['updates'];

        if ($this->handlesPersonalData($prd)) {
            $items[] = $strings['privacy'];
        }

        $lines = ['## '.$strings['title'], '', $strings['intro'], ''];

        foreach ($items as $item) {
            $lines[] = '- '.$item;
        }

        $lines[] = '';
        $lines[] = $strings['close'];
        $lines[] = '';

        return $lines;
    }

    /**
     * The privacy claim is only made when the project document says personal
     * data is in scope — a GDPR promise on a product that stores none is noise.
     */
    protected function handlesPersonalData(string $prd): bool
    {
        return preg_match(
            '/\b(gdpr|rgpd|dsgvo|personal data|dati personali|datos personales|données personnelles|privacy|consenso|consentimiento|consentement)\b/i',
            $prd
        ) === 1;
    }

    /**
     * @param  array<string, string>  $sections
     * @param  array<string, mixed>  $t
     * @return array{included: list<string>, excluded: list<string>, client: list<string>}
     */
    protected function scopeLists(array $sections, array $t): array
    {
        $mvp = (string) ($sections['mvp'] ?? '');
        $inScope = $this->subsectionBullets($mvp, ['In Scope', 'In ambito', 'Dentro del alcance', 'Dans le périmètre']);
        $outScope = $this->subsectionBullets($mvp, ['Out of Scope', 'Fuori ambito', 'Fuera de alcance', 'Hors périmètre']);
        $future = $this->subsectionBullets($mvp, ['Future Phases', 'Fasi future', 'Fases futuras', 'Phases futures']);

        $included = array_values(array_unique(array_merge($t['included_defaults'], $inScope)));
        $excluded = array_values(array_unique(array_merge($t['excluded_defaults'], $outScope, $future)));
        $client = $t['client_defaults'];

        $choices = $this->choices->read();
        $deploy = $choices['deploy_platform'] ?? null;

        if (is_string($deploy) && trim($deploy) !== '') {
            $client[] = sprintf($t['client_deploy'], trim($deploy));
        }

        return [
            'included' => $included,
            'excluded' => $excluded,
            'client' => $client,
        ];
    }

    /**
     * @param  list<string>  $headings
     * @return list<string>
     */
    protected function subsectionBullets(string $section, array $headings): array
    {
        foreach ($headings as $heading) {
            if (preg_match('/^###\s+'.preg_quote($heading, '/').'\s*$/mi', $section) !== 1) {
                continue;
            }

            $parts = preg_split('/^###\s+'.preg_quote($heading, '/').'\s*$/mi', $section, 2);

            if ($parts === false || ! isset($parts[1])) {
                continue;
            }

            $split = preg_split('/^###\s+/m', $parts[1], 2);
            $chunk = is_array($split) ? (string) $split[0] : $parts[1];

            return $this->bulletLines($chunk, 10);
        }

        return [];
    }

    /**
     * @return list<string>
     */
    protected function bulletLines(string $text, int $max): array
    {
        $items = [];

        foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $matches) !== 1) {
                continue;
            }

            $item = trim($matches[1]);
            $item = preg_replace('/\*\*(.+?)\*\*/', '$1', $item) ?? $item;

            if ($item === '') {
                continue;
            }

            $items[] = $item;

            if (count($items) >= $max) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $t
     * @return list<string>
     */
    protected function gantt(DateTimeImmutable $start, float $months, array $t): array
    {
        $totalDays = max(21, (int) round($months * 30));
        $kickoff = max(3, (int) round($totalDays * 0.08));
        $design = max(4, (int) round($totalDays * 0.12));
        $build = max(8, (int) round($totalDays * 0.55));
        $qa = max(4, (int) round($totalDays * 0.15));
        $launch = max(3, $totalDays - $kickoff - $design - $build - $qa);

        $d1 = $start;
        $d2 = $d1->add(new DateInterval('P'.$kickoff.'D'));
        $d3 = $d2->add(new DateInterval('P'.$design.'D'));
        $d4 = $d3->add(new DateInterval('P'.$build.'D'));
        $d5 = $d4->add(new DateInterval('P'.$qa.'D'));
        $d6 = $d5->add(new DateInterval('P'.$launch.'D'));

        $fmt = static fn (DateTimeImmutable $d): string => $d->format('Y-m-d');

        return [
            '| '.$t['phase'].' | '.$t['start'].' | '.$t['end'].' | '.$t['days'].' |',
            '| --- | --- | --- | ---: |',
            '| '.$t['phase_kickoff'].' | '.$fmt($d1).' | '.$fmt($d2).' | '.$kickoff.' |',
            '| '.$t['phase_design'].' | '.$fmt($d2).' | '.$fmt($d3).' | '.$design.' |',
            '| '.$t['phase_build'].' | '.$fmt($d3).' | '.$fmt($d4).' | '.$build.' |',
            '| '.$t['phase_qa'].' | '.$fmt($d4).' | '.$fmt($d5).' | '.$qa.' |',
            '| '.$t['phase_launch'].' | '.$fmt($d5).' | '.$fmt($d6).' | '.$launch.' |',
            '',
            '```mermaid',
            'gantt',
            '    title '.$t['gantt_title'],
            '    dateFormat YYYY-MM-DD',
            '    axisFormat %d %b',
            '    section '.$t['section_start'],
            '    '.$t['phase_kickoff'].' :a1, '.$fmt($d1).', '.$kickoff.'d',
            '    '.$t['phase_design'].' :a2, after a1, '.$design.'d',
            '    section '.$t['section_build'],
            '    '.$t['phase_build'].' :b1, after a2, '.$build.'d',
            '    '.$t['phase_qa'].' :b2, after b1, '.$qa.'d',
            '    section '.$t['section_launch'],
            '    '.$t['phase_launch'].' :c1, after b2, '.$launch.'d',
            '```',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function strings(string $lang): array
    {
        return match ($lang) {
            'it' => [
                'infrastructure' => [
                    'title' => 'Infrastruttura e messa in esercizio',
                    'intro' => 'Dove vive il software e chi lo tiene in piedi. Sono decisioni prese in fase di analisi e riportate qui per intero: nessuna sorpresa dopo la pubblicazione.',
                    'platform' => 'Piattaforma di hosting',
                    'management' => 'Gestione del server',
                    'ops' => 'Presidio operativo',
                    'backups' => 'Backup',
                    'monitoring' => 'Monitoraggio',
                    'tls' => 'Certificati e dominio',
                    'support' => 'Finestra di assistenza',
                    'cost' => 'Costo di esercizio stimato',
                    'cost_value' => '%s al mese',
                    'cost_note' => 'Il costo di esercizio è una stima: viene fatturato direttamente dal fornitore di hosting e non è compreso nell\'investimento di realizzazione.',
                    'ownership' => 'L\'ambiente, il dominio e i dati restano intestati al cliente: in qualsiasi momento le credenziali possono essere trasferite a un altro fornitore senza riscrivere nulla.',
                    'open' => 'La gestione del server non è ancora stata definita: va decisa prima della messa in esercizio, perché determina backup, monitoraggio e tempi di intervento.',
                    'backups_us' => 'Backup automatici giornalieri con verifica periodica del ripristino',
                    'backups_platform' => 'Backup gestiti dalla piattaforma, più esportazione periodica dei dati applicativi',
                    'backups_client' => 'A carico del reparto IT del cliente',
                    'monitoring_us' => 'Controllo di raggiungibilità e notifica degli errori applicativi',
                    'monitoring_platform' => 'Controllo di raggiungibilità sulla piattaforma e notifica degli errori applicativi',
                    'monitoring_client' => 'A carico del reparto IT del cliente',
                    'tls_us' => 'Certificato TLS rinnovato automaticamente; dominio intestato al cliente',
                    'tls_platform' => 'Certificato TLS gestito dalla piattaforma; dominio intestato al cliente',
                    'tls_client' => 'A carico del reparto IT del cliente',
                ],
                'assurance' => [
                    'title' => 'Sicurezza e qualità: cosa protegge questo investimento',
                    'intro' => 'Un software si paga una volta e si mantiene per anni. Quello che decide quanto costerà mantenerlo — e se una mattina finirà nei guai — non si vede nella demo: si vede in come è stato costruito. Ecco cosa è compreso, senza voce separata a listino.',
                    'close' => 'Nessun vincolo: codice, dati e infrastruttura sono del cliente. Se un domani il progetto passa ad altri, passa con la sua storia, i suoi test e la sua documentazione — non con un problema da decifrare.',
                    'review' => '**Ogni modifica passa da una revisione indipendente** prima di arrivare nel vostro ambiente. Nessuno pubblica il proprio lavoro senza che sia stato letto da qualcun altro.',
                    'criteria' => '**Ogni funzione ha criteri di accettazione scritti prima di essere sviluppata** e viene verificata contro quelli. "Funziona" non è un\'opinione: è una lista di condizioni spuntate.',
                    'tests_best' => '**Suite di test automatici estesa, eseguita a ogni modifica.** È ciò che impedisce che una richiesta di oggi rompa una funzione che ieri andava: la verifica non dipende dalla memoria di nessuno.',
                    'tests_normal' => '**Test automatici sui percorsi critici, eseguiti a ogni modifica.** Le parti che, rompendosi, vi fermerebbero il lavoro sono coperte da controlli che girano da soli.',
                    'tests_light' => '**Test automatici sui percorsi critici** — accessi, pagamenti, funzioni portanti — più verifica funzionale di ogni consegna rispetto ai criteri di accettazione concordati.',
                    'scan' => '**Scansione di sicurezza automatica a ogni modifica.** Le vulnerabilità note vengono intercettate prima della pubblicazione, non dopo una segnalazione.',
                    'owasp' => '**Sicurezza applicativa secondo le pratiche OWASP**: gestione delle credenziali, protezione dei moduli, controllo degli accessi e delle autorizzazioni verificati prima del rilascio.',
                    'secrets' => '**Nessuna credenziale dentro il codice.** Chiavi e password vivono nella configurazione dell\'ambiente, sotto il vostro controllo, e possono essere ruotate senza toccare il software.',
                    'gitflow' => '**Storia tracciabile.** Ogni riga di codice è collegata alla richiesta che l\'ha generata: a distanza di mesi si può ricostruire cosa è cambiato, quando e perché.',
                    'releases' => '**Rilasci numerati con elenco delle modifiche.** Se un aggiornamento crea un problema, si torna alla versione precedente in minuti, non in una giornata.',
                    'updates' => '**Aggiornamenti di sicurezza del framework e delle librerie** inclusi nel canone di manutenzione, non rimandati fino alla prossima emergenza.',
                    'privacy' => '**Trattamento dei dati personali conforme al GDPR**: base giuridica, tempi di conservazione, informative e diritti degli interessati previsti fin dalla progettazione.',
                ],
                'document_title' => 'Offerta commerciale',
                'project' => 'Progetto',
                'reference' => 'Riferimento',
                'date' => 'Data',
                'validity' => 'Validità',
                'validity_value' => '15 giorni dalla data del documento',
                'summary' => 'Sintesi dell\'offerta',
                'summary_body' => 'Questa offerta descrive la realizzazione di %s. Il lavoro è stimato in %s, con una durata indicativa di %s dall\'accettazione. L\'investimento per la realizzazione è di %s, con manutenzione annuale opzionale di %s.',
                'objectives' => 'Obiettivi',
                'deliverables' => 'Cosa realizziamo',
                'deliverables_intro' => 'Il progetto consegna le seguenti funzionalità:',
                'deliverables_empty' => 'Il perimetro segue il documento di progetto condiviso; le funzionalità saranno elencate in dettaglio al kick-off.',
                'tech_note' => 'Realizzazione su misura in tecnologia Laravel, pubblicata su %s. Codice, dati e accessi restano di proprietà del cliente.',
                'tech_note_generic' => 'Realizzazione su misura in tecnologia Laravel. Codice, dati e accessi restano di proprietà del cliente.',
                'investment' => 'Investimento',
                'item' => 'Voce',
                'amount' => 'Importo',
                'build' => 'Realizzazione (imponibile)',
                'discount' => 'Sconto commerciale',
                'discounted' => 'Imponibile scontato',
                'vat' => 'IVA',
                'vat_exempt' => 'IVA non applicata',
                'vat_reverse' => 'IVA — reverse charge UE B2B',
                'total' => 'Totale',
                'maintenance_year' => 'Manutenzione annuale (opzionale)',
                'effort_note' => 'Impegno stimato: **%s**, calendario indicativo **%s**, buffer di progetto e collaudo inclusi.',
                'timeline' => 'Tempi di consegna',
                'timeline_intro' => 'Durata indicativa **%s** su un impegno di **%s**. Le date decorrono dall\'accettazione dell\'offerta e si adattano ai tempi di feedback e alle chiusure aziendali.',
                'phase' => 'Fase',
                'start' => 'Inizio',
                'end' => 'Fine',
                'days' => 'Giorni',
                'phase_kickoff' => 'Kick-off e analisi',
                'phase_design' => 'Approvazione grafica',
                'phase_build' => 'Realizzazione',
                'phase_qa' => 'Collaudo e verifica',
                'phase_launch' => 'Pubblicazione e consegna',
                'gantt_title' => 'Piano di consegna',
                'section_start' => 'Avvio',
                'section_build' => 'Realizzazione',
                'section_launch' => 'Rilascio',
                'payment' => 'Modalità di pagamento',
                'milestone' => 'Milestone',
                'share' => 'Quota',
                'pay_kickoff' => 'All\'accettazione / kick-off',
                'pay_uat' => 'All\'avvio del collaudo',
                'pay_golive' => 'Alla pubblicazione',
                'payment_note' => 'Fatture a 30 giorni data fattura, salvo diverso accordo. La pubblicazione avviene a saldo della quota precedente.',
                'maintenance_section' => 'Manutenzione e assistenza',
                'maintenance_body' => 'Canone annuale suggerito: **%s** (circa **%s** al mese), pari al %s%% della realizzazione. Comprende aggiornamenti di sicurezza, aggiornamento delle componenti software, piccole correzioni e assistenza sull\'uso. Nuove funzionalità sono quotate a parte.',
                'included' => 'Cosa è compreso',
                'not_included' => 'Cosa non è compreso',
                'client_provides' => 'Cosa deve fornire il cliente',
                'included_defaults' => [
                    'Analisi, progettazione e realizzazione delle funzionalità elencate',
                    'Interfacce grafiche condivise e approvate prima della realizzazione',
                    'Verifica di funzionamento su tutte le funzionalità consegnate',
                    'Pubblicazione in ambiente di produzione concordato',
                    'Sessione di consegna e materiale di utilizzo',
                    'Correzione dei difetti segnalati nei 30 giorni successivi alla pubblicazione',
                ],
                'excluded_defaults' => [
                    'Funzionalità non elencate in questa offerta e fasi successive',
                    'Canoni di hosting, domini, licenze e servizi di terze parti',
                    'Produzione di contenuti, campagne pubblicitarie e gestione social',
                    'Formazione oltre la sessione di consegna',
                    'Modifiche al perimetro o rifacimenti grafici dopo l\'approvazione',
                    'Consulenza fiscale, legale o in materia di privacy',
                ],
                'client_defaults' => [
                    'Un referente unico per decisioni, contenuti e approvazioni',
                    'Logo e materiali di immagine coordinata, se già esistenti',
                    'Testi, immagini e dati da inserire',
                    'Accesso a dominio, DNS e caselle email coinvolte',
                    'Credenziali di prova dei servizi da collegare (pagamenti, email, gestionali)',
                    'Riscontri sulle interfacce e sulle consegne nei tempi concordati',
                ],
                'client_deploy' => 'Accesso operativo alla piattaforma di pubblicazione indicata: %s',
                'next_steps' => 'Passi successivi',
                'next_steps_items' => [
                    'Conferma scritta dell\'offerta entro la validità indicata',
                    'Kick-off, raccolta accessi e approvazione del perimetro',
                    'Avvio del calendario di consegna',
                ],
                'signoff' => 'Accettazione',
                'supplier' => 'Fornitore',
                'client' => 'Cliente',
                'date_sign' => 'Data',
                'disclaimer' => 'Offerta commerciale generata con Larapilot a partire dal documento di progetto e dalle stime di lavoro. Importi e date sono una pianificazione e diventano vincolanti con la sottoscrizione.',
            ],
            'es' => [
                'infrastructure' => [
                    'title' => 'Infraestructura y puesta en producción',
                    'intro' => 'Dónde vive el software y quién lo mantiene en pie. Son decisiones tomadas en el análisis y recogidas aquí en su totalidad: sin sorpresas después de la publicación.',
                    'platform' => 'Plataforma de hosting',
                    'management' => 'Gestión del servidor',
                    'ops' => 'Responsable de operación',
                    'backups' => 'Copias de seguridad',
                    'monitoring' => 'Monitorización',
                    'tls' => 'Certificados y dominio',
                    'support' => 'Ventana de soporte',
                    'cost' => 'Coste de explotación estimado',
                    'cost_value' => '%s al mes',
                    'cost_note' => 'El coste de explotación es una estimación: lo factura directamente el proveedor de hosting y no está incluido en la inversión de desarrollo.',
                    'ownership' => 'El entorno, el dominio y los datos quedan a nombre del cliente: las credenciales pueden trasladarse a otro proveedor en cualquier momento sin reescribir nada.',
                    'open' => 'La gestión del servidor aún no está definida: debe decidirse antes de la puesta en producción, porque determina copias, monitorización y tiempos de intervención.',
                    'backups_us' => 'Copias automáticas diarias con verificación periódica de la restauración',
                    'backups_platform' => 'Copias gestionadas por la plataforma, más exportación periódica de los datos de la aplicación',
                    'backups_client' => 'A cargo del departamento IT del cliente',
                    'monitoring_us' => 'Control de disponibilidad y aviso de errores de la aplicación',
                    'monitoring_platform' => 'Control de disponibilidad en la plataforma y aviso de errores de la aplicación',
                    'monitoring_client' => 'A cargo del departamento IT del cliente',
                    'tls_us' => 'Certificado TLS renovado automáticamente; dominio a nombre del cliente',
                    'tls_platform' => 'Certificado TLS gestionado por la plataforma; dominio a nombre del cliente',
                    'tls_client' => 'A cargo del departamento IT del cliente',
                ],
                'assurance' => [
                    'title' => 'Seguridad y calidad: lo que protege esta inversión',
                    'intro' => 'Un software se paga una vez y se mantiene durante años. Lo que decide cuánto costará mantenerlo — y si una mañana dará un disgusto — no se ve en la demo: se ve en cómo fue construido. Esto va incluido, sin línea aparte en el presupuesto.',
                    'close' => 'Sin ataduras: el código, los datos y la infraestructura son del cliente. Si mañana el proyecto pasa a otro equipo, pasa con su historia, sus pruebas y su documentación — no como un problema por descifrar.',
                    'review' => '**Cada cambio pasa por una revisión independiente** antes de llegar a su entorno. Nadie publica su propio trabajo sin que otra persona lo haya leído.',
                    'criteria' => '**Cada funcionalidad tiene criterios de aceptación escritos antes de desarrollarse** y se verifica contra ellos. "Funciona" no es una opinión: es una lista de condiciones cumplidas.',
                    'tests_best' => '**Suite de pruebas automáticas amplia, ejecutada en cada cambio.** Es lo que impide que una petición de hoy rompa algo que ayer funcionaba: la verificación no depende de la memoria de nadie.',
                    'tests_normal' => '**Pruebas automáticas en los recorridos críticos, ejecutadas en cada cambio.** Lo que, al romperse, les pararía el trabajo está cubierto por controles que se ejecutan solos.',
                    'tests_light' => '**Pruebas automáticas en los recorridos críticos** — accesos, pagos, funciones centrales — más verificación funcional de cada entrega frente a los criterios de aceptación acordados.',
                    'scan' => '**Análisis de seguridad automático en cada cambio.** Las vulnerabilidades conocidas se detectan antes de publicar, no tras un incidente.',
                    'owasp' => '**Seguridad de la aplicación según las prácticas OWASP**: gestión de credenciales, protección de formularios, control de accesos y permisos revisados antes de cada entrega.',
                    'secrets' => '**Ninguna credencial dentro del código.** Claves y contraseñas viven en la configuración del entorno, bajo su control, y pueden rotarse sin tocar el software.',
                    'gitflow' => '**Historia trazable.** Cada línea de código está unida a la petición que la originó: meses después se puede reconstruir qué cambió, cuándo y por qué.',
                    'releases' => '**Versiones numeradas con listado de cambios.** Si una actualización da problemas, se vuelve a la anterior en minutos, no en una jornada.',
                    'updates' => '**Actualizaciones de seguridad del framework y de las librerías** incluidas en el mantenimiento, no aplazadas hasta la próxima urgencia.',
                    'privacy' => '**Tratamiento de datos personales conforme al RGPD**: base jurídica, plazos de conservación, avisos y derechos de los interesados previstos desde el diseño.',
                ],
                'document_title' => 'Oferta comercial',
                'project' => 'Proyecto',
                'reference' => 'Referencia',
                'date' => 'Fecha',
                'validity' => 'Validez',
                'validity_value' => '15 días desde la fecha del documento',
                'summary' => 'Resumen de la oferta',
                'summary_body' => 'Esta oferta describe el desarrollo de %s. El trabajo se estima en %s, con una duración aproximada de %s desde la aceptación. La inversión de desarrollo es de %s, con mantenimiento anual opcional de %s.',
                'objectives' => 'Objetivos',
                'deliverables' => 'Qué entregamos',
                'deliverables_intro' => 'El proyecto entrega las siguientes funcionalidades:',
                'deliverables_empty' => 'El alcance sigue el documento de proyecto compartido; las funcionalidades se detallarán en el arranque.',
                'tech_note' => 'Desarrollo a medida con tecnología Laravel, publicado en %s. El código, los datos y los accesos son propiedad del cliente.',
                'tech_note_generic' => 'Desarrollo a medida con tecnología Laravel. El código, los datos y los accesos son propiedad del cliente.',
                'investment' => 'Inversión',
                'item' => 'Concepto',
                'amount' => 'Importe',
                'build' => 'Desarrollo (base imponible)',
                'discount' => 'Descuento comercial',
                'discounted' => 'Base imponible con descuento',
                'vat' => 'IVA',
                'vat_exempt' => 'IVA no aplicado',
                'vat_reverse' => 'IVA — inversión del sujeto pasivo UE B2B',
                'total' => 'Total',
                'maintenance_year' => 'Mantenimiento anual (opcional)',
                'effort_note' => 'Esfuerzo estimado: **%s**, calendario aproximado **%s**, con margen de gestión y pruebas incluido.',
                'timeline' => 'Plazos de entrega',
                'timeline_intro' => 'Duración aproximada de **%s** sobre un esfuerzo de **%s**. Las fechas empiezan con la aceptación de la oferta y se ajustan a los tiempos de respuesta y a los periodos de cierre.',
                'phase' => 'Fase',
                'start' => 'Inicio',
                'end' => 'Fin',
                'days' => 'Días',
                'phase_kickoff' => 'Arranque y análisis',
                'phase_design' => 'Aprobación del diseño',
                'phase_build' => 'Desarrollo',
                'phase_qa' => 'Pruebas y validación',
                'phase_launch' => 'Publicación y entrega',
                'gantt_title' => 'Plan de entrega',
                'section_start' => 'Arranque',
                'section_build' => 'Desarrollo',
                'section_launch' => 'Publicación',
                'payment' => 'Condiciones de pago',
                'milestone' => 'Hito',
                'share' => 'Parte',
                'pay_kickoff' => 'A la aceptación / arranque',
                'pay_uat' => 'Al inicio de las pruebas',
                'pay_golive' => 'A la publicación',
                'payment_note' => 'Facturas a 30 días desde la fecha de factura, salvo acuerdo distinto. La publicación se realiza tras el cobro del hito anterior.',
                'maintenance_section' => 'Mantenimiento y soporte',
                'maintenance_body' => 'Cuota anual sugerida: **%s** (unos **%s** al mes), el %s%% del desarrollo. Incluye actualizaciones de seguridad, actualización de componentes, correcciones menores y soporte de uso. Las nuevas funcionalidades se presupuestan aparte.',
                'included' => 'Qué incluye',
                'not_included' => 'Qué no incluye',
                'client_provides' => 'Qué debe aportar el cliente',
                'included_defaults' => [
                    'Análisis, diseño y desarrollo de las funcionalidades listadas',
                    'Interfaces revisadas y aprobadas antes del desarrollo',
                    'Verificación de funcionamiento de todo lo entregado',
                    'Publicación en el entorno de producción acordado',
                    'Sesión de entrega y material de uso',
                    'Corrección de defectos notificados durante 30 días tras la publicación',
                ],
                'excluded_defaults' => [
                    'Funcionalidades no listadas en esta oferta y fases posteriores',
                    'Hosting, dominios, licencias y servicios de terceros',
                    'Producción de contenidos, campañas de publicidad y gestión de redes',
                    'Formación más allá de la sesión de entrega',
                    'Cambios de alcance o rediseños después de la aprobación',
                    'Asesoramiento fiscal, legal o de protección de datos',
                ],
                'client_defaults' => [
                    'Una persona de contacto para decisiones, contenidos y aprobaciones',
                    'Logotipo y materiales de marca, si ya existen',
                    'Textos, imágenes y datos a incorporar',
                    'Acceso a dominio, DNS y cuentas de correo implicadas',
                    'Credenciales de prueba de los servicios a conectar (pagos, correo, ERP)',
                    'Respuestas sobre interfaces y entregas en los plazos acordados',
                ],
                'client_deploy' => 'Acceso operativo a la plataforma de publicación indicada: %s',
                'next_steps' => 'Próximos pasos',
                'next_steps_items' => [
                    'Confirmación por escrito de la oferta dentro de su validez',
                    'Arranque, recogida de accesos y aprobación del alcance',
                    'Inicio del calendario de entrega',
                ],
                'signoff' => 'Aceptación',
                'supplier' => 'Proveedor',
                'client' => 'Cliente',
                'date_sign' => 'Fecha',
                'disclaimer' => 'Oferta comercial generada con Larapilot a partir del documento de proyecto y de las estimaciones de trabajo. Importes y fechas son una planificación y pasan a ser vinculantes con la firma.',
            ],
            'fr' => [
                'infrastructure' => [
                    'title' => 'Infrastructure et mise en production',
                    'intro' => 'Où vit le logiciel et qui le maintient en état. Ce sont des décisions prises lors de l\'analyse et reprises ici intégralement : aucune surprise après la mise en ligne.',
                    'platform' => 'Plateforme d\'hébergement',
                    'management' => 'Gestion du serveur',
                    'ops' => 'Responsable de l\'exploitation',
                    'backups' => 'Sauvegardes',
                    'monitoring' => 'Supervision',
                    'tls' => 'Certificats et domaine',
                    'support' => 'Plage de support',
                    'cost' => 'Coût d\'exploitation estimé',
                    'cost_value' => '%s par mois',
                    'cost_note' => 'Le coût d\'exploitation est une estimation : il est facturé directement par l\'hébergeur et n\'est pas compris dans l\'investissement de réalisation.',
                    'ownership' => 'L\'environnement, le domaine et les données restent au nom du client : les accès peuvent être transférés à un autre prestataire à tout moment, sans rien réécrire.',
                    'open' => 'La gestion du serveur n\'est pas encore arrêtée : elle doit l\'être avant la mise en production, car elle détermine sauvegardes, supervision et délais d\'intervention.',
                    'backups_us' => 'Sauvegardes automatiques quotidiennes avec test de restauration périodique',
                    'backups_platform' => 'Sauvegardes assurées par la plateforme, plus export périodique des données applicatives',
                    'backups_client' => 'À la charge de la DSI du client',
                    'monitoring_us' => 'Contrôle de disponibilité et alerte sur les erreurs applicatives',
                    'monitoring_platform' => 'Contrôle de disponibilité sur la plateforme et alerte sur les erreurs applicatives',
                    'monitoring_client' => 'À la charge de la DSI du client',
                    'tls_us' => 'Certificat TLS renouvelé automatiquement ; domaine au nom du client',
                    'tls_platform' => 'Certificat TLS géré par la plateforme ; domaine au nom du client',
                    'tls_client' => 'À la charge de la DSI du client',
                ],
                'assurance' => [
                    'title' => 'Sécurité et qualité : ce qui protège cet investissement',
                    'intro' => 'Un logiciel se paie une fois et se maintient pendant des années. Ce qui décide du coût de cette maintenance — et du risque qu\'un matin il devienne un problème — ne se voit pas dans la démonstration : cela se voit dans la façon dont il a été construit. Voici ce qui est compris, sans ligne séparée au devis.',
                    'close' => 'Aucune dépendance : le code, les données et l\'infrastructure appartiennent au client. Si le projet passe un jour à une autre équipe, il passe avec son historique, ses tests et sa documentation — pas comme une énigme à déchiffrer.',
                    'review' => '**Chaque modification passe par une relecture indépendante** avant d\'atteindre votre environnement. Personne ne publie son propre travail sans qu\'il ait été lu par quelqu\'un d\'autre.',
                    'criteria' => '**Chaque fonctionnalité dispose de critères d\'acceptation écrits avant son développement** et est vérifiée par rapport à eux. « Ça marche » n\'est pas une opinion : c\'est une liste de conditions remplies.',
                    'tests_best' => '**Suite de tests automatisés étendue, exécutée à chaque modification.** C\'est ce qui empêche une demande d\'aujourd\'hui de casser ce qui fonctionnait hier : la vérification ne repose sur la mémoire de personne.',
                    'tests_normal' => '**Tests automatisés sur les parcours critiques, exécutés à chaque modification.** Ce qui, en cassant, vous arrêterait est couvert par des contrôles qui tournent seuls.',
                    'tests_light' => '**Tests automatisés sur les parcours critiques** — connexion, paiements, fonctions centrales — et vérification fonctionnelle de chaque livraison au regard des critères d\'acceptation convenus.',
                    'scan' => '**Analyse de sécurité automatique à chaque modification.** Les vulnérabilités connues sont interceptées avant la mise en ligne, pas après un incident.',
                    'owasp' => '**Sécurité applicative selon les pratiques OWASP** : gestion des identifiants, protection des formulaires, contrôle des accès et des habilitations vérifiés avant livraison.',
                    'secrets' => '**Aucun identifiant dans le code.** Clés et mots de passe vivent dans la configuration de l\'environnement, sous votre contrôle, et peuvent être renouvelés sans toucher au logiciel.',
                    'gitflow' => '**Historique traçable.** Chaque ligne de code est reliée à la demande qui l\'a produite : des mois plus tard, on peut reconstituer ce qui a changé, quand et pourquoi.',
                    'releases' => '**Versions numérotées avec liste des modifications.** Si une mise à jour pose problème, on revient à la précédente en quelques minutes, pas en une journée.',
                    'updates' => '**Mises à jour de sécurité du framework et des bibliothèques** comprises dans la maintenance, et non reportées jusqu\'à la prochaine urgence.',
                    'privacy' => '**Traitement des données personnelles conforme au RGPD** : base légale, durées de conservation, mentions d\'information et droits des personnes prévus dès la conception.',
                ],
                'document_title' => 'Offre commerciale',
                'project' => 'Projet',
                'reference' => 'Référence',
                'date' => 'Date',
                'validity' => 'Validité',
                'validity_value' => '15 jours à compter de la date du document',
                'summary' => 'Synthèse de l\'offre',
                'summary_body' => 'Cette offre décrit la réalisation de %s. La charge est estimée à %s, pour une durée indicative de %s à compter de l\'acceptation. L\'investissement de réalisation est de %s, avec une maintenance annuelle optionnelle de %s.',
                'objectives' => 'Objectifs',
                'deliverables' => 'Ce que nous livrons',
                'deliverables_intro' => 'Le projet livre les fonctionnalités suivantes :',
                'deliverables_empty' => 'Le périmètre suit le document de projet partagé ; les fonctionnalités seront détaillées au lancement.',
                'tech_note' => 'Réalisation sur mesure en technologie Laravel, publiée sur %s. Le code, les données et les accès restent la propriété du client.',
                'tech_note_generic' => 'Réalisation sur mesure en technologie Laravel. Le code, les données et les accès restent la propriété du client.',
                'investment' => 'Investissement',
                'item' => 'Poste',
                'amount' => 'Montant',
                'build' => 'Réalisation (hors taxes)',
                'discount' => 'Remise commerciale',
                'discounted' => 'Montant remisé (HT)',
                'vat' => 'TVA',
                'vat_exempt' => 'TVA non applicable',
                'vat_reverse' => 'TVA — autoliquidation UE B2B',
                'total' => 'Total',
                'maintenance_year' => 'Maintenance annuelle (optionnelle)',
                'effort_note' => 'Charge estimée : **%s**, calendrier indicatif **%s**, marge de gestion et de recette incluse.',
                'timeline' => 'Délais de livraison',
                'timeline_intro' => 'Durée indicative de **%s** pour une charge de **%s**. Les dates courent à partir de l\'acceptation de l\'offre et s\'adaptent aux délais de retour et aux périodes de fermeture.',
                'phase' => 'Phase',
                'start' => 'Début',
                'end' => 'Fin',
                'days' => 'Jours',
                'phase_kickoff' => 'Lancement et analyse',
                'phase_design' => 'Validation du design',
                'phase_build' => 'Réalisation',
                'phase_qa' => 'Recette et vérification',
                'phase_launch' => 'Mise en ligne et livraison',
                'gantt_title' => 'Plan de livraison',
                'section_start' => 'Lancement',
                'section_build' => 'Réalisation',
                'section_launch' => 'Mise en ligne',
                'payment' => 'Modalités de paiement',
                'milestone' => 'Jalon',
                'share' => 'Part',
                'pay_kickoff' => 'À l\'acceptation / lancement',
                'pay_uat' => 'Au début de la recette',
                'pay_golive' => 'À la mise en ligne',
                'payment_note' => 'Factures à 30 jours date de facture, sauf accord contraire. La mise en ligne intervient après règlement du jalon précédent.',
                'maintenance_section' => 'Maintenance et assistance',
                'maintenance_body' => 'Forfait annuel conseillé : **%s** (environ **%s** par mois), soit %s%% de la réalisation. Il couvre les mises à jour de sécurité, la mise à jour des composants, les corrections mineures et l\'assistance à l\'usage. Les nouvelles fonctionnalités sont chiffrées séparément.',
                'included' => 'Ce qui est inclus',
                'not_included' => 'Ce qui n\'est pas inclus',
                'client_provides' => 'Éléments à fournir par le client',
                'included_defaults' => [
                    'Analyse, conception et réalisation des fonctionnalités listées',
                    'Interfaces partagées et validées avant la réalisation',
                    'Vérification du bon fonctionnement de tout ce qui est livré',
                    'Mise en production sur l\'environnement convenu',
                    'Séance de livraison et support d\'utilisation',
                    'Correction des anomalies signalées pendant 30 jours après la mise en ligne',
                ],
                'excluded_defaults' => [
                    'Fonctionnalités non listées dans cette offre et phases ultérieures',
                    'Hébergement, noms de domaine, licences et services tiers',
                    'Production de contenus, campagnes publicitaires et animation des réseaux',
                    'Formation au-delà de la séance de livraison',
                    'Changements de périmètre ou refonte graphique après validation',
                    'Conseil fiscal, juridique ou en protection des données',
                ],
                'client_defaults' => [
                    'Un interlocuteur unique pour les décisions, contenus et validations',
                    'Logo et éléments de charte graphique, s\'ils existent déjà',
                    'Textes, images et données à intégrer',
                    'Accès au domaine, au DNS et aux boîtes email concernées',
                    'Identifiants de test des services à connecter (paiement, email, ERP)',
                    'Retours sur les interfaces et les livraisons dans les délais convenus',
                ],
                'client_deploy' => 'Accès opérationnel à la plateforme de publication indiquée : %s',
                'next_steps' => 'Prochaines étapes',
                'next_steps_items' => [
                    'Confirmation écrite de l\'offre pendant sa durée de validité',
                    'Lancement, collecte des accès et validation du périmètre',
                    'Démarrage du calendrier de livraison',
                ],
                'signoff' => 'Acceptation',
                'supplier' => 'Prestataire',
                'client' => 'Client',
                'date_sign' => 'Date',
                'disclaimer' => 'Offre commerciale générée avec Larapilot à partir du document de projet et des estimations de charge. Les montants et les dates constituent une planification et deviennent contractuels à la signature.',
            ],
            'de' => [
                'infrastructure' => [
                    'title' => 'Infrastruktur und Betrieb',
                    'intro' => 'Wo die Software lebt und wer sie am Laufen hält. Das wurde in der Analyse festgelegt und wird hier vollständig ausgeschrieben, damit nach dem Go-live nichts mehr auftaucht.',
                    'platform' => 'Hosting-Plattform',
                    'management' => 'Serververwaltung',
                    'ops' => 'Operative Verantwortung',
                    'backups' => 'Backups',
                    'monitoring' => 'Monitoring',
                    'tls' => 'Zertifikate und Domain',
                    'support' => 'Supportfenster',
                    'cost' => 'Geschätzte Betriebskosten',
                    'cost_value' => '%s pro Monat',
                    'cost_note' => 'Die Betriebskosten sind eine Schätzung: Sie werden direkt vom Hosting-Anbieter berechnet und sind nicht Teil der oben genannten Entwicklungsinvestition.',
                    'ownership' => 'Umgebung, Domain und Daten bleiben auf den Kunden eingetragen: Die Zugangsdaten können jederzeit zu einem anderen Anbieter wechseln, ohne dass etwas neu geschrieben werden muss.',
                    'open' => 'Die Serververwaltung ist noch nicht entschieden. Sie muss vor dem Go-live geklärt werden, denn sie bestimmt Backups, Monitoring und wie schnell jemand eingreifen kann.',
                    'backups_us' => 'Automatische tägliche Backups mit regelmäßigem Wiederherstellungstest',
                    'backups_platform' => 'Backups durch die Plattform, dazu regelmäßiger Export der Anwendungsdaten',
                    'backups_client' => 'In der Verantwortung der IT des Kunden',
                    'monitoring_us' => 'Erreichbarkeitsprüfung und Benachrichtigung bei Anwendungsfehlern',
                    'monitoring_platform' => 'Erreichbarkeitsprüfung der Plattform und Benachrichtigung bei Anwendungsfehlern',
                    'monitoring_client' => 'In der Verantwortung der IT des Kunden',
                    'tls_us' => 'TLS-Zertifikat wird automatisch erneuert; die Domain bleibt auf den Kunden eingetragen',
                    'tls_platform' => 'TLS-Zertifikat wird von der Plattform verwaltet; die Domain bleibt auf den Kunden eingetragen',
                    'tls_client' => 'In der Verantwortung der IT des Kunden',
                ],
                'assurance' => [
                    'title' => 'Sicherheit und Qualität: was diese Investition schützt',
                    'intro' => 'Software wird einmal bezahlt und jahrelang genutzt. Was darüber entscheidet, wie teuer diese Jahre werden — und ob sie eines Morgens zum Problem wird — ist in einer Demo unsichtbar: Es steckt darin, wie das Ding gebaut wurde. Alles Folgende ist enthalten, ohne eigene Zeile auf der Preisliste.',
                    'close' => 'Keine Abhängigkeit: Code, Daten und Infrastruktur gehören dem Kunden. Sollte das Projekt je zu einem anderen Team wechseln, wechselt es mit seiner Historie, seinen Tests und seiner Dokumentation — nicht als Rätsel zum Entziffern.',
                    'review' => '**Jede Änderung durchläuft ein unabhängiges Review**, bevor sie Ihre Umgebung erreicht. Niemand liefert die eigene Arbeit ungelesen aus.',
                    'criteria' => '**Jede Funktion hat Abnahmekriterien, die vor dem Bau geschrieben werden**, und wird daran geprüft. „Es funktioniert“ ist hier keine Meinung: Es ist eine Liste abgehakter Bedingungen.',
                    'tests_best' => '**Eine breite automatisierte Testsuite, die bei jeder Änderung läuft.** Genau das verhindert, dass die heutige Anforderung kaputt macht, was gestern funktionierte: Die Prüfung hängt nicht davon ab, dass sich jemand erinnert.',
                    'tests_normal' => '**Automatisierte Tests auf den kritischen Pfaden, bei jeder Änderung ausgeführt.** Die Teile, die Ihre Arbeit lahmlegen würden, sind durch Prüfungen abgedeckt, die von selbst laufen.',
                    'tests_light' => '**Automatisierte Tests auf den kritischen Pfaden** — Anmeldung, Zahlungen, die Funktionen, von denen das Geschäft lebt — dazu eine funktionale Prüfung jeder Lieferung gegen die vereinbarten Abnahmekriterien.',
                    'scan' => '**Ein automatischer Sicherheitsscan bei jeder Änderung.** Bekannte Schwachstellen werden vor der Veröffentlichung gefunden, nicht nachdem jemand sie meldet.',
                    'owasp' => '**Anwendungssicherheit nach OWASP-Praxis**: Umgang mit Zugangsdaten, Formularschutz, Zugriffs- und Rechtekontrolle werden vor jeder Lieferung geprüft.',
                    'secrets' => '**Keine Zugangsdaten im Code.** Schlüssel und Passwörter liegen in der Umgebungskonfiguration, unter Ihrer Kontrolle, und lassen sich wechseln, ohne die Software anzufassen.',
                    'gitflow' => '**Eine nachvollziehbare Historie.** Jede Codezeile hängt an der Anforderung, die sie erzeugt hat: Noch Monate später lässt sich rekonstruieren, was sich wann und warum geändert hat.',
                    'releases' => '**Nummerierte Releases mit einer Liste der Änderungen.** Macht ein Update Ärger, sind Sie in Minuten zurück auf der Vorversion, nicht in einem Tag.',
                    'updates' => '**Sicherheitsupdates für das Framework und seine Bibliotheken** sind Teil der Wartungspauschale und werden nicht bis zum nächsten Notfall aufgeschoben.',
                    'privacy' => '**Personenbezogene Daten nach DSGVO**: Rechtsgrundlage, Aufbewahrungsfristen, Hinweise und Betroffenenrechte von Anfang an mitgeplant.',
                ],
                'document_title' => 'Angebot',
                'project' => 'Projekt',
                'reference' => 'Referenz',
                'date' => 'Datum',
                'validity' => 'Gültigkeit',
                'validity_value' => '15 Tage ab Datum des Dokuments',
                'summary' => 'Zusammenfassung des Angebots',
                'summary_body' => 'Dieses Angebot umfasst die Lieferung von %s. Der Aufwand wird auf %s geschätzt, über rund %s ab Annahme. Die Entwicklungsinvestition beträgt %s, bei optionaler jährlicher Wartung von %s.',
                'objectives' => 'Ziele',
                'deliverables' => 'Was Sie bekommen',
                'deliverables_intro' => 'Das Projekt liefert die folgenden Funktionen:',
                'deliverables_empty' => 'Der Umfang folgt dem gemeinsamen Projektdokument; die Funktionen werden beim Kick-off im Detail aufgelistet.',
                'tech_note' => 'Individuelle Entwicklung auf Laravel, veröffentlicht auf %s. Code, Daten und Zugänge bleiben Eigentum des Kunden.',
                'tech_note_generic' => 'Individuelle Entwicklung auf Laravel. Code, Daten und Zugänge bleiben Eigentum des Kunden.',
                'investment' => 'Investition',
                'item' => 'Position',
                'amount' => 'Betrag',
                'build' => 'Entwicklung (netto)',
                'discount' => 'Kommerzieller Rabatt',
                'discounted' => 'Entwicklung nach Rabatt (netto)',
                'vat' => 'MwSt.',
                'vat_exempt' => 'MwSt. nicht ausgewiesen',
                'vat_reverse' => 'MwSt. — Reverse-Charge EU-B2B',
                'total' => 'Gesamt',
                'maintenance_year' => 'Jährliche Wartung (optional)',
                'effort_note' => 'Geschätzter Aufwand: **%s**, indikativer Zeitraum **%s**, Projekt- und Testpuffer enthalten.',
                'timeline' => 'Lieferplan',
                'timeline_intro' => 'Indikative Dauer von **%s** bei **%s** Arbeit. Die Termine beginnen mit der Angebotsannahme und verschieben sich mit Rückmeldezeiten und Betriebsferien.',
                'phase' => 'Phase',
                'start' => 'Beginn',
                'end' => 'Ende',
                'days' => 'Tage',
                'phase_kickoff' => 'Kick-off und Analyse',
                'phase_design' => 'Design-Freigabe',
                'phase_build' => 'Entwicklung',
                'phase_qa' => 'Test und Validierung',
                'phase_launch' => 'Go-live und Übergabe',
                'gantt_title' => 'Lieferplan',
                'section_start' => 'Start',
                'section_build' => 'Entwicklung',
                'section_launch' => 'Release',
                'payment' => 'Zahlungsbedingungen',
                'milestone' => 'Meilenstein',
                'share' => 'Anteil',
                'pay_kickoff' => 'Bei Annahme / Kick-off',
                'pay_uat' => 'Zu Testbeginn',
                'pay_golive' => 'Beim Go-live',
                'payment_note' => 'Rechnungen sind, sofern nicht anders vereinbart, 30 Tage ab Rechnungsdatum fällig. Das Go-live erfolgt nach Ausgleich der vorherigen Rate.',
                'maintenance_section' => 'Wartung und Support',
                'maintenance_body' => 'Vorgeschlagene Jahrespauschale: **%s** (etwa **%s** pro Monat), %s%% der Entwicklung. Sie deckt Sicherheitsupdates, Aktualisierung der Abhängigkeiten, kleinere Korrekturen und Anwendungsunterstützung. Neue Funktionen werden separat angeboten.',
                'included' => 'Enthalten',
                'not_included' => 'Nicht enthalten',
                'client_provides' => 'Was der Kunde beisteuert',
                'included_defaults' => [
                    'Analyse, Design und Entwicklung der aufgeführten Funktionen',
                    'Vor der Entwicklung geteilte und freigegebene Oberflächen',
                    'Funktionale Prüfung aller Lieferungen',
                    'Veröffentlichung in der vereinbarten Produktivumgebung',
                    'Übergabetermin und Anwendungsunterlagen',
                    'Behebung von Mängeln, die innerhalb von 30 Tagen nach Go-live gemeldet werden',
                ],
                'excluded_defaults' => [
                    'Funktionen, die in diesem Angebot nicht aufgeführt sind, und spätere Phasen',
                    'Hosting, Domains, Lizenzen und Dienste Dritter',
                    'Erstellung von Inhalten, Werbekampagnen und Social-Media-Betreuung',
                    'Schulungen über den Übergabetermin hinaus',
                    'Umfangsänderungen oder visuelle Überarbeitung nach der Freigabe',
                    'Steuer-, Rechts- oder Datenschutzberatung',
                ],
                'client_defaults' => [
                    'Eine Ansprechperson für Entscheidungen, Inhalte und Freigaben',
                    'Logo und Markenmaterial, soweit bereits vorhanden',
                    'Texte, Bilder und die zu ladenden Daten',
                    'Zugang zu Domain, DNS und den betroffenen Postfächern',
                    'Testzugänge für die anzubindenden Dienste (Zahlung, E-Mail, ERP)',
                    'Rückmeldungen zu Oberflächen und Lieferungen innerhalb der vereinbarten Frist',
                ],
                'client_deploy' => 'Operativer Zugang zur genannten Veröffentlichungsplattform: %s',
                'next_steps' => 'Nächste Schritte',
                'next_steps_items' => [
                    'Schriftliche Annahme dieses Angebots innerhalb seiner Gültigkeit',
                    'Kick-off, Sammlung der Zugänge und Freigabe des Umfangs',
                    'Start des Lieferkalenders',
                ],
                'signoff' => 'Annahme',
                'supplier' => 'Auftragnehmer',
                'client' => 'Kunde',
                'date_sign' => 'Datum',
                'disclaimer' => 'Angebot, erzeugt mit Larapilot aus dem Projektdokument und den Aufwandsschätzungen. Beträge und Termine sind Planungsgrößen und werden mit der Unterschrift verbindlich.',
            ],
            'pt' => [
                'infrastructure' => [
                    'title' => 'Infraestrutura e alojamento',
                    'intro' => 'Onde vive o software e quem o mantém a funcionar. Foi decidido durante a análise e está aqui escrito por inteiro, para que nada apareça depois do arranque.',
                    'platform' => 'Plataforma de alojamento',
                    'management' => 'Gestão do servidor',
                    'ops' => 'Responsabilidade operacional',
                    'backups' => 'Cópias de segurança',
                    'monitoring' => 'Monitorização',
                    'tls' => 'Certificados e domínio',
                    'support' => 'Janela de suporte',
                    'cost' => 'Custo de exploração estimado',
                    'cost_value' => '%s por mês',
                    'cost_note' => 'O custo de exploração é uma estimativa: é faturado diretamente pelo fornecedor de alojamento e não faz parte do investimento de desenvolvimento acima.',
                    'ownership' => 'O ambiente, o domínio e os dados ficam em nome do cliente: as credenciais podem passar para outro fornecedor a qualquer momento sem reescrever nada.',
                    'open' => 'A gestão do servidor ainda não foi decidida. Tem de ficar resolvida antes do arranque, porque determina cópias de segurança, monitorização e a rapidez com que alguém pode intervir.',
                    'backups_us' => 'Cópias de segurança diárias automáticas com teste periódico de reposição',
                    'backups_platform' => 'Cópias de segurança tratadas pela plataforma, mais exportação periódica dos dados da aplicação',
                    'backups_client' => 'A cargo da equipa de TI do cliente',
                    'monitoring_us' => 'Verificação de disponibilidade e alertas de erros da aplicação',
                    'monitoring_platform' => 'Verificação de disponibilidade da plataforma e alertas de erros da aplicação',
                    'monitoring_client' => 'A cargo da equipa de TI do cliente',
                    'tls_us' => 'Certificado TLS renovado automaticamente; o domínio fica em nome do cliente',
                    'tls_platform' => 'Certificado TLS tratado pela plataforma; o domínio fica em nome do cliente',
                    'tls_client' => 'A cargo da equipa de TI do cliente',
                ],
                'assurance' => [
                    'title' => 'Segurança e qualidade: o que protege este investimento',
                    'intro' => 'O software paga-se uma vez e vive-se com ele durante anos. O que decide quanto custam esses anos — e se numa manhã se transforma num problema — é invisível numa demonstração: está em como a coisa foi construída. Tudo o que se segue está incluído, sem linha separada na tabela de preços.',
                    'close' => 'Sem dependência: o código, os dados e a infraestrutura pertencem ao cliente. Se o projeto alguma vez passar para outra equipa, passa com o seu histórico, os seus testes e a sua documentação — não como um enigma a decifrar.',
                    'review' => '**Cada alteração passa por uma revisão independente** antes de chegar ao seu ambiente. Ninguém publica o próprio trabalho sem que outra pessoa o leia.',
                    'criteria' => '**Cada funcionalidade tem critérios de aceitação escritos antes de ser construída** e é verificada contra eles. «Funciona» aqui não é uma opinião: é uma lista de condições assinaladas.',
                    'tests_best' => '**Um conjunto alargado de testes automáticos, executado em cada alteração.** É isto que impede que o pedido de hoje parta o que funcionava ontem: a verificação não depende de alguém se lembrar.',
                    'tests_normal' => '**Testes automáticos nos percursos críticos, executados em cada alteração.** As partes que parariam o seu trabalho se falhassem estão cobertas por verificações que correm sozinhas.',
                    'tests_light' => '**Testes automáticos nos percursos críticos** — entrada na aplicação, pagamentos, as funções de que o negócio vive — mais verificação funcional de cada entrega contra os critérios de aceitação acordados.',
                    'scan' => '**Uma análise automática de segurança em cada alteração.** As vulnerabilidades conhecidas são apanhadas antes da publicação, não depois de alguém as reportar.',
                    'owasp' => '**Segurança aplicacional segundo a prática OWASP**: tratamento de credenciais, proteção de formulários, controlo de acessos e permissões revistos antes de cada entrega.',
                    'secrets' => '**Sem credenciais dentro do código.** Chaves e palavras-passe vivem na configuração do ambiente, sob o seu controlo, e podem ser trocadas sem tocar no software.',
                    'gitflow' => '**Um histórico rastreável.** Cada linha de código está ligada ao pedido que a originou: meses depois consegue reconstruir o que mudou, quando e porquê.',
                    'releases' => '**Versões numeradas com a lista do que mudou.** Se uma atualização der problemas, volta-se à versão anterior em minutos, não num dia.',
                    'updates' => '**As atualizações de segurança da framework e das suas bibliotecas** fazem parte da avença de manutenção e não ficam adiadas até à próxima emergência.',
                    'privacy' => '**Dados pessoais tratados de acordo com o RGPD**: fundamento legal, prazos de conservação, informações e direitos dos titulares pensados desde o início.',
                ],
                'document_title' => 'Proposta comercial',
                'project' => 'Projeto',
                'reference' => 'Referência',
                'date' => 'Data',
                'validity' => 'Validade',
                'validity_value' => '15 dias a contar da data do documento',
                'summary' => 'Resumo da proposta',
                'summary_body' => 'Esta proposta cobre a entrega de %s. O trabalho está estimado em %s, ao longo de cerca de %s a contar da aceitação. O investimento de desenvolvimento é de %s, com manutenção anual opcional de %s.',
                'objectives' => 'Objetivos',
                'deliverables' => 'O que recebe',
                'deliverables_intro' => 'O projeto entrega as seguintes funcionalidades:',
                'deliverables_empty' => 'O âmbito segue o documento de projeto partilhado; as funcionalidades serão listadas em detalhe no arranque.',
                'tech_note' => 'Desenvolvimento à medida em Laravel, publicado em %s. Código, dados e acessos continuam propriedade do cliente.',
                'tech_note_generic' => 'Desenvolvimento à medida em Laravel. Código, dados e acessos continuam propriedade do cliente.',
                'investment' => 'Investimento',
                'item' => 'Rubrica',
                'amount' => 'Valor',
                'build' => 'Desenvolvimento (sem IVA)',
                'discount' => 'Desconto comercial',
                'discounted' => 'Desenvolvimento com desconto (sem IVA)',
                'vat' => 'IVA',
                'vat_exempt' => 'IVA não aplicado',
                'vat_reverse' => 'IVA — autoliquidação intracomunitária B2B',
                'total' => 'Total',
                'maintenance_year' => 'Manutenção anual (opcional)',
                'effort_note' => 'Esforço estimado: **%s**, calendário indicativo **%s**, margem de projeto e testes incluída.',
                'timeline' => 'Calendário de entrega',
                'timeline_intro' => 'Duração indicativa de **%s** sobre **%s** de trabalho. As datas começam na aceitação da proposta e ajustam-se ao tempo de resposta e a períodos de encerramento.',
                'phase' => 'Fase',
                'start' => 'Início',
                'end' => 'Fim',
                'days' => 'Dias',
                'phase_kickoff' => 'Arranque e análise',
                'phase_design' => 'Aprovação do design',
                'phase_build' => 'Desenvolvimento',
                'phase_qa' => 'Testes e validação',
                'phase_launch' => 'Entrada em produção e entrega',
                'gantt_title' => 'Plano de entrega',
                'section_start' => 'Início',
                'section_build' => 'Desenvolvimento',
                'section_launch' => 'Lançamento',
                'payment' => 'Condições de pagamento',
                'milestone' => 'Marco',
                'share' => 'Parcela',
                'pay_kickoff' => 'Na aceitação / arranque',
                'pay_uat' => 'No início dos testes',
                'pay_golive' => 'Na entrada em produção',
                'payment_note' => 'Faturas a 30 dias da data de emissão salvo acordo em contrário. A entrada em produção segue a liquidação da prestação anterior.',
                'maintenance_section' => 'Manutenção e suporte',
                'maintenance_body' => 'Avença anual sugerida: **%s** (cerca de **%s** por mês), %s%% do desenvolvimento. Cobre atualizações de segurança, atualização de dependências, pequenas correções e apoio à utilização. Novas funcionalidades são orçamentadas à parte.',
                'included' => 'Incluído',
                'not_included' => 'Não incluído',
                'client_provides' => 'O que o cliente fornece',
                'included_defaults' => [
                    'Análise, design e desenvolvimento das funcionalidades listadas',
                    'Interfaces partilhadas e aprovadas antes do desenvolvimento',
                    'Verificação funcional de tudo o que é entregue',
                    'Publicação no ambiente de produção acordado',
                    'Sessão de entrega e material de utilização',
                    'Correção de defeitos reportados até 30 dias após a entrada em produção',
                ],
                'excluded_defaults' => [
                    'Funcionalidades não listadas nesta proposta e fases posteriores',
                    'Alojamento, domínios, licenças e serviços de terceiros',
                    'Produção de conteúdos, campanhas publicitárias e gestão de redes sociais',
                    'Formação para além da sessão de entrega',
                    'Alterações de âmbito ou redesenho visual após aprovação',
                    'Aconselhamento fiscal, jurídico ou de proteção de dados',
                ],
                'client_defaults' => [
                    'Um ponto de contacto para decisões, conteúdos e aprovações',
                    'Logótipo e elementos de marca, quando já existirem',
                    'Textos, imagens e os dados a carregar',
                    'Acesso ao domínio, ao DNS e às caixas de correio envolvidas',
                    'Credenciais de teste dos serviços a ligar (pagamentos, email, ERP)',
                    'Retorno sobre interfaces e entregas dentro do prazo acordado',
                ],
                'client_deploy' => 'Acesso operacional à plataforma de publicação indicada: %s',
                'next_steps' => 'Próximos passos',
                'next_steps_items' => [
                    'Aceitação por escrito desta proposta dentro da sua validade',
                    'Arranque, recolha de acessos e fecho do âmbito',
                    'Início do calendário de entrega',
                ],
                'signoff' => 'Aceitação',
                'supplier' => 'Fornecedor',
                'client' => 'Cliente',
                'date_sign' => 'Data',
                'disclaimer' => 'Proposta comercial gerada com o Larapilot a partir do documento de projeto e das estimativas de esforço. Valores e datas são números de planeamento e tornam-se vinculativos com a assinatura.',
            ],
            'nl' => [
                'infrastructure' => [
                    'title' => 'Infrastructuur en hosting',
                    'intro' => 'Waar de software draait en wie hem in de lucht houdt. Dit is tijdens de analyse vastgelegd en staat hier volledig uitgeschreven, zodat er na livegang niets meer opduikt.',
                    'platform' => 'Hostingplatform',
                    'management' => 'Serverbeheer',
                    'ops' => 'Operationeel eigenaarschap',
                    'backups' => 'Back-ups',
                    'monitoring' => 'Monitoring',
                    'tls' => 'Certificaten en domein',
                    'support' => 'Supportvenster',
                    'cost' => 'Geschatte exploitatiekosten',
                    'cost_value' => '%s per maand',
                    'cost_note' => 'De exploitatiekosten zijn een schatting: ze worden rechtstreeks door de hostingpartij gefactureerd en maken geen deel uit van de bouwinvestering hierboven.',
                    'ownership' => 'De omgeving, het domein en de data blijven op naam van de klant: de toegang kan op elk moment naar een andere leverancier verhuizen zonder dat er iets herschreven hoeft te worden.',
                    'open' => 'Het serverbeheer is nog niet besloten. Dat moet vóór livegang geregeld zijn, want het bepaalt de back-ups, de monitoring en hoe snel iemand kan ingrijpen.',
                    'backups_us' => 'Automatische dagelijkse back-ups met periodieke hersteltest',
                    'backups_platform' => 'Back-ups verzorgd door het platform, plus periodieke export van de applicatiegegevens',
                    'backups_client' => 'Onder beheer van de IT-afdeling van de klant',
                    'monitoring_us' => 'Bereikbaarheidscontrole en melding van applicatiefouten',
                    'monitoring_platform' => 'Bereikbaarheidscontrole van het platform en melding van applicatiefouten',
                    'monitoring_client' => 'Onder beheer van de IT-afdeling van de klant',
                    'tls_us' => 'TLS-certificaat wordt automatisch vernieuwd; het domein blijft op naam van de klant',
                    'tls_platform' => 'TLS-certificaat wordt door het platform beheerd; het domein blijft op naam van de klant',
                    'tls_client' => 'Onder beheer van de IT-afdeling van de klant',
                ],
                'assurance' => [
                    'title' => 'Beveiliging en kwaliteit: wat deze investering beschermt',
                    'intro' => 'Software wordt één keer betaald en jarenlang gebruikt. Wat bepaalt hoeveel die jaren kosten — en of het op een ochtend een probleem wordt — is onzichtbaar in een demo: het zit in hoe het ding gebouwd is. Alles hieronder is inbegrepen, zonder aparte regel op de prijslijst.',
                    'close' => 'Geen lock-in: de code, de data en de infrastructuur zijn van de klant. Verhuist het project ooit naar een ander team, dan gaat het mee met zijn historie, zijn tests en zijn documentatie — niet als puzzel om te ontcijferen.',
                    'review' => '**Elke wijziging gaat door een onafhankelijke review** voordat hij jouw omgeving bereikt. Niemand levert zijn eigen werk ongelezen op.',
                    'criteria' => '**Elke functie heeft acceptatiecriteria die vóór de bouw worden geschreven** en wordt daaraan getoetst. „Het werkt” is hier geen mening: het is een lijst afgevinkte voorwaarden.',
                    'tests_best' => '**Een brede geautomatiseerde testsuite, bij elke wijziging uitgevoerd.** Dat is wat voorkomt dat het verzoek van vandaag stukmaakt wat gisteren werkte: de controle hangt er niet van af of iemand eraan denkt.',
                    'tests_normal' => '**Geautomatiseerde tests op de kritieke paden, bij elke wijziging uitgevoerd.** De onderdelen die je werk zouden stilleggen als ze het begeven, zijn gedekt door controles die zichzelf uitvoeren.',
                    'tests_light' => '**Geautomatiseerde tests op de kritieke paden** — inloggen, betalingen, de functies waar het bedrijf op draait — plus functionele controle van elke oplevering tegen de afgesproken acceptatiecriteria.',
                    'scan' => '**Een automatische beveiligingsscan bij elke wijziging.** Bekende kwetsbaarheden worden vóór publicatie gevonden, niet nadat iemand ze meldt.',
                    'owasp' => '**Applicatiebeveiliging volgens OWASP-praktijk**: omgang met inloggegevens, formulierbeveiliging, toegangs- en rechtencontrole worden vóór elke oplevering nagelopen.',
                    'secrets' => '**Geen inloggegevens in de code.** Sleutels en wachtwoorden staan in de omgevingsconfiguratie, onder jouw beheer, en kunnen worden vervangen zonder de software aan te raken.',
                    'gitflow' => '**Een navolgbare historie.** Elke regel code hangt aan het verzoek dat hem opleverde: maanden later valt te reconstrueren wat er veranderde, wanneer en waarom.',
                    'releases' => '**Genummerde releases met een lijst van wat er veranderde.** Geeft een update problemen, dan sta je in minuten terug op de vorige versie, niet in een dag.',
                    'updates' => '**Beveiligingsupdates voor het framework en zijn bibliotheken** horen bij het onderhoudsabonnement en worden niet uitgesteld tot het volgende noodgeval.',
                    'privacy' => '**Persoonsgegevens verwerkt conform de AVG**: grondslag, bewaartermijnen, informatieplicht en rechten van betrokkenen vanaf het begin meegenomen.',
                ],
                'document_title' => 'Offerte',
                'project' => 'Project',
                'reference' => 'Kenmerk',
                'date' => 'Datum',
                'validity' => 'Geldigheid',
                'validity_value' => '15 dagen vanaf de datum van dit document',
                'summary' => 'Samenvatting van de offerte',
                'summary_body' => 'Deze offerte betreft de oplevering van %s. Het werk is geraamd op %s, over ongeveer %s vanaf akkoord. De bouwinvestering bedraagt %s, met optioneel jaarlijks onderhoud van %s.',
                'objectives' => 'Doelen',
                'deliverables' => 'Wat je krijgt',
                'deliverables_intro' => 'Het project levert de volgende functionaliteit:',
                'deliverables_empty' => 'De scope volgt het gedeelde projectdocument; de functionaliteit wordt bij de kick-off in detail opgesomd.',
                'tech_note' => 'Maatwerk op Laravel, gepubliceerd op %s. Code, data en toegang blijven eigendom van de klant.',
                'tech_note_generic' => 'Maatwerk op Laravel. Code, data en toegang blijven eigendom van de klant.',
                'investment' => 'Investering',
                'item' => 'Post',
                'amount' => 'Bedrag',
                'build' => 'Bouw (excl. btw)',
                'discount' => 'Commerciële korting',
                'discounted' => 'Bouw na korting (excl. btw)',
                'vat' => 'Btw',
                'vat_exempt' => 'Btw niet van toepassing',
                'vat_reverse' => 'Btw — verlegd, EU B2B',
                'total' => 'Totaal',
                'maintenance_year' => 'Jaarlijks onderhoud (optioneel)',
                'effort_note' => 'Geraamde inspanning: **%s**, indicatieve doorlooptijd **%s**, project- en testbuffer inbegrepen.',
                'timeline' => 'Opleverplanning',
                'timeline_intro' => 'Indicatieve duur van **%s** bij **%s** werk. De data starten bij akkoord op de offerte en schuiven mee met reactietijden en sluitingsperiodes.',
                'phase' => 'Fase',
                'start' => 'Start',
                'end' => 'Einde',
                'days' => 'Dagen',
                'phase_kickoff' => 'Kick-off en analyse',
                'phase_design' => 'Akkoord op ontwerp',
                'phase_build' => 'Bouw',
                'phase_qa' => 'Test en validatie',
                'phase_launch' => 'Livegang en overdracht',
                'gantt_title' => 'Opleverplan',
                'section_start' => 'Start',
                'section_build' => 'Bouw',
                'section_launch' => 'Release',
                'payment' => 'Betalingsvoorwaarden',
                'milestone' => 'Mijlpaal',
                'share' => 'Deel',
                'pay_kickoff' => 'Bij akkoord / kick-off',
                'pay_uat' => 'Bij start van de tests',
                'pay_golive' => 'Bij livegang',
                'payment_note' => 'Facturen zijn, tenzij anders afgesproken, 30 dagen na factuurdatum verschuldigd. Livegang volgt op voldoening van de vorige termijn.',
                'maintenance_section' => 'Onderhoud en support',
                'maintenance_body' => 'Voorgesteld jaarabonnement: **%s** (ongeveer **%s** per maand), %s%% van de bouw. Het dekt beveiligingsupdates, upgrades van afhankelijkheden, kleine correcties en gebruiksondersteuning. Nieuwe functies worden apart geoffreerd.',
                'included' => 'Inbegrepen',
                'not_included' => 'Niet inbegrepen',
                'client_provides' => 'Wat de klant aanlevert',
                'included_defaults' => [
                    'Analyse, ontwerp en bouw van de genoemde functionaliteit',
                    'Schermen die vóór de bouw gedeeld en goedgekeurd worden',
                    'Functionele controle van alles wat wordt opgeleverd',
                    'Publicatie naar de afgesproken productieomgeving',
                    'Overdrachtssessie en gebruiksmateriaal',
                    'Herstel van gebreken die binnen 30 dagen na livegang worden gemeld',
                ],
                'excluded_defaults' => [
                    'Functionaliteit die niet in deze offerte staat, en latere fases',
                    'Hosting, domeinen, licenties en diensten van derden',
                    'Contentproductie, advertentiecampagnes en socialmediabeheer',
                    'Training buiten de overdrachtssessie',
                    'Scopewijzigingen of visueel herontwerp na goedkeuring',
                    'Fiscaal, juridisch of privacyadvies',
                ],
                'client_defaults' => [
                    'Eén aanspreekpunt voor beslissingen, content en goedkeuringen',
                    'Logo en merkmateriaal, voor zover al aanwezig',
                    'Teksten, beeldmateriaal en de te laden gegevens',
                    'Toegang tot het domein, de DNS en de betrokken mailboxen',
                    'Testtoegang voor de te koppelen diensten (betalingen, e-mail, ERP)',
                    'Terugkoppeling op schermen en opleveringen binnen de afgesproken termijn',
                ],
                'client_deploy' => 'Operationele toegang tot het genoemde publicatieplatform: %s',
                'next_steps' => 'Vervolgstappen',
                'next_steps_items' => [
                    'Schriftelijk akkoord op deze offerte binnen de geldigheidstermijn',
                    'Kick-off, verzamelen van toegangen en vastleggen van de scope',
                    'Start van de opleverkalender',
                ],
                'signoff' => 'Akkoord',
                'supplier' => 'Leverancier',
                'client' => 'Klant',
                'date_sign' => 'Datum',
                'disclaimer' => 'Offerte gegenereerd met Larapilot op basis van het projectdocument en de inspanningsramingen. Bedragen en data zijn planningscijfers en worden bindend na ondertekening.',
            ],
            'pl' => [
                'infrastructure' => [
                    'title' => 'Infrastruktura i hosting',
                    'intro' => 'Gdzie mieszka oprogramowanie i kto utrzymuje je przy życiu. Zostało to ustalone na etapie analizy i jest tu rozpisane w całości, żeby po uruchomieniu nic nie wyszło na jaw.',
                    'platform' => 'Platforma hostingowa',
                    'management' => 'Zarządzanie serwerem',
                    'ops' => 'Odpowiedzialność operacyjna',
                    'backups' => 'Kopie zapasowe',
                    'monitoring' => 'Monitoring',
                    'tls' => 'Certyfikaty i domena',
                    'support' => 'Okno wsparcia',
                    'cost' => 'Szacowany koszt eksploatacji',
                    'cost_value' => '%s miesięcznie',
                    'cost_note' => 'Koszt eksploatacji jest szacunkiem: fakturuje go bezpośrednio dostawca hostingu i nie wchodzi w skład powyższej inwestycji w budowę.',
                    'ownership' => 'Środowisko, domena i dane pozostają zapisane na klienta: dane dostępowe mogą w każdej chwili przejść do innego dostawcy bez przepisywania czegokolwiek.',
                    'open' => 'Zarządzanie serwerem nie zostało jeszcze rozstrzygnięte. Trzeba to ustalić przed uruchomieniem, bo decyduje o kopiach zapasowych, monitoringu i tym, jak szybko ktoś może zareagować.',
                    'backups_us' => 'Automatyczne codzienne kopie zapasowe z okresowym testem odtworzenia',
                    'backups_platform' => 'Kopie zapasowe po stronie platformy oraz okresowy eksport danych aplikacji',
                    'backups_client' => 'Po stronie działu IT klienta',
                    'monitoring_us' => 'Kontrola dostępności i powiadomienia o błędach aplikacji',
                    'monitoring_platform' => 'Kontrola dostępności platformy i powiadomienia o błędach aplikacji',
                    'monitoring_client' => 'Po stronie działu IT klienta',
                    'tls_us' => 'Certyfikat TLS odnawiany automatycznie; domena pozostaje zapisana na klienta',
                    'tls_platform' => 'Certyfikat TLS obsługiwany przez platformę; domena pozostaje zapisana na klienta',
                    'tls_client' => 'Po stronie działu IT klienta',
                ],
                'assurance' => [
                    'title' => 'Bezpieczeństwo i jakość: co chroni tę inwestycję',
                    'intro' => 'Za oprogramowanie płaci się raz, a żyje się z nim latami. To, co decyduje, ile kosztują te lata — i czy pewnego ranka stanie się problemem — jest niewidoczne na prezentacji: siedzi w tym, jak rzecz została zbudowana. Wszystko poniżej jest wliczone, bez osobnej pozycji w cenniku.',
                    'close' => 'Bez uzależnienia od dostawcy: kod, dane i infrastruktura należą do klienta. Jeśli projekt kiedyś przejdzie do innego zespołu, przechodzi ze swoją historią, testami i dokumentacją — a nie jako zagadka do rozszyfrowania.',
                    'review' => '**Każda zmiana przechodzi niezależny przegląd**, zanim trafi do Państwa środowiska. Nikt nie wypuszcza własnej pracy bez cudzego spojrzenia.',
                    'criteria' => '**Każda funkcja ma kryteria akceptacji spisane przed budową** i jest wobec nich sprawdzana. „Działa” nie jest tu opinią: to lista odhaczonych warunków.',
                    'tests_best' => '**Szeroki zestaw testów automatycznych, uruchamiany przy każdej zmianie.** To właśnie powstrzymuje dzisiejsze zgłoszenie przed zepsuciem tego, co działało wczoraj: weryfikacja nie zależy od tego, czy ktoś pamięta.',
                    'tests_normal' => '**Testy automatyczne na ścieżkach krytycznych, uruchamiane przy każdej zmianie.** Fragmenty, których awaria zatrzymałaby Państwa pracę, są objęte kontrolami, które uruchamiają się same.',
                    'tests_light' => '**Testy automatyczne na ścieżkach krytycznych** — logowanie, płatności, funkcje, na których stoi biznes — plus weryfikacja funkcjonalna każdej dostawy wobec uzgodnionych kryteriów akceptacji.',
                    'scan' => '**Automatyczny skan bezpieczeństwa przy każdej zmianie.** Znane podatności są wychwytywane przed publikacją, a nie po tym, jak ktoś je zgłosi.',
                    'owasp' => '**Bezpieczeństwo aplikacji zgodnie z praktyką OWASP**: obsługa danych dostępowych, ochrona formularzy, kontrola dostępu i uprawnień przeglądane przed każdą dostawą.',
                    'secrets' => '**Żadnych danych dostępowych w kodzie.** Klucze i hasła żyją w konfiguracji środowiska, pod Państwa kontrolą, i można je wymienić bez dotykania oprogramowania.',
                    'gitflow' => '**Historia, którą da się prześledzić.** Każda linia kodu jest powiązana ze zgłoszeniem, które ją wywołało: po miesiącach da się odtworzyć, co się zmieniło, kiedy i dlaczego.',
                    'releases' => '**Numerowane wydania z listą zmian.** Jeśli aktualizacja narobi kłopotów, wraca się do poprzedniej wersji w kilka minut, a nie w dzień.',
                    'updates' => '**Aktualizacje bezpieczeństwa frameworka i jego bibliotek** są częścią abonamentu utrzymaniowego, a nie czymś odkładanym do następnej awarii.',
                    'privacy' => '**Dane osobowe przetwarzane zgodnie z RODO**: podstawa prawna, okresy przechowywania, obowiązki informacyjne i prawa osób zaplanowane od początku.',
                ],
                'document_title' => 'Oferta handlowa',
                'project' => 'Projekt',
                'reference' => 'Numer referencyjny',
                'date' => 'Data',
                'validity' => 'Ważność',
                'validity_value' => '15 dni od daty dokumentu',
                'summary' => 'Podsumowanie oferty',
                'summary_body' => 'Niniejsza oferta obejmuje dostarczenie %s. Pracę oszacowano na %s, w ciągu około %s od akceptacji. Inwestycja w budowę wynosi %s, przy opcjonalnym rocznym utrzymaniu %s.',
                'objectives' => 'Cele',
                'deliverables' => 'Co Państwo otrzymują',
                'deliverables_intro' => 'Projekt dostarcza następujące funkcje:',
                'deliverables_empty' => 'Zakres wynika ze wspólnego dokumentu projektowego; funkcje zostaną szczegółowo wypisane na spotkaniu otwierającym.',
                'tech_note' => 'Rozwiązanie szyte na miarę na Laravelu, publikowane na %s. Kod, dane i dostępy pozostają własnością klienta.',
                'tech_note_generic' => 'Rozwiązanie szyte na miarę na Laravelu. Kod, dane i dostępy pozostają własnością klienta.',
                'investment' => 'Inwestycja',
                'item' => 'Pozycja',
                'amount' => 'Kwota',
                'build' => 'Budowa (netto)',
                'discount' => 'Rabat handlowy',
                'discounted' => 'Budowa po rabacie (netto)',
                'vat' => 'VAT',
                'vat_exempt' => 'VAT nie został naliczony',
                'vat_reverse' => 'VAT — odwrotne obciążenie UE B2B',
                'total' => 'Razem',
                'maintenance_year' => 'Roczne utrzymanie (opcjonalne)',
                'effort_note' => 'Szacowana pracochłonność: **%s**, orientacyjny kalendarz **%s**, bufor projektowy i testowy wliczony.',
                'timeline' => 'Harmonogram dostawy',
                'timeline_intro' => 'Orientacyjny czas trwania **%s** przy **%s** pracy. Daty liczą się od akceptacji oferty i przesuwają się wraz z czasem odpowiedzi oraz okresami przerw.',
                'phase' => 'Faza',
                'start' => 'Początek',
                'end' => 'Koniec',
                'days' => 'Dni',
                'phase_kickoff' => 'Otwarcie i analiza',
                'phase_design' => 'Akceptacja projektu graficznego',
                'phase_build' => 'Budowa',
                'phase_qa' => 'Testy i walidacja',
                'phase_launch' => 'Uruchomienie i przekazanie',
                'gantt_title' => 'Plan dostawy',
                'section_start' => 'Start',
                'section_build' => 'Budowa',
                'section_launch' => 'Wydanie',
                'payment' => 'Warunki płatności',
                'milestone' => 'Kamień milowy',
                'share' => 'Udział',
                'pay_kickoff' => 'Przy akceptacji / otwarciu',
                'pay_uat' => 'Na starcie testów',
                'pay_golive' => 'Przy uruchomieniu',
                'payment_note' => 'Faktury płatne w ciągu 30 dni od daty wystawienia, o ile nie uzgodniono inaczej. Uruchomienie następuje po rozliczeniu poprzedniej raty.',
                'maintenance_section' => 'Utrzymanie i wsparcie',
                'maintenance_body' => 'Proponowany abonament roczny: **%s** (około **%s** miesięcznie), %s%% wartości budowy. Obejmuje aktualizacje bezpieczeństwa, aktualizacje zależności, drobne poprawki i wsparcie w użytkowaniu. Nowe funkcje wyceniane są osobno.',
                'included' => 'W cenie',
                'not_included' => 'Poza ceną',
                'client_provides' => 'Co zapewnia klient',
                'included_defaults' => [
                    'Analiza, projekt i budowa wymienionych funkcji',
                    'Interfejsy przedstawione i zaakceptowane przed budową',
                    'Weryfikacja funkcjonalna wszystkiego, co zostaje dostarczone',
                    'Wdrożenie na uzgodnione środowisko produkcyjne',
                    'Spotkanie przekazujące i materiały dla użytkowników',
                    'Naprawa usterek zgłoszonych w ciągu 30 dni od uruchomienia',
                ],
                'excluded_defaults' => [
                    'Funkcje niewymienione w tej ofercie oraz późniejsze etapy',
                    'Hosting, domeny, licencje i usługi zewnętrzne',
                    'Tworzenie treści, kampanie reklamowe i prowadzenie mediów społecznościowych',
                    'Szkolenia wykraczające poza spotkanie przekazujące',
                    'Zmiany zakresu lub przeprojektowanie wizualne po akceptacji',
                    'Doradztwo podatkowe, prawne lub w zakresie ochrony danych',
                ],
                'client_defaults' => [
                    'Jedna osoba kontaktowa do decyzji, treści i akceptacji',
                    'Logo i materiały marki, o ile już istnieją',
                    'Teksty, grafiki i dane do wgrania',
                    'Dostęp do domeny, DNS i powiązanych skrzynek pocztowych',
                    'Dane testowe do usług, które mają zostać podłączone (płatności, poczta, ERP)',
                    'Informacja zwrotna do interfejsów i dostaw w uzgodnionym terminie',
                ],
                'client_deploy' => 'Dostęp operacyjny do wskazanej platformy publikacji: %s',
                'next_steps' => 'Następne kroki',
                'next_steps_items' => [
                    'Pisemna akceptacja tej oferty w okresie jej ważności',
                    'Otwarcie projektu, zebranie dostępów i zamknięcie zakresu',
                    'Start kalendarza dostawy',
                ],
                'signoff' => 'Akceptacja',
                'supplier' => 'Wykonawca',
                'client' => 'Klient',
                'date_sign' => 'Data',
                'disclaimer' => 'Oferta handlowa wygenerowana przez Larapilot na podstawie dokumentu projektowego i szacunków pracochłonności. Kwoty i daty są wielkościami planistycznymi i stają się wiążące po podpisaniu.',
            ],
            default => [
                'infrastructure' => [
                    'title' => 'Infrastructure and hosting',
                    'intro' => 'Where the software lives and who keeps it running. These were settled during analysis and are written out here in full, so nothing surfaces after go-live.',
                    'platform' => 'Hosting platform',
                    'management' => 'Server management',
                    'ops' => 'Operational ownership',
                    'backups' => 'Backups',
                    'monitoring' => 'Monitoring',
                    'tls' => 'Certificates and domain',
                    'support' => 'Support window',
                    'cost' => 'Estimated running cost',
                    'cost_value' => '%s per month',
                    'cost_note' => 'The running cost is an estimate: it is billed directly by the hosting provider and is not part of the build investment above.',
                    'ownership' => 'The environment, the domain, and the data stay in the client\'s name: credentials can move to another supplier at any time without rewriting anything.',
                    'open' => 'Server management has not been decided yet. It needs settling before go-live, because it determines backups, monitoring, and how fast someone can intervene.',
                    'backups_us' => 'Automated daily backups with periodic restore testing',
                    'backups_platform' => 'Backups handled by the platform, plus periodic export of the application data',
                    'backups_client' => 'Owned by the client\'s IT team',
                    'monitoring_us' => 'Uptime checks and alerting on application errors',
                    'monitoring_platform' => 'Platform uptime checks and alerting on application errors',
                    'monitoring_client' => 'Owned by the client\'s IT team',
                    'tls_us' => 'TLS certificate renewed automatically; domain stays in the client\'s name',
                    'tls_platform' => 'TLS certificate handled by the platform; domain stays in the client\'s name',
                    'tls_client' => 'Owned by the client\'s IT team',
                ],
                'assurance' => [
                    'title' => 'Security and quality: what protects this investment',
                    'intro' => 'Software is paid for once and lived with for years. What decides how much those years cost — and whether one morning it becomes a problem — is invisible in a demo: it is in how the thing was built. All of the following is included, with no separate line on the price list.',
                    'close' => 'No lock-in: the code, the data, and the infrastructure belong to the client. If the project ever moves to another team, it moves with its history, its tests, and its documentation — not as a puzzle to decipher.',
                    'review' => '**Every change goes through an independent review** before it reaches your environment. Nobody ships their own work unread.',
                    'criteria' => '**Every feature has acceptance criteria written before it is built**, and is checked against them. "It works" is not an opinion here: it is a list of conditions ticked off.',
                    'tests_best' => '**A broad automated test suite, run on every change.** This is what stops today\'s request from breaking what worked yesterday: verification does not depend on anyone remembering.',
                    'tests_normal' => '**Automated tests on the critical paths, run on every change.** The parts that would stop your work if they broke are covered by checks that run themselves.',
                    'tests_light' => '**Automated tests on the critical paths** — sign-in, payments, the functions the business runs on — plus functional verification of every delivery against the agreed acceptance criteria.',
                    'scan' => '**An automated security scan on every change.** Known vulnerabilities are caught before publication, not after someone reports them.',
                    'owasp' => '**Application security to OWASP practice**: credential handling, form protection, access and permission control reviewed before each delivery.',
                    'secrets' => '**No credentials inside the code.** Keys and passwords live in the environment configuration, under your control, and can be rotated without touching the software.',
                    'gitflow' => '**A traceable history.** Every line of code is tied to the request that produced it: months later you can reconstruct what changed, when, and why.',
                    'releases' => '**Numbered releases with a list of what changed.** If an update causes trouble, you go back to the previous version in minutes, not in a day.',
                    'updates' => '**Security updates for the framework and its libraries** are part of the maintenance retainer, not deferred until the next emergency.',
                    'privacy' => '**Personal data handled to GDPR**: legal basis, retention periods, notices, and data-subject rights designed in from the start.',
                ],
                'document_title' => 'Commercial proposal',
                'project' => 'Project',
                'reference' => 'Reference',
                'date' => 'Date',
                'validity' => 'Validity',
                'validity_value' => '15 days from the document date',
                'summary' => 'Offer summary',
                'summary_body' => 'This proposal covers the delivery of %s. The work is estimated at %s, over roughly %s from acceptance. The build investment is %s, with optional annual maintenance of %s.',
                'objectives' => 'Objectives',
                'deliverables' => 'What you get',
                'deliverables_intro' => 'The project delivers the following capabilities:',
                'deliverables_empty' => 'Scope follows the shared project document; capabilities will be listed in detail at kick-off.',
                'tech_note' => 'Custom build on Laravel, published on %s. Code, data, and access stay the client\'s property.',
                'tech_note_generic' => 'Custom build on Laravel. Code, data, and access stay the client\'s property.',
                'investment' => 'Investment',
                'item' => 'Item',
                'amount' => 'Amount',
                'build' => 'Build (ex VAT)',
                'discount' => 'Commercial discount',
                'discounted' => 'Discounted build (ex VAT)',
                'vat' => 'VAT',
                'vat_exempt' => 'VAT not applied',
                'vat_reverse' => 'VAT — EU B2B reverse charge',
                'total' => 'Total',
                'maintenance_year' => 'Annual maintenance (optional)',
                'effort_note' => 'Estimated effort: **%s**, indicative calendar **%s**, project and testing buffer included.',
                'timeline' => 'Delivery timeline',
                'timeline_intro' => 'Indicative duration of **%s** on **%s** of work. Dates start at offer acceptance and flex with feedback turnaround and holiday closures.',
                'phase' => 'Phase',
                'start' => 'Start',
                'end' => 'End',
                'days' => 'Days',
                'phase_kickoff' => 'Kick-off and analysis',
                'phase_design' => 'Design sign-off',
                'phase_build' => 'Build',
                'phase_qa' => 'Testing and validation',
                'phase_launch' => 'Go-live and handover',
                'gantt_title' => 'Delivery plan',
                'section_start' => 'Start',
                'section_build' => 'Build',
                'section_launch' => 'Release',
                'payment' => 'Payment terms',
                'milestone' => 'Milestone',
                'share' => 'Share',
                'pay_kickoff' => 'On acceptance / kick-off',
                'pay_uat' => 'At testing start',
                'pay_golive' => 'At go-live',
                'payment_note' => 'Invoices due 30 days from invoice date unless agreed otherwise. Go-live follows settlement of the previous instalment.',
                'maintenance_section' => 'Maintenance and support',
                'maintenance_body' => 'Suggested annual retainer: **%s** (about **%s** per month), %s%% of the build. It covers security updates, dependency upgrades, minor fixes, and usage support. New features are quoted separately.',
                'included' => 'Included',
                'not_included' => 'Not included',
                'client_provides' => 'What the client provides',
                'included_defaults' => [
                    'Analysis, design, and build of the listed capabilities',
                    'Interfaces shared and signed off before the build',
                    'Functional verification of everything delivered',
                    'Deployment to the agreed production environment',
                    'Handover session and usage material',
                    'Fixes for defects reported within 30 days of go-live',
                ],
                'excluded_defaults' => [
                    'Capabilities not listed in this proposal, and later phases',
                    'Hosting, domains, licences, and third-party services',
                    'Content production, advertising campaigns, and social media management',
                    'Training beyond the handover session',
                    'Scope changes or visual redesign after sign-off',
                    'Tax, legal, or data-protection advice',
                ],
                'client_defaults' => [
                    'One point of contact for decisions, content, and approvals',
                    'Logo and brand assets, where they already exist',
                    'Copy, imagery, and the data to load',
                    'Access to the domain, DNS, and the mailboxes involved',
                    'Test credentials for the services to connect (payments, email, ERP)',
                    'Feedback on interfaces and deliveries within the agreed turnaround',
                ],
                'client_deploy' => 'Operational access to the named publishing platform: %s',
                'next_steps' => 'Next steps',
                'next_steps_items' => [
                    'Written acceptance of this proposal within its validity',
                    'Kick-off, access collection, and scope sign-off',
                    'Start of the delivery calendar',
                ],
                'signoff' => 'Acceptance',
                'supplier' => 'Supplier',
                'client' => 'Client',
                'date_sign' => 'Date',
                'disclaimer' => 'Commercial proposal generated with Larapilot from the project document and effort estimates. Amounts and dates are planning figures and become binding once signed.',
            ],
        };
    }

    protected function projectTitle(string $prd): string
    {
        if (preg_match('/^#\s+(.+)$/m', $prd, $matches) === 1) {
            $title = trim($matches[1]);

            if ($title !== '' && ! preg_match('/^product requirements document$/i', $title)) {
                return $title;
            }
        }

        $root = basename($this->config->projectRoot());

        return $root !== '' ? $root : 'Project';
    }

    protected function firstParagraph(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $parts = preg_split('/\n\s*\n/', $text) ?: [];

        foreach ($parts as $part) {
            $line = trim($part);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '|')) {
                continue;
            }

            return $this->clip($line, 400);
        }

        return $this->clip($text, 400);
    }

    protected function clip(string $text, int $max): string
    {
        $text = trim($text);

        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, $max - 1)).'…';
    }

    /**
     * Client-readable line item: no trailing punctuation, no bold markers,
     * no leading spec code.
     */
    protected function sentence(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^(?:US|BUG|TASK)-\d+\s*[—:-]\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text) ?? $text;
        $text = rtrim(trim($text), '.;');

        return $this->clip($text, 180);
    }

    protected function normalizeKey(string $text): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $text) ?? $text);
    }

    protected function daysLabel(int $days, string $lang): string
    {
        return match ($lang) {
            'it' => $days.' '.($days === 1 ? 'giornata di lavoro' : 'giornate di lavoro'),
            'es' => $days.' '.($days === 1 ? 'jornada de trabajo' : 'jornadas de trabajo'),
            'fr' => $days.' '.($days === 1 ? 'jour de travail' : 'jours de travail'),
            'de' => $days.' '.($days === 1 ? 'Arbeitstag' : 'Arbeitstage'),
            'pt' => $days.' '.($days === 1 ? 'dia de trabalho' : 'dias de trabalho'),
            'nl' => $days.' '.($days === 1 ? 'werkdag' : 'werkdagen'),
            'pl' => $days.' '.($days === 1 ? 'dzień roboczy' : 'dni roboczych'),
            default => $days.' '.($days === 1 ? 'working day' : 'working days'),
        };
    }

    protected function durationLabel(float $months, string $lang): string
    {
        $value = $this->number($months, $lang);

        return match ($lang) {
            'it' => $value.($months <= 1 ? ' mese' : ' mesi'),
            'es' => $value.($months <= 1 ? ' mes' : ' meses'),
            'fr' => $value.' mois',
            'de' => $value.($months <= 1 ? ' Monat' : ' Monate'),
            'pt' => $value.($months <= 1 ? ' mês' : ' meses'),
            'nl' => $value.($months <= 1 ? ' maand' : ' maanden'),
            'pl' => $value.($months <= 1 ? ' miesiąc' : ' miesiąca'),
            default => $value.($months <= 1 ? ' month' : ' months'),
        };
    }

    /**
     * Decimal comma for it / es / fr, decimal point for en.
     */
    protected function number(float $value, string $lang = 'en'): string
    {
        $formatted = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return $lang === 'en' ? $formatted : str_replace('.', ',', $formatted);
    }

    protected function formatDate(DateTimeImmutable $date, string $lang): string
    {
        $months = match ($lang) {
            'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
            'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
            'pt' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
            'nl' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
            'pl' => ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'],
            default => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        };

        $month = $months[(int) $date->format('n') - 1] ?? $date->format('F');

        return $date->format('j').' '.$month.' '.$date->format('Y');
    }

    protected function money(float $amount, string $currency, string $lang = 'en'): string
    {
        $separator = $lang === 'en' ? ',' : '.';

        return $currency.' '.number_format($amount, 0, '.', $separator);
    }

    protected function slug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'quote';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'quote';
    }

    protected function yamlScalar(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
