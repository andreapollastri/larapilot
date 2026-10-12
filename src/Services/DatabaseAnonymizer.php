<?php

declare(strict_types=1);

namespace Larapilot\Services;

use DateTimeInterface;
use Faker\Factory;
use Faker\Generator;
use InvalidArgumentException;
use Larapilot\Services\Auth\DashboardSession;
use Larapilot\Support\AtomicFile;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Replaces personal data before it leaves the database viewer: on the
 * page, in the SQL dump, and in the Laravel seeders. Migrations hold no
 * row and are left as they are.
 *
 * An admin keeps the map in `.larapilot/anonymization.yaml`: the Faker
 * locale, and for each column one of three strategies (keep, hide, same
 * shape) or a formatter of fakerphp.org — the common ones, and those the
 * chosen locale adds (an Italian codice fiscale, a French NIR, …). A
 * column with no line follows a default that errs on the side of privacy:
 * a name, an email, a phone, an address, free text are replaced; keys,
 * dates of record, statuses, and enumerations are kept so the copy still
 * imports and still works.
 *
 * In production anyone who is not an admin always gets the anonymized
 * copy, and cannot turn it off. An admin sees the real rows and asks for
 * an anonymized download when they want one. A password, a token, or a
 * secret stays hidden either way.
 *
 * The same source value always becomes the same replacement on one
 * install — a foreign key written as text still matches, a dump can be
 * imported twice — because the replacement is seeded with an HMAC of the
 * value keyed with the application key. Without that key the
 * replacement says nothing about the original: no hash of a real value
 * is ever written out.
 */
class DatabaseAnonymizer
{
    public const FILE = '.larapilot/anonymization.yaml';

    public const DEFAULT_LOCALE = 'en_US';

    /**
     * What a column may become that is not a Faker formatter.
     *
     * @var array<string, string>
     */
    public const STRATEGIES = [
        'keep' => 'Keep as it is',
        'mask' => 'Hide — NULL, or empty',
        'shape' => 'Same shape, new characters',
    ];

    /**
     * The formatters of https://fakerphp.org/formatters/ an admin may pick,
     * grouped as the site groups them. None takes an argument. A Faker
     * method is never named by the form: only a key of this list, or of
     * the list the chosen locale adds.
     *
     * @var array<string, array<string, string>>
     */
    public const CATALOG = [
        'Person' => [
            'name' => 'Full name',
            'firstName' => 'First name',
            'lastName' => 'Last name',
            'title' => 'Title — Mr., Ms., Dr.',
            'birthDate' => 'Date of birth — 18 to 90 years ago',
        ],
        'Address' => [
            'address' => 'Full address',
            'streetAddress' => 'Street and number',
            'streetName' => 'Street name',
            'buildingNumber' => 'Building number',
            'city' => 'City',
            'postcode' => 'Postcode',
            'country' => 'Country',
            'countryCode' => 'Country code — ISO 3166',
            'latitude' => 'Latitude',
            'longitude' => 'Longitude',
        ],
        'Phone' => [
            'phoneNumber' => 'Phone number',
            'e164PhoneNumber' => 'Phone number — E.164',
        ],
        'Company' => [
            'company' => 'Company',
            'jobTitle' => 'Job title',
        ],
        'Internet' => [
            'safeEmail' => 'Email — example.* domains, unique',
            'userName' => 'Username — unique',
            'domainName' => 'Domain name',
            'url' => 'URL',
            'slug' => 'Slug',
            'ipv4' => 'IPv4 address',
            'ipv6' => 'IPv6 address',
            'macAddress' => 'MAC address',
            'userAgent' => 'User agent',
        ],
        'Text' => [
            'word' => 'Word',
            'sentence' => 'Sentence',
            'paragraph' => 'Paragraph',
            'text' => 'Text of about the same length',
        ],
        'Date and time' => [
            'date' => 'Date',
            'dateTime' => 'Date and time',
            'time' => 'Time',
            'year' => 'Year',
        ],
        'Payment' => [
            'iban' => 'IBAN',
            'swiftBicNumber' => 'SWIFT / BIC',
            'creditCardNumber' => 'Card number',
            'creditCardExpirationDateString' => 'Card expiry',
        ],
        'Identifiers' => [
            'uuid' => 'UUID',
            'ean13' => 'EAN-13',
            'isbn13' => 'ISBN-13',
        ],
        'Miscellaneous' => [
            'colorName' => 'Colour name',
            'hexColor' => 'Hex colour',
            'currencyCode' => 'Currency code',
            'languageCode' => 'Language code',
            'boolean' => 'Yes or no',
        ],
    ];

