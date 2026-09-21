<?php

declare(strict_types=1);

namespace Larapilot\Support;

class ArtifactLanguage
{
    public const DEFAULT = 'en';

    /** @var list<string> */
    public const SUPPORTED = ['en', 'it', 'es', 'fr'];

    /**
     * Minimum stopword hits before frequency scoring is trusted.
     */
    protected const MIN_SIGNAL = 4.0;

    /**
     * Tokens scanned per document — enough signal for any PRD, bounded cost.
     */
    protected const MAX_TOKENS = 6000;

    /**
     * @var array<string, list<string>>|null
     */
    protected static ?array $stopwordIndex = null;

    /**
     * Detect the artifact language from PRD / backlog markdown.
     *
     * Only for prose Larapilot itself emits — the design presentation index and
     * the built-in quote template. Anything an agent writes carries its own
     * language and never comes through here.
     *
     * Headings are a strong signal; the bulk of the decision comes from
     * function-word frequency, which survives PRDs written in one language
     * that still carry English technical vocabulary (Laravel, requirements,
     * acceptance criteria, deploy …).
     */
    public static function detect(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return self::DEFAULT;
        }

        $scores = array_fill_keys(self::SUPPORTED, 0.0);

        foreach (self::headingHints() as $lang => $labels) {
            foreach ($labels as $label) {
                if (preg_match('/^(?:#{1,4}|\*\*)\s*'.preg_quote($label, '/').'(?:\s*\*\*)?\s*$/mi', $content) === 1) {
                    $scores[$lang] += 6.0;
                }
            }
        }

        $words = 0.0;

        foreach (self::tokens($content) as $token) {
            foreach (self::stopwordIndex()[$token] ?? [] as $lang) {
                $scores[$lang] += 1.0;
                $words += 1.0;
            }
        }

        arsort($scores);
        $top = (string) array_key_first($scores);
        $best = $scores[$top];

        if ($best <= 0.0 || ($words < self::MIN_SIGNAL && $best < 6.0)) {
            return self::DEFAULT;
        }

        // Stable-sort ties would hand the win to whichever bucket is declared
        // first, so an exact draw falls back to the default language.
        $tied = array_keys($scores, $best, true);

        if (count($tied) > 1) {
            return in_array(self::DEFAULT, $tied, true) ? self::DEFAULT : (string) $tied[0];
        }

