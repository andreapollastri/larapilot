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
        $inception = is_array($snapshot['inception'] ?? null) ? $snapshot['inception'] : [];
        $product = is_array($snapshot['product'] ?? null) ? $snapshot['product'] : [];
        $currency = (string) ($snapshot['country']['currency'] ?? $quote['currency'] ?? 'EUR');
        $today = new DateTimeImmutable('now');
        $months = max(0.5, (float) ($quote['calendar_months'] ?? $effort['calendar_months'] ?? 1));
        $hours = (float) ($quote['billable_hours'] ?? $effort['billable_hours'] ?? 0);
        $gross = (float) ($quote['gross'] ?? 0);
        $vat = (float) ($quote['vat'] ?? 0);
        $total = (float) ($quote['client_total'] ?? ($gross + $vat));
        $maintenanceYear = (float) ($quote['maintenance_year'] ?? 0);
        $maintenanceMonth = (float) ($quote['maintenance_monthly'] ?? ($maintenanceYear / 12));
        $vatRate = (float) ($quote['vat_rate'] ?? 0);
        $vatMode = (string) ($quote['vat_mode'] ?? 'domestic');
        $reference = 'LP-'.$today->format('Ymd').'-'.strtoupper(substr($this->slug($title), 0, 8));
        $sections = $this->prdSections($prd);
        $specs = $this->specRows();
        $technical = $this->technicalPoints($sections, $inception, $specs);
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
            '| **'.$t['product_model'].'** | '.(string) ($product['label'] ?? '').' |',
            '',
        ];

        $pitch = $this->firstParagraph($sections['elevator'] ?? $sections['pitch'] ?? '');
        $vision = $this->firstParagraph($sections['vision'] ?? '');

        $lines[] = '## '.$t['summary'];
        $lines[] = '';
        $lines[] = sprintf(
            $t['summary_body'],
            '**'.$title.'**',
            $this->hoursLabel($hours, $lang),
            $this->durationLabel($months, $lang),
            $this->money($gross, $currency),
            $this->money($maintenanceYear, $currency)
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

        $lines[] = '## '.$t['investment'];
        $lines[] = '';
        $lines[] = '| '.$t['item'].' | '.$t['amount'].' |';
        $lines[] = '| --- | ---: |';
        $lines[] = '| '.$t['build'].' | '.$this->money($gross, $currency).' |';

        if ($vatMode === 'eu_b2b') {
            $lines[] = '| '.$t['vat_reverse'].' | '.$this->money(0, $currency).' |';
        } elseif ($vatRate > 0) {
            $lines[] = '| '.$t['vat'].' ('.$vatRate.'%) | '.$this->money($vat, $currency).' |';
        } else {
            $lines[] = '| '.$t['vat_exempt'].' | '.$this->money(0, $currency).' |';
        }

        $lines[] = '| **'.$t['total'].'** | **'.$this->money($total, $currency).'** |';
        $lines[] = '| '.$t['maintenance_year'].' | '.$this->money($maintenanceYear, $currency).' |';
        $lines[] = '| '.$t['maintenance_month'].' | '.$this->money($maintenanceMonth, $currency).' |';
        $lines[] = '';
        $lines[] = sprintf($t['hours_note'], $this->hoursLabel($hours, $lang), $this->durationLabel($months, $lang));
        $lines[] = '';

        $lines[] = '## '.$t['deliverables'];
        $lines[] = '';

        if ($specs === []) {
            $lines[] = $t['deliverables_empty'];
            $lines[] = '';
        } else {
            $lines[] = '| '.$t['story_id'].' | '.$t['story_title'].' | '.$t['points'].' |';
            $lines[] = '| --- | --- | ---: |';
            foreach ($specs as $row) {
                $lines[] = '| '.$row['code'].' | '.$row['title'].' | '.$row['points'].' |';
            }
            $lines[] = '';
        }

        $mvp = trim((string) ($sections['mvp'] ?? ''));
        if ($mvp !== '') {
            $lines[] = '### '.$t['mvp'];
            $lines[] = '';
            $lines[] = $this->clip($mvp, 1200);
            $lines[] = '';
        }

        $lines[] = '## '.$t['technical'];
        $lines[] = '';
        foreach ($technical as $point) {
            $lines[] = '- '.$point;
        }
        $lines[] = '';

        $arch = trim((string) ($sections['architecture'] ?? ''));
        if ($arch !== '') {
            $lines[] = '### '.$t['architecture'];
            $lines[] = '';
            $lines[] = $this->clip($arch, 1600);
            $lines[] = '';
        }

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

        $lines[] = '## '.$t['timeline'];
        $lines[] = '';
        $lines[] = sprintf($t['timeline_intro'], $this->durationLabel($months, $lang), $this->hoursLabel($hours, $lang));
        $lines[] = '';
        $gantt = $this->gantt($today, $months, $t);
        array_push($lines, ...$gantt);
        $lines[] = '';

        $lines[] = '## '.$t['payment'];
        $lines[] = '';
        $kickoff = round($total * 0.40, 2);
        $uat = round($total * 0.40, 2);
        $golive = round($total - $kickoff - $uat, 2);
        $lines[] = '| '.$t['milestone'].' | '.$t['share'].' | '.$t['amount'].' |';
        $lines[] = '| --- | ---: | ---: |';
        $lines[] = '| '.$t['pay_kickoff'].' | 40% | '.$this->money($kickoff, $currency).' |';
        $lines[] = '| '.$t['pay_uat'].' | 40% | '.$this->money($uat, $currency).' |';
        $lines[] = '| '.$t['pay_golive'].' | 20% | '.$this->money($golive, $currency).' |';
        $lines[] = '';
        $lines[] = $t['payment_note'];
        $lines[] = '';

        $lines[] = '## '.$t['maintenance_section'];
        $lines[] = '';
        $lines[] = sprintf($t['maintenance_body'], $this->money($maintenanceYear, $currency), $this->money($maintenanceMonth, $currency));
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
        $suffix = match ($lang) {
            'it' => 'preventivo',
            'es' => 'presupuesto',
            'fr' => 'devis',
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
            'elevator' => ['Elevator Pitch', 'Pitch', 'Sintesi', 'Accroche'],
            'vision' => ['Vision', 'Visione', 'Visión'],
            'mvp' => ['MVP Scope', 'Ambito MVP', 'Alcance MVP', 'Périmètre MVP'],
            'architecture' => ['Technical Architecture', 'Architettura tecnica', 'Arquitectura técnica', 'Architecture technique'],
            'requirements' => ['Functional Requirements', 'Requisiti funzionali', 'Requisitos funcionales', 'Exigences fonctionnelles'],
        ];

        $found = [];
        $parts = preg_split('/^##\s+(.+)$/m', $prd, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        for ($i = 1; $i < count($parts); $i += 2) {
            $heading = trim($parts[$i]);
            $body = trim($parts[$i + 1] ?? '');

            foreach ($map as $key => $labels) {
                foreach ($labels as $label) {
                    if (strcasecmp($heading, $label) === 0) {
                        $found[$key] = $body;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @return list<array{code: string, title: string, points: int, tasks: list<string>}>
     */
    protected function specRows(): array
    {
        $rows = [];

        foreach ($this->specs->allSpecs() as $spec) {
            if (! is_array($spec)) {
                continue;
            }

            $code = (string) ($spec['code'] ?? '');

            if ($code === '') {
                continue;
            }

            $tasks = [];
            $plan = $this->plans->read($code);
            $planTasks = is_array($plan['tasks'] ?? null) ? $plan['tasks'] : [];

            foreach ($planTasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $title = trim((string) ($task['title'] ?? ''));

                if ($title !== '') {
                    $tasks[] = $title;
                }
            }

            $rows[] = [
                'code' => $code,
                'title' => (string) ($spec['title'] ?? $code),
                'points' => (int) ($spec['points'] ?? 0),
                'tasks' => $tasks,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $sections
     * @param  array<string, mixed>  $inception
     * @param  list<array{code: string, title: string, points: int, tasks: list<string>}>  $specs
     * @return list<string>
     */
    protected function technicalPoints(array $sections, array $inception, array $specs): array
    {
        $points = [];
        $choices = $this->choices->read();

        foreach ([
            'project_kind' => $inception['project_kind'] ?? $choices['project_kind'] ?? null,
            'delivery_target' => $inception['delivery_target'] ?? $choices['delivery_target'] ?? null,
            'website_type' => $inception['website_type'] ?? $choices['website_type'] ?? null,
            'frontend_topology' => $inception['frontend_topology'] ?? $choices['frontend_topology'] ?? null,
            'admin_panel' => $choices['admin_panel'] ?? null,
            'data_store' => $choices['data_store'] ?? null,
            'deploy_platform' => $inception['deploy_platform'] ?? $choices['deploy_platform'] ?? null,
            'local_dev' => $choices['local_dev'] ?? null,
        ] as $label => $value) {
            if (is_string($value) && trim($value) !== '') {
                $points[] = $this->choiceLabel($label).': '.trim($value);
            }
        }

        $taskTitles = [];

        foreach ($specs as $spec) {
            foreach ($spec['tasks'] as $task) {
                $taskTitles[$task] = true;
            }
        }

        $unique = array_slice(array_keys($taskTitles), 0, 12);

        if ($unique !== []) {
            $points[] = implode(', ', $unique);
        }

        $reqs = $this->bulletLines((string) ($sections['requirements'] ?? ''), 8);

        foreach ($reqs as $req) {
            $points[] = $req;
        }

        if ($points === []) {
            $points[] = 'Laravel application delivery with tests and documented handover.';
        }

        return array_values(array_unique($points));
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
                'document_title' => 'Offerta commerciale',
                'project' => 'Progetto',
                'reference' => 'Riferimento',
                'date' => 'Data',
                'validity' => 'Validità',
                'validity_value' => '15 giorni dalla data del documento',
                'product_model' => 'Modello di prodotto',
                'summary' => 'Sintesi dell\'offerta',
                'summary_body' => 'La presente offerta copre la realizzazione di %s. L\'impegno stimato è di %s (circa %s). Il corrispettivo per la realizzazione è %s (IVA come da tabella), con manutenzione annuale suggerita di %s.',
                'investment' => 'Investimento',
                'item' => 'Voce',
                'amount' => 'Importo',
                'build' => 'Realizzazione (imponibile)',
                'vat' => 'IVA',
                'vat_exempt' => 'IVA non applicata',
                'vat_reverse' => 'IVA — reverse charge UE B2B',
                'total' => 'Totale cliente',
                'maintenance_year' => 'Manutenzione annuale (opzionale)',
                'maintenance_month' => 'Manutenzione mensile equivalente',
                'hours_note' => 'Stima di effort: **%s**, calendario indicativo **%s** (include buffer di progetto / QA).',
                'deliverables' => 'Perimetro e deliverable',
                'deliverables_empty' => 'Il perimetro segue il PRD. Le user story saranno elencate a backlog consolidato.',
                'story_id' => 'ID',
                'story_title' => 'User story',
                'points' => 'Punti',
                'mvp' => 'Ambito MVP',
                'technical' => 'Aspetti tecnici',
                'architecture' => 'Architettura',
                'included' => 'Cosa è compreso',
                'not_included' => 'Cosa non è compreso',
                'client_provides' => 'Cosa deve fornire il cliente',
                'included_defaults' => [
                    'Analisi, progettazione e implementazione delle user story elencate / ambito MVP',
                    'Mockup HTML già prodotti e allineamento visivo in fase di sviluppo',
                    'Test automatici previsti dai plan di delivery',
                    'Messa in produzione sull\'ambiente concordato',
                    'Documentazione di handover e sessione di consegna',
                    'Correzione di bug di regressione per 30 giorni dal go-live',
                ],
                'excluded_defaults' => [
                    'Funzionalità fuori ambito, fasi future e voci MoSCoW Won\'t',
                    'Canoni di hosting, licenze SaaS e servizi terzi',
                    'Produzione continuativa di contenuti marketing, SEM e community management',
                    'Formazione oltre la sessione di handover',
                    'Cambi di scope o redesign dopo il freeze di progetto',
                    'Consulenza fiscale, legale o privacy oltre i testi previsti nel PRD',
                ],
                'client_defaults' => [
                    'Referente unico per decisioni, contenuti e approvazioni nei tempi di progetto',
                    'Logo, palette e materiali di brand se già esistenti (altrimenti si usa l\'identità prodotta in design)',
                    'Testi, immagini e contenuti di dominio; indicazioni per privacy e termini se non in scope',
                    'Accesso a dominio, DNS e credenziali dell\'ambiente di deploy',
                    'Credenziali sandbox dei servizi terzi da integrare (pagamenti, email, CRM, …)',
                    'Feedback puntuali sui mockup e sugli sprint, entro i tempi concordati',
                ],
                'client_deploy' => 'Accesso operativo alla piattaforma di deploy indicata: %s',
                'timeline' => 'Stime e tempistiche',
                'timeline_intro' => 'Calendario indicativo di **%s**, basato su **%s** di lavoro. Le date partono dall\'accettazione dell\'offerta e si adattano a ferie e tempi di feedback del cliente.',
                'phase' => 'Fase',
                'start' => 'Inizio',
                'end' => 'Fine',
                'days' => 'Giorni',
                'phase_kickoff' => 'Kick-off e analisi',
                'phase_design' => 'Design freeze',
                'phase_build' => 'Implementazione',
                'phase_qa' => 'QA e UAT',
                'phase_launch' => 'Go-live e consegna',
                'gantt_title' => 'Piano di consegna',
                'section_start' => 'Avvio',
                'section_build' => 'Realizzazione',
                'section_launch' => 'Rilascio',
                'payment' => 'Modalità di pagamento',
                'milestone' => 'Milestone',
                'share' => 'Quota',
                'pay_kickoff' => 'All\'accettazione / kick-off',
                'pay_uat' => 'All\'avvio UAT',
                'pay_golive' => 'Al go-live',
                'payment_note' => 'Fatture a 30 giorni data fattura, salvo diverso accordo. Il go-live avviene a saldo della quota precedente.',
                'maintenance_section' => 'Manutenzione',
                'maintenance_body' => 'Canone annuale suggerito: **%s** (circa **%s**/mese), pari al 15%% della realizzazione. Copre aggiornamenti di sicurezza, dipendenze e correzioni minori. Evolutive extra sono stimate a parte.',
                'next_steps' => 'Passi successivi',
                'next_steps_items' => [
                    'Conferma scritta di questa offerta entro la validità indicata',
                    'Kick-off, accesso agli ambienti e freeze del perimetro MVP',
                    'Avvio del calendario di cui al Gantt',
                ],
                'signoff' => 'Accettazione',
                'supplier' => 'Fornitore',
                'client' => 'Cliente',
                'date_sign' => 'Data',
                'disclaimer' => 'Documento di offerta commerciale generato da Larapilot a partire da PRD, backlog e stime di effort. Importi e date sono pianificazioni, non un contratto vincolante finché non sottoscritti. Non costituisce consulenza fiscale.',
            ],
            'es' => $this->romance('es'),
            'fr' => $this->romance('fr'),
            default => [
                'document_title' => 'Commercial proposal',
                'project' => 'Project',
                'reference' => 'Reference',
                'date' => 'Date',
                'validity' => 'Validity',
                'validity_value' => '15 days from the document date',
                'product_model' => 'Product model',
                'summary' => 'Offer summary',
                'summary_body' => 'This proposal covers delivery of %s. Estimated effort is %s (about %s). The build price is %s (VAT as per the table), with suggested annual maintenance of %s.',
                'investment' => 'Investment',
                'item' => 'Item',
                'amount' => 'Amount',
                'build' => 'Build (ex VAT)',
                'vat' => 'VAT',
                'vat_exempt' => 'VAT not applied',
                'vat_reverse' => 'VAT — EU B2B reverse charge',
                'total' => 'Client total',
                'maintenance_year' => 'Annual maintenance (optional)',
                'maintenance_month' => 'Equivalent monthly maintenance',
                'hours_note' => 'Effort estimate: **%s**, indicative calendar **%s** (includes project / QA buffer).',
                'deliverables' => 'Scope and deliverables',
                'deliverables_empty' => 'Scope follows the PRD. User stories will be listed once the backlog is consolidated.',
                'story_id' => 'ID',
                'story_title' => 'User story',
                'points' => 'Points',
                'mvp' => 'MVP scope',
                'technical' => 'Technical aspects',
                'architecture' => 'Architecture',
                'included' => 'Included',
                'not_included' => 'Not included',
                'client_provides' => 'What the client provides',
                'included_defaults' => [
                    'Analysis, design, and implementation of the listed user stories / MVP scope',
                    'Existing HTML mockups and visual alignment during build',
                    'Automated tests defined in the delivery plans',
                    'Production deployment on the agreed environment',
                    'Handover documentation and a delivery session',
                    'Regression bug fixes for 30 days after go-live',
                ],
                'excluded_defaults' => [
                    'Out-of-scope features, future phases, and MoSCoW Won\'t items',
                    'Hosting fees, SaaS licences, and third-party subscriptions',
                    'Ongoing marketing content, SEM, and community management',
                    'Training beyond the handover session',
                    'Scope changes or redesign after project freeze',
                    'Tax, legal, or privacy advice beyond copy already scoped in the PRD',
                ],
                'client_defaults' => [
                    'A single decision-maker for content, approvals, and timely feedback',
                    'Existing brand assets (logo, palette) — otherwise the design identity is used',
                    'Domain copy, imagery, and guidance for privacy/terms if not in scope',
                    'Domain, DNS, and deploy-environment credentials',
                    'Sandbox credentials for third-party services to integrate (payments, email, CRM, …)',
                    'Prompt feedback on mockups and sprints within agreed turnaround',
                ],
                'client_deploy' => 'Operational access to the named deploy platform: %s',
                'timeline' => 'Estimates and timeline',
                'timeline_intro' => 'Indicative calendar of **%s**, based on **%s** of work. Dates start at offer acceptance and flex for holidays and client feedback.',
                'phase' => 'Phase',
                'start' => 'Start',
                'end' => 'End',
                'days' => 'Days',
                'phase_kickoff' => 'Kick-off and analysis',
                'phase_design' => 'Design freeze',
                'phase_build' => 'Implementation',
                'phase_qa' => 'QA and UAT',
                'phase_launch' => 'Go-live and handover',
                'gantt_title' => 'Delivery plan',
                'section_start' => 'Start',
                'section_build' => 'Build',
                'section_launch' => 'Release',
                'payment' => 'Payment terms',
                'milestone' => 'Milestone',
                'share' => 'Share',
                'pay_kickoff' => 'On acceptance / kick-off',
                'pay_uat' => 'At UAT start',
                'pay_golive' => 'At go-live',
                'payment_note' => 'Invoices due 30 days from invoice date unless agreed otherwise. Go-live follows settlement of the previous instalment.',
                'maintenance_section' => 'Maintenance',
                'maintenance_body' => 'Suggested annual retainer: **%s** (about **%s**/month), 15%% of the build. Covers security updates, dependency bumps, and minor fixes. Extra evolutions are quoted separately.',
                'next_steps' => 'Next steps',
                'next_steps_items' => [
                    'Written acceptance of this proposal within the stated validity',
                    'Kick-off, environment access, and MVP scope freeze',
                    'Start of the Gantt calendar above',
                ],
                'signoff' => 'Acceptance',
                'supplier' => 'Supplier',
                'client' => 'Client',
                'date_sign' => 'Date',
                'disclaimer' => 'Commercial proposal generated by Larapilot from the PRD, backlog, and effort estimates. Amounts and dates are planning figures, not a binding contract until signed. This is not tax advice.',
            ],
        };
    }

    /**
     * Compact Spanish / French catalogues reuse the English structure.
     *
     * @return array<string, mixed>
     */
    protected function romance(string $lang): array
    {
        $base = $this->strings('en');

        if ($lang === 'es') {
            $base['document_title'] = 'Oferta comercial';
            $base['project'] = 'Proyecto';
            $base['reference'] = 'Referencia';
            $base['date'] = 'Fecha';
            $base['validity'] = 'Validez';
            $base['validity_value'] = '15 días desde la fecha del documento';
            $base['summary'] = 'Resumen de la oferta';
            $base['investment'] = 'Inversión';
            $base['included'] = 'Incluido';
            $base['not_included'] = 'No incluido';
            $base['client_provides'] = 'Qué debe aportar el cliente';
            $base['timeline'] = 'Estimaciones y calendario';
            $base['payment'] = 'Condiciones de pago';
            $base['maintenance_section'] = 'Mantenimiento';
            $base['signoff'] = 'Aceptación';
            $base['supplier'] = 'Proveedor';
            $base['client'] = 'Cliente';
            $base['disclaimer'] = 'Oferta comercial generada por Larapilot a partir del PRD, el backlog y las estimaciones. No es un contrato ni asesoramiento fiscal hasta su firma.';
        }

        if ($lang === 'fr') {
            $base['document_title'] = 'Offre commerciale';
            $base['project'] = 'Projet';
            $base['reference'] = 'Référence';
            $base['date'] = 'Date';
            $base['validity'] = 'Validité';
            $base['validity_value'] = '15 jours à compter de la date du document';
            $base['summary'] = 'Synthèse de l\'offre';
            $base['investment'] = 'Investissement';
            $base['included'] = 'Inclus';
            $base['not_included'] = 'Non inclus';
            $base['client_provides'] = 'Éléments à fournir par le client';
            $base['timeline'] = 'Estimations et planning';
            $base['payment'] = 'Modalités de paiement';
            $base['maintenance_section'] = 'Maintenance';
            $base['signoff'] = 'Acceptation';
            $base['supplier'] = 'Prestataire';
            $base['client'] = 'Client';
            $base['disclaimer'] = 'Offre commerciale générée par Larapilot à partir du PRD, du backlog et des estimations. Ce n\'est pas un contrat ni un conseil fiscal tant qu\'elle n\'est pas signée.';
        }

        return $base;
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

    protected function hoursLabel(float $hours, string $lang): string
    {
        $value = rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');

        return match ($lang) {
            'it' => $value.' ore',
            'es' => $value.' horas',
            'fr' => $value.' heures',
            default => $value.' hours',
        };
    }

    protected function durationLabel(float $months, string $lang): string
    {
        $value = rtrim(rtrim(number_format($months, 1, '.', ''), '0'), '.');

        return match ($lang) {
            'it' => $value.' mesi',
            'es' => $value.' meses',
            'fr' => $value.' mois',
            default => $value.' months',
        };
    }

    protected function formatDate(DateTimeImmutable $date, string $lang): string
    {
        $months = match ($lang) {
            'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
            'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
            'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
            default => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        };

        $month = $months[(int) $date->format('n') - 1] ?? $date->format('F');

        return match ($lang) {
            'en' => $date->format('j').' '.$month.' '.$date->format('Y'),
            default => $date->format('j').' '.$month.' '.$date->format('Y'),
        };
    }

    protected function money(float $amount, string $currency): string
    {
        return $currency.' '.number_format($amount, 0, '.', ',');
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

    protected function choiceLabel(string $key): string
    {
        return match ($key) {
            'project_kind' => 'Project kind',
            'delivery_target' => 'Delivery target',
            'website_type' => 'Website type',
            'frontend_topology' => 'Frontend topology',
            'admin_panel' => 'Admin panel',
            'data_store' => 'Data store',
            'deploy_platform' => 'Deploy',
            'local_dev' => 'Local dev',
            default => $key,
        };
    }
}