    /**
     * Tables of the framework itself: their rows are kept, hidden, or
     * replaced whatever their column names suggest — a migration name is
     * not a person, a queued job's payload may be anyone.
     *
     * @var array<string, array<string, string>>
     */
    protected const FRAMEWORK = [
        'migrations' => ['*' => 'keep'],
        'jobs' => ['payload' => 'mask', '*' => 'keep'],
        'job_batches' => ['failed_job_ids' => 'keep', 'options' => 'mask', '*' => 'keep'],
        'failed_jobs' => ['payload' => 'mask', 'exception' => 'mask', '*' => 'keep'],
        'cache' => ['value' => 'mask', '*' => 'keep'],
        'cache_locks' => ['*' => 'keep'],
        'sessions' => ['payload' => 'mask', 'ip_address' => 'ipv4', 'user_agent' => 'userAgent', '*' => 'keep'],
        'password_reset_tokens' => ['email' => 'safeEmail', '*' => 'keep'],
        'password_resets' => ['email' => 'safeEmail', '*' => 'keep'],
        'personal_access_tokens' => ['name' => 'keep', 'abilities' => 'keep', '*' => 'keep'],
        'telescope_entries' => ['content' => 'mask', '*' => 'keep'],
        'telescope_entries_tags' => ['tag' => 'mask', '*' => 'keep'],
        'telescope_monitoring' => ['*' => 'keep'],
        'pulse_entries' => ['key' => 'mask', '*' => 'keep'],
        'pulse_values' => ['key' => 'mask', 'value' => 'mask', '*' => 'keep'],
        'pulse_aggregates' => ['key' => 'mask', '*' => 'keep'],
    ];

    /** Kept whatever their type: the bookkeeping of a row, not a person. */
    protected const KEPT_NAMES = [
        'id', 'uuid', 'ulid', 'status', 'state', 'type', 'kind', 'role', 'slug', 'code', 'sku', 'published',
        'locale', 'lang', 'language', 'currency', 'timezone', 'position', 'order', 'sort', 'priority', 'level',
        'version', 'guard_name', 'model_type', 'morph_type', 'queue', 'connection', 'driver', 'channel',
    ];

    /** @var array<string, Generator> */
    protected array $generators = [];

    protected bool $requested = false;

    /** @var array{locale: string, tables: array<string, array<string, string>>}|null */
    protected ?array $map = null;

    protected bool $broken = false;

    /** @var array<string, array<string, string>> */
    protected array $localFormatters = [];

    protected ?string $key = null;

    public function __construct(
        protected ConfigService $config,
        protected DashboardSession $session,
    ) {}

    /**
     * An admin asked for the anonymized copy. The viewer and the download
     * of this request read it. The next request says so again: a download
     * that did not ask is the real rows, unless production requires the
     * anonymized ones.
     */
    public function engage(bool $requested): void
    {
        $this->requested = $requested;
    }

    /**
     * Production, and the person at the keyboard is not an admin. There
     * is no switch for this.
     */
    public function required(): bool
    {
        return app()->environment('production') && ! $this->session->isAdmin();
    }

    public function active(): bool
    {
        return $this->requested || $this->required();
    }

    public function path(): string
    {
        return $this->config->absolutePath(self::FILE);
    }

    public function relativePath(): string
    {
        return $this->config->relativePath($this->path());
    }

    /**
     * The file could not be parsed. The map is then empty, so the safe
     * default still applies and the page can say the file is broken.
     */
    public function broken(): bool
    {
        $this->read();

        return $this->broken;
    }

    /**
     * The Faker locale the replacements are written in.
     */
    public function locale(): string
    {
        return $this->read()['locale'];
    }

    /**
     * The saved choices: table, then column, then a strategy or a formatter.
     *
     * @return array<string, array<string, string>>
     */
    public function map(): array
    {
        return $this->read()['tables'];
    }