        return $top;
    }

    /**
     * Any well-formed language tag, for documents an agent writes in a language
     * the built-in templates do not carry (de, pt-BR, nl, pl, …).
     */
    public static function normalizeTag(?string $language): ?string
    {
        $value = strtolower(trim((string) $language));
        $value = str_replace('_', '-', $value);

        return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $value) === 1 ? $value : null;
    }

    /**
     * Lowercase word tokens, fenced code and inline code stripped.
     *
     * @return list<string>
     */
    protected static function tokens(string $content): array
    {
        $text = (string) preg_replace('/```.*?```/s', ' ', $content);
        $text = (string) preg_replace('/`[^`]*`/', ' ', $text);
        $text = mb_strtolower($text, 'UTF-8');

        preg_match_all('/[\p{L}\'’]+/u', $text, $matches);

        return array_slice($matches[0], 0, self::MAX_TOKENS);
    }

    /**
     * word => languages that count it as a function word.
     *
     * @return array<string, list<string>>
     */
    protected static function stopwordIndex(): array
    {
        if (self::$stopwordIndex !== null) {
            return self::$stopwordIndex;
        }

        $index = [];

        foreach (self::stopwords() as $lang => $words) {
            foreach ($words as $word) {
                $index[$word][] = $lang;
            }
        }

        return self::$stopwordIndex = $index;
    }

    /**
     * Function words and product vocabulary per language. Lists are kept at a
     * comparable size so raw hit counts stay directly comparable.
     *
     * @return array<string, list<string>>
     */
    protected static function stopwords(): array
    {
        return [
            'en' => [
                'the', 'and', 'of', 'to', 'in', 'is', 'are', 'for', 'with', 'that',
                'this', 'these', 'must', 'should', 'will', 'can', 'be', 'as', 'from', 'when',
                'each', 'all', 'user', 'users', 'page', 'screen', 'data', 'also', 'but', 'on',
                'by', 'an', 'it', 'we', 'they', 'has', 'have', 'not', 'or', 'if',
                'their', 'there', 'which', 'while', 'about', 'into', 'then', 'only', 'other', 'between',
                'at', 'does', 'any', 'more', 'than',
            ],
            'it' => [
                'il', 'lo', 'gli', 'la', 'le', 'un', 'una', 'di', 'del', 'della',
                'delle', 'degli', 'dei', 'nel', 'nella', 'alla', 'agli', 'dal', 'dalla', 'sulla',
                'che', 'con', 'per', 'non', 'più', 'come', 'anche', 'perché', 'però', 'quindi',
                'sono', 'essere', 'viene', 'deve', 'può', 'ogni', 'questo', 'questa', 'quando', 'oppure',
                'utente', 'utenti', 'pagina', 'schermata', 'elenco', 'gestione', 'dati', 'inoltre', 'tutti', 'tutte',
                'nei', 'sui', 'allo', 'negli', 'tra',
            ],
            'es' => [
                'el', 'los', 'las', 'la', 'un', 'una', 'del', 'de', 'que', 'con',
                'para', 'por', 'se', 'su', 'sus', 'al', 'lo', 'es', 'son', 'está',
                'están', 'hay', 'como', 'debe', 'puede', 'ser', 'más', 'también', 'pero', 'cuando',
                'desde', 'entre', 'sobre', 'este', 'esta', 'cada', 'usuario', 'usuarios', 'pantalla', 'página',
                'datos', 'además', 'todos', 'todas', 'donde', 'porque', 'mismo', 'ellos', 'nuestro', 'gestión',
                'sin', 'hasta', 'tras', 'ya', 'les',
            ],
            'fr' => [
                'le', 'la', 'les', 'des', 'du', 'de', 'une', 'un', 'que', 'qui',
                'avec', 'pour', 'dans', 'par', 'sur', 'est', 'sont', 'être', 'doit', 'peut',
                'au', 'aux', 'et', 'ou', 'ne', 'pas', 'plus', 'aussi', 'mais', 'quand',
                'depuis', 'entre', 'ce', 'cette', 'chaque', 'tous', 'toutes', 'utilisateur', 'utilisateurs', 'écran',
                'page', 'données', 'gestion', 'ainsi', 'donc', 'leur', 'notre', 'où', 'parce', 'même',
                'sans', 'jusqu', 'déjà', 'afin', 'lors',
            ],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function headingHints(): array
    {
        return [
            'en' => ['Elevator Pitch', 'Functional Requirements', 'MVP Scope', 'Technical Architecture', 'Acceptance Criteria', 'User Story', 'User Personas', 'Overview', 'Business Goals', 'Out of Scope'],
            'it' => ['Sintesi', 'Visione', 'Requisiti funzionali', 'Ambito MVP', 'Architettura tecnica', 'Criteri di Accettazione', 'Storia Utente', 'Personas utente', 'Panoramica', 'Obiettivi', 'Obiettivi di business', 'Funzionalità', 'Funzionalità principali', 'Utenti e ruoli', 'Fuori ambito', 'Architettura'],
            'es' => ['Requisitos funcionales', 'Alcance MVP', 'Arquitectura técnica', 'Criterios de aceptación', 'Historia de usuario', 'Personas de usuario', 'Resumen', 'Objetivos', 'Funcionalidades', 'Fuera de alcance'],
            'fr' => ['Accroche', 'Exigences fonctionnelles', 'Périmètre MVP', 'Architecture technique', 'Critères d\'acceptation', 'Synthèse', 'Objectifs', 'Fonctionnalités', 'Hors périmètre'],
        ];
    }
}