    /**
     * How many columns have a line of their own.
     */
    public function rules(): int
    {
        return array_sum(array_map('count', $this->map()));
    }

    /**
     * Every locale Faker ships, as `code => label`.
     *
     * @return array<string, string>
     */
    public function locales(): array
    {
        $root = $this->providerRoot();
        $locales = [];

        foreach (scandir($root) ?: [] as $entry) {
            if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $entry) === 1 && is_dir($root.DIRECTORY_SEPARATOR.$entry)) {
                $locales[$entry] = $this->localeLabel($entry);
            }
        }

        asort($locales);

        return $locales;
    }

    /**
     * The formatters a locale adds to the common ones, as `method => label`:
     * the public methods of its providers that take no argument.
     *
     * @return array<string, string>
     */
    public function localFormatters(?string $locale = null): array
    {
        $locale ??= $this->locale();

        if (isset($this->localFormatters[$locale])) {
            return $this->localFormatters[$locale];
        }

        $common = $this->commonKeys();
        $found = [];
        $dir = $this->providerRoot().DIRECTORY_SEPARATOR.$locale;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $base = basename($file, '.php');

            // A file of functions (pt_BR's check_digit.php), not a provider.
            if (preg_match('/^[A-Z][A-Za-z]+$/', $base) !== 1) {
                continue;
            }

            $class = 'Faker\\Provider\\'.$locale.'\\'.$base;

            try {
                if (! class_exists($class)) {
                    continue;
                }

                foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    $name = $method->getName();

                    if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()
                        || $method->getNumberOfRequiredParameters() > 0 || isset($common[$name]) || str_starts_with($name, '__')) {
                        continue;
                    }

                    $found[$name] = $this->humanize($name).' — '.$base;
                }
            } catch (Throwable) {
                continue;
            }
        }

        ksort($found);

        return $this->localFormatters[$locale] = $found;
    }

    /**
     * Whether a key may be saved for a column: a strategy, a common
     * formatter, or one the locale adds.
     */
    public function known(string $formatter, ?string $locale = null): bool
    {
        return isset(self::STRATEGIES[$formatter]) || isset($this->commonKeys()[$formatter]) || isset($this->localFormatters($locale)[$formatter]);
    }

    public function label(string $formatter): string
    {
        if (isset(self::STRATEGIES[$formatter])) {
            return self::STRATEGIES[$formatter];
        }

        foreach (self::CATALOG as $formatters) {
            if (isset($formatters[$formatter])) {
                return $formatters[$formatter];
            }
        }

        return $this->localFormatters()[$formatter] ?? $formatter;
    }

    /**
     * What a column becomes when the map says nothing.
     */
    public function suggestion(string $table, string $column, string $type = '', bool $secret = false): string
    {
        if ($secret) {
            return 'mask';
        }

        $framework = self::FRAMEWORK[$table] ?? null;

        if ($framework !== null) {
            return $framework[$column] ?? $framework['*'];
        }

        return $this->implicit($column, $this->category($type));
    }

    /**
     * The strategy or formatter in force: a secret is always hidden, then
     * the map, then the default.
     */
    public function formatter(string $table, string $column, bool $secret = false, string $type = ''): string
    {
        if ($secret) {
            return 'mask';
        }

        $saved = $this->map()[$table][$column] ?? null;

        return is_string($saved) ? $saved : $this->suggestion($table, $column, $type);
    }

    /**
     * One value as it may leave. `masked` means the caller shows the
     * placeholder (asterisks on the page, NULL or nothing in a dump).
     *
     * @return array{value: mixed, masked: bool}
     */
    public function apply(string $table, string $column, mixed $value, bool $secret, bool $numeric, bool $binary, string $type = ''): array
    {
        if (! $this->active()) {
            return ['value' => $value, 'masked' => $secret];
        }

        $formatter = $this->formatter($table, $column, $secret, $type);

        if ($formatter === 'keep') {
            return ['value' => $value, 'masked' => false];
        }

        if ($formatter === 'mask' || $binary || is_resource($value)) {
            return ['value' => $value, 'masked' => true];
        }

        if ($value === null || $value === '' || ! is_scalar($value)) {
            return ['value' => $value, 'masked' => false];
        }

        $category = $numeric && $type === '' ? 'numeric' : $this->category($type);

        // An enumeration or a flag takes no invented value: the copy would
        // not import. The map only offers keep or hide for them.
        if (in_array($category, ['enum', 'boolean'], true)) {
            return ['value' => $value, 'masked' => false];
        }

        return ['value' => $this->replace($table, $column, $value, $formatter, $category, $this->length($type)), 'masked' => false];
    }

    /**
     * An example of what a formatter writes for a column of a type — for
     * the page, never seeded with a real value.
     */
    public function sample(string $table, string $column, string $formatter, string $type = ''): string
    {
        if ($formatter === 'keep') {
            return 'the value as it is';
        }

        if ($formatter === 'mask') {
            return 'NULL, or empty';
        }

        $category = $this->category($type);
        $name = strtolower($column);
        $has = static fn (string ...$needles): bool => array_filter($needles, static fn (string $needle): bool => str_contains($name, $needle)) !== [];
        $example = match (true) {
            $category === 'numeric' => '4815162342',
            $category === 'date' => '1984-06-21',
            $category === 'datetime' => '2026-03-14 09:26:53',
            $category === 'time' => '09:26:53',
            $category === 'year' => '1984',
            $has('email') => 'ada.lovelace@example.test',
            $has('phone', 'mobile', 'fax') => '+39 333 123 4567',
            $has('tax', 'fiscal', 'ssn', 'vat', 'national') => 'LVLDAA80A41H501U',
            $has('iban') => 'IT60X0542811101000000123456',
            $formatter === 'shape' => 'AB-1234-XY',
            mb_strlen($column) > 0 && $has('note', 'bio', 'description', 'comment', 'message', 'body') => 'Met at the conference in Turin; prefers calls after 5 pm.',
            default => 'Ada Lovelace',
        };

        $made = $this->replace($table, $column, $example, $formatter, $category, $this->length($type), 'sample');

        return is_scalar($made) ? (string) $made : '';
    }

    /**
     * The tables and columns of the connection, with the choice saved for
     * each, the default, and an example. Views are left out: their rows
     * follow the same rules when they are read.
     *
     * @return array{error: string|null, tables: list<array{key: string, name: string, framework: bool, columns: list<array{name: string, type: string, secret: bool, saved: string, suggestion: string, sample: string, kept_only: bool}>}>}
     */
    public function tablesForForm(DatabaseViewerService $viewer): array
    {
        $schema = $viewer->schema();

        if (($schema['error'] ?? null) !== null) {
            return ['error' => (string) $schema['error'], 'tables' => []];
        }

        $map = $this->map();
        $tables = [];

        foreach ($schema['schema'] ?? [] as $object) {
            if (($object['kind'] ?? '') !== 'table') {
                continue;
            }

            $columns = [];
            $key = (string) $object['key'];

            foreach ($object['columns'] as $column) {
                $name = (string) $column['name'];
                $type = (string) $column['type'];
                $secret = $viewer->masked($name);
                $saved = $secret ? 'mask' : ($map[$key][$name] ?? '');
                $suggestion = $this->suggestion($key, $name, $type, $secret);

                $columns[] = [
                    'name' => $name,
                    'type' => $type,
                    'secret' => $secret,
                    'saved' => $saved,
                    'suggestion' => $suggestion,
                    'sample' => $this->sample($key, $name, $saved !== '' ? $saved : $suggestion, $type),
                    // An enumeration or a flag cannot take an invented value.
                    'kept_only' => in_array($this->category($type), ['enum', 'boolean', 'binary'], true),
                ];
            }

            $tables[] = [
                'key' => $key,
                'name' => (string) $object['name'],
                'framework' => isset(self::FRAMEWORK[$key]),
                'columns' => $columns,
            ];
        }

        return ['error' => null, 'tables' => $tables];
    }

    /**
     * Set the Faker locale of every replacement.
     *
     * @throws InvalidArgumentException when Faker has no such locale
     */
    public function saveLocale(string $locale): void
    {
        if (! isset($this->locales()[$locale])) {
            throw new InvalidArgumentException('Faker has no locale “'.$locale.'”.');
        }

        $map = $this->read();
        $map['locale'] = $locale;

        // A formatter of the old locale the new one lacks falls back to its
        // default when read; drop it from the file so the page says so.
        foreach ($map['tables'] as $table => $columns) {
            foreach ($columns as $column => $formatter) {
                if (! $this->known($formatter, $locale)) {
                    unset($map['tables'][$table][$column]);
                }
            }

            if ($map['tables'][$table] === []) {
                unset($map['tables'][$table]);
            }
        }

        $this->write($map);
    }

    /**
     * Replace one table's lines and leave every other table as it was. A
     * secret column is not stored: it stays hidden. An enumeration or a
     * flag takes only keep or hide. An unknown formatter refuses the save;
     * an unknown column is ignored.
     *
     * @param  array<mixed, mixed>  $columns
     *
     * @throws InvalidArgumentException
     */
    public function saveTable(string $table, array $columns, DatabaseViewerService $viewer): int
    {
        $form = $this->tablesForForm($viewer);

        if ($form['error'] !== null) {
            throw new InvalidArgumentException('The database cannot be read, so the map was not saved.');
        }

        $known = [];

        foreach ($form['tables'] as $object) {
            if ($object['key'] !== $table) {
                continue;
            }

            foreach ($object['columns'] as $column) {
                $known[$column['name']] = $column;
            }
        }

        if ($known === []) {
            throw new InvalidArgumentException('That table is not in this database.');
        }

        $chosen = [];

        foreach ($columns as $column => $formatter) {
            if (! is_string($column) || ! isset($known[$column]) || $known[$column]['secret']) {
                continue;
            }

            if (! is_string($formatter) || $formatter === '' || $formatter === 'default') {
                continue;
            }

            if (! $this->known($formatter)) {
                throw new InvalidArgumentException('“'.$formatter.'” is not a formatter Larapilot knows for '.$this->locale().'.');
            }

            if ($known[$column]['kept_only'] && ! in_array($formatter, ['keep', 'mask'], true)) {
                throw new InvalidArgumentException($table.'.'.$column.' is an enumeration or a flag: keep it or hide it.');
            }

            // The same as the default needs no line.
            if ($formatter === $known[$column]['suggestion']) {
                continue;
            }

            $chosen[$column] = $formatter;
        }

        ksort($chosen);

        $map = $this->read();

        if ($chosen === []) {
            unset($map['tables'][$table]);
        } else {
            $map['tables'][$table] = $chosen;
        }

        $this->write($map);

        return count($chosen);
    }

    /**
     * The kind of value a column holds, from its type as the schema
     * builder reports it.
     */
    public function category(string $type): string
    {
        $type = strtolower(trim($type));

        return match (true) {
            $type === '' => 'text',
            (bool) preg_match('/^(enum|set)\b/', $type) => 'enum',
            (bool) preg_match('/^(bool|boolean|bit)\b|^tinyint\(1\)/', $type) => 'boolean',
            (bool) preg_match('/blob|binary|bytea|^image\b/', $type) => 'binary',
            (bool) preg_match('/^(uuid|uniqueidentifier)\b/', $type) => 'uuid',
            (bool) preg_match('/^jsonb?\b/', $type) => 'json',
            (bool) preg_match('/^(datetime|timestamp|smalldatetime|datetimeoffset)/', $type) => 'datetime',
            (bool) preg_match('/^date\b/', $type) => 'date',
            (bool) preg_match('/^time\b/', $type) => 'time',
            (bool) preg_match('/^year\b/', $type) => 'year',
            (bool) preg_match('/int|dec|num|float|double|real|money|serial/', $type) => 'numeric',
            default => 'text',
        };
    }

    /**
     * @return array{locale: string, tables: array<string, array<string, string>>}
     */
    protected function read(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $this->broken = false;
        $empty = ['locale' => self::DEFAULT_LOCALE, 'tables' => []];
        $path = $this->path();

        if (! is_file($path)) {
            return $this->map = $empty;
        }

        try {
            $parsed = Yaml::parseFile($path);
        } catch (ParseException) {
            $this->broken = true;

            return $this->map = $empty;
        }

        $parsed = is_array($parsed) ? $parsed : [];
        $locale = is_string($parsed['locale'] ?? null) && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $parsed['locale']) === 1
            && is_dir($this->providerRoot().DIRECTORY_SEPARATOR.$parsed['locale'])
            ? $parsed['locale']
            : self::DEFAULT_LOCALE;

        $tables = is_array($parsed['tables'] ?? null) ? $parsed['tables'] : [];
        $clean = [];

        foreach ($tables as $table => $columns) {
            if (! is_string($table) || ! is_array($columns)) {
                continue;
            }

            foreach ($columns as $column => $formatter) {
                if (is_string($column) && is_string($formatter) && $this->known($formatter, $locale)) {
                    $clean[$table][$column] = $formatter;
                }
            }
        }

        return $this->map = ['locale' => $locale, 'tables' => $clean];
    }

    /**
     * @param  array{locale: string, tables: array<string, array<string, string>>}  $map
     */
    protected function write(array $map): void
    {
        ksort($map['tables']);

        $comment = "# How the rows of the database leave Larapilot when they are anonymized —\n"
            ."# on the Database page for a developer in production, and in every SQL dump or seeder\n"
            ."# downloaded with Anonymize. `locale` is the Faker locale of the replacements; under\n"
            ."# `tables`, each column names a strategy (keep, mask, shape) or a fakerphp.org formatter.\n"
            ."# A column with no line follows the safe default. Edit it at /larapilot/anonymization. Commit it.\n\n";

        AtomicFile::write($this->path(), $comment.Yaml::dump(['locale' => $map['locale'], 'tables' => $map['tables']], 4, 2));

        $this->map = $map;
    }

    protected function implicit(string $column, string $category): string
    {
        $name = strtolower($column);

        if (in_array($category, ['enum', 'boolean', 'uuid'], true)) {
            return 'keep';
        }

        if ($category === 'binary') {
            return 'mask';
        }

        if (in_array($name, self::KEPT_NAMES, true) || str_ends_with($name, '_id') || str_ends_with($name, '_uuid')
            || str_ends_with($name, '_type') || str_ends_with($name, '_at') || str_ends_with($name, '_count')
            || str_starts_with($name, 'is_') || str_starts_with($name, 'has_')) {
            return 'keep';
        }

        $has = static fn (string ...$needles): bool => array_filter($needles, static fn (string $needle): bool => str_contains($name, $needle)) !== [];

        if (in_array($category, ['date', 'datetime'], true)) {
            return $has('birth', 'dob', 'born') ? 'birthDate' : 'keep';
        }

        if (in_array($category, ['time', 'year'], true)) {
            return 'keep';
        }

        if ($category === 'numeric') {
            return $has('phone', 'mobile', 'fax', 'tel', 'tax', 'vat', 'ssn', 'iban', 'card', 'account_number', 'national') ? 'shape' : 'keep';
        }

        return match (true) {
            $has('password', 'secret', 'token', 'api_key', 'apikey', 'private_key') => 'mask',
            $has('email', 'e_mail') => 'safeEmail',
            $has('phone', 'mobile', 'fax', 'telefon', 'cellular') || $name === 'tel' => 'phoneNumber',
            $has('first_name', 'firstname', 'given_name', 'forename') => 'firstName',
            $has('last_name', 'lastname', 'surname', 'family_name') => 'lastName',
            $has('username', 'user_name', 'login', 'nickname', 'handle') => 'userName',
            $has('company', 'organisation', 'organization', 'employer') => 'company',
            $has('job_title', 'jobtitle', 'occupation') => 'jobTitle',
            $has('iban') => 'iban',
            $has('swift', 'bic') => 'swiftBicNumber',
            $has('card_number', 'cardnumber', 'pan') => 'creditCardNumber',
            $has('tax', 'fiscal', 'codice_fiscale', 'ssn', 'social_security', 'national_id', 'vat', 'passport', 'licence', 'license_number', 'id_number', 'document_number') => 'shape',
            $has('country_code') => 'countryCode',
            $has('country') => 'country',
            $has('postcode', 'postal', 'zip') => 'postcode',
            $has('city', 'town', 'locality') => 'city',
            $has('street', 'address') => 'streetAddress',
            $has('latitude') || $name === 'lat' => 'latitude',
            $has('longitude') || in_array($name, ['lng', 'lon'], true) => 'longitude',
            $name === 'ip' || $has('ip_address', 'ipaddress') || str_ends_with($name, '_ip') => 'ipv4',
            $has('user_agent', 'useragent') => 'userAgent',
            $has('url', 'website', 'homepage', 'link') => 'url',
            $name === 'name' || $has('full_name', 'fullname', 'display_name', 'contact_name', 'customer_name', 'client_name', 'owner_name') => 'name',
            // A number of the business — an invoice, an order, a reference — names no person.
            in_array($name, ['number', 'reference', 'ref', 'sku', 'order_no', 'invoice_no'], true) || str_ends_with($name, '_reference') || ($has('number') && $has('invoice', 'order', 'quote', 'receipt', 'ticket')) => 'keep',
            // Settings of a record, not facts about a person.
            $has('color', 'colour', 'timezone', 'time_zone', 'locale', 'currency') => 'keep',
            $category === 'json' => 'text',
            default => 'text',
        };
    }

    /**
     * The replacement of one value, seeded from it, in the shape the column
     * can take back: a date for a date, digits for a number, no longer
     * than the column, and never the original itself.
     */
    protected function replace(string $table, string $column, mixed $value, string $formatter, string $category, ?int $length, string $context = 'row'): mixed
    {
        $text = (string) $value;

        if ($category === 'json' || ($formatter === 'text' && $this->looksLikeJson($text))) {
            $decoded = json_decode($text, true);

            if (is_array($decoded)) {
                $encoded = json_encode($this->scrub($decoded, $table, $column), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return is_string($encoded) ? $encoded : '{}';
            }
        }

        $faker = $this->generator();
        $faker->seed($this->seed($table, $column, $text, $context));

        if ($formatter === 'shape' || $category === 'numeric') {
            return $this->shape($faker, $text);
        }

        if (in_array($category, ['date', 'datetime', 'time', 'year'], true)) {
            $moment = in_array($formatter, ['birthDate'], true)
                ? $faker->dateTimeBetween('-90 years', '-18 years')
                : $faker->dateTimeBetween('-10 years', 'now');

            return match ($category) {
                'date' => $moment->format('Y-m-d'),
                'time' => $moment->format('H:i:s'),
                'year' => $moment->format('Y'),
                default => $moment->format('Y-m-d H:i:s'),
            };
        }

        if ($category === 'uuid') {
            return $faker->uuid();
        }

        $made = match ($formatter) {
            'safeEmail' => $this->uniqueLocalPart($faker, $table, $column, $text).'@'.$faker->randomElement(['example.com', 'example.net', 'example.org']),
            'userName' => $this->uniqueLocalPart($faker, $table, $column, $text),
            'birthDate' => $faker->dateTimeBetween('-90 years', '-18 years')->format('Y-m-d'),
            'date' => $faker->date('Y-m-d'),
            'dateTime' => $faker->dateTime()->format('Y-m-d H:i:s'),
            'time' => $faker->time('H:i:s'),
            'year' => $faker->year(),
            'text' => $this->textLike($faker, $text),
            'boolean' => $faker->boolean() ? '1' : '0',
            default => $this->call($faker, $formatter, $text),
        };

        if ($made instanceof DateTimeInterface) {
            $made = $made->format('Y-m-d H:i:s');
        }

        $made = is_scalar($made) ? (string) $made : $this->textLike($faker, $text);

        if ($length !== null && mb_strlen($made) > $length) {
            $made = rtrim(mb_substr($made, 0, $length));
        }

        if ($made === $text) {
            $made = $this->shape($faker, $text);
        }

        return $made;
    }

    /**
     * A Faker formatter by name — only one the catalog or the locale lists.
     * A formatter the locale lacks falls back to the same shape.
     */
    protected function call(Generator $faker, string $formatter, string $original): mixed
    {
        if (! $this->known($formatter)) {
            return $this->shape($faker, $original);
        }

        try {
            return $faker->format($formatter);
        } catch (Throwable) {
            return $this->shape($faker, $original);
        }
    }

    /**
     * Every digit a new digit, every letter a new letter of the same case;
     * separators, spaces, and length stay. `+39 333 123 4567` stays a phone,
     * `RSSMRA80A01H501U` stays sixteen characters of the same pattern.
     */
    protected function shape(Generator $faker, string $text): string
    {
        $out = '';

        foreach (mb_str_split($text) as $index => $char) {
            $out .= match (true) {
                ctype_digit($char) => (string) ($index === 0 && $char !== '0' ? $faker->numberBetween(1, 9) : $faker->randomDigit()),
                ctype_upper($char) => chr($faker->numberBetween(65, 90)),
                ctype_lower($char) => chr($faker->numberBetween(97, 122)),
                default => $char,
            };
        }

        return $out === $text && $out !== '' ? $this->shape($faker, $text.'0') : $out;
    }

    /**
     * Words of about the length of the original.
     */
    protected function textLike(Generator $faker, string $text): string
    {
        $length = mb_strlen($text);

        if ($length <= 12) {
            return mb_substr($faker->word(), 0, max(1, $length));
        }

        $made = $length <= 60 ? $faker->sentence(max(2, intdiv($length, 7))) : $faker->text(max(80, min(2000, $length)));

        return mb_strlen($made) > $length + 20 ? rtrim(mb_substr($made, 0, $length)) : $made;
    }

    /**
     * A username Faker makes, then a few characters keyed to the original
     * so two people never share one — keyed, so they tell nothing.
     */
    protected function uniqueLocalPart(Generator $faker, string $table, string $column, string $original): string
    {
        $base = preg_replace('/[^a-z0-9._-]+/', '', strtolower($faker->userName())) ?: 'user';

        return $base.'.'.substr(hash_hmac('sha256', $table."\0".$column."\0".$original, $this->key()), 0, 8);
    }

    protected function looksLikeJson(string $text): bool
    {
        $text = ltrim($text);

        return str_starts_with($text, '{') || str_starts_with($text, '[');
    }

    /**
     * A JSON document with every string replaced and every key kept.
     */
    protected function scrub(mixed $value, string $table, string $column): mixed
    {
        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[$key] = $this->scrub($item, $table, $column.'.'.(string) $key);
            }

            return $out;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        $faker = $this->generator();
        $faker->seed($this->seed($table, $column, $value, 'json'));
        $made = str_contains($value, '@') ? $this->uniqueLocalPart($faker, $table, $column, $value).'@example.com' : $this->textLike($faker, $value);

        return $made === $value ? $this->shape($faker, $value) : $made;
    }

    /**
     * The seed of one replacement: an HMAC of the value keyed with the
     * application key, so the same value always gives the same
     * replacement here and nothing about it anywhere else.
     */
    protected function seed(string $table, string $column, string $value, string $context = 'row'): int
    {
        return (int) hexdec(substr(hash_hmac('sha256', $context."\0".$table."\0".$column."\0".$value, $this->key()), 0, 12));
    }

    protected function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $key = (string) config('app.key', '');

        // No key: the replacements are still random, they only change from
        // one download to the next.
        return $this->key = $key !== '' ? 'larapilot-anonymize|'.$key : bin2hex(random_bytes(32));
    }

    protected function generator(): Generator
    {
        $locale = $this->locale();

        return $this->generators[$locale] ??= Factory::create($locale);
    }

    protected function length(string $type): ?int
    {
        if (preg_match('/^(?:n?var)?char\s*\(\s*(\d+)\s*\)/i', trim($type), $match) === 1) {
            return max(1, (int) $match[1]);
        }

        return null;
    }

    /**
     * @return array<string, true>
     */
    protected function commonKeys(): array
    {
        static $keys = null;

        if ($keys === null) {
            $keys = [];

            foreach (self::CATALOG as $formatters) {
                foreach (array_keys($formatters) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        return $keys;
    }

    protected function providerRoot(): string
    {
        return dirname((string) (new ReflectionClass(Factory::class))->getFileName()).DIRECTORY_SEPARATOR.'Provider';
    }

    protected function localeLabel(string $locale): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayName($locale, 'en');

            if (is_string($name) && $name !== '' && $name !== $locale) {
                return $name.' — '.$locale;
            }
        }

        return $locale;
    }

    protected function humanize(string $method): string
    {
        $words = preg_replace('/(?<!^)([A-Z])/', ' $1', $method) ?? $method;

        return ucfirst(strtolower($words));
    }
}
