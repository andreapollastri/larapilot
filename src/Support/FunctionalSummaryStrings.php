<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * Headings of the functional-analysis summary downloaded from the PRD page.
 * The requirement text itself stays in the PRD's own words.
 */
class FunctionalSummaryStrings
{
    /**
     * @param  array<string, string|int|float>  $replace
     */
    public static function line(string $lang, string $key, array $replace = []): string
    {
        $line = self::for($lang)[$key] ?? $key;

        if ($replace === []) {
            return $line;
        }

        $pairs = [];

        foreach ($replace as $name => $value) {
            $pairs[':'.$name] = (string) $value;
        }

        return strtr($line, $pairs);
    }

    /**
     * @return array<string, string>
     */
    public static function for(string $lang): array
    {
        $lang = in_array($lang, ArtifactLanguage::SUPPORTED, true) ? $lang : ArtifactLanguage::DEFAULT;
        $english = self::en();

        return $lang === ArtifactLanguage::DEFAULT
            ? $english
            : array_replace($english, self::table()[$lang] ?? []);
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected static function table(): array
    {
        return [
            'it' => self::it(),
            'es' => self::es(),
            'fr' => self::fr(),
            'de' => self::de(),
            'pt' => self::pt(),
            'nl' => self::nl(),
            'pl' => self::pl(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function en(): array
    {
        return [
            'title' => 'Functional analysis summary',
            'lead' => 'What the product does, one point at a time. Required first, then what matters, then what can wait.',
            'product' => 'Product',
            'date' => 'Date',
            'brief' => 'In one sentence',
            'who' => 'Who uses it',
            'what' => 'What it does',
            'must' => 'Required',
            'should' => 'Important',
            'could' => 'Useful, not required',
            'wont' => 'Not in this version',
            'open' => 'Priority not set',
            'included' => 'Included',
            'excluded' => 'Left out',
            'empty' => 'The functional analysis is not written yet.',
            'file' => 'functional-summary',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function it(): array
    {
        return [
            'title' => 'Sintesi di analisi funzionale',
            'lead' => 'Cosa fa il prodotto, un punto alla volta. Prima l\'indispensabile, poi ciò che conta, poi ciò che può aspettare.',
            'product' => 'Prodotto',
            'date' => 'Data',
            'brief' => 'In una frase',
            'who' => 'Chi lo usa',
            'what' => 'Cosa deve fare',
            'must' => 'Indispensabile',
            'should' => 'Importante',
            'could' => 'Utile, non necessario',
            'wont' => 'Non in questa versione',
            'open' => 'Priorità non indicata',
            'included' => 'Dentro questa versione',
            'excluded' => 'Fuori da questa versione',
            'empty' => 'L\'analisi funzionale non è ancora scritta.',
            'file' => 'sintesi-funzionale',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function es(): array
    {
        return [
            'title' => 'Síntesis de análisis funcional',
            'lead' => 'Qué hace el producto, un punto cada vez. Primero lo indispensable, luego lo importante, luego lo que puede esperar.',
            'product' => 'Producto',
            'date' => 'Fecha',
            'brief' => 'En una frase',
            'who' => 'Quién lo usa',
            'what' => 'Qué debe hacer',
            'must' => 'Imprescindible',
            'should' => 'Importante',
            'could' => 'Útil, no necesario',
            'wont' => 'No en esta versión',
            'open' => 'Prioridad sin indicar',
            'included' => 'Dentro de esta versión',
            'excluded' => 'Fuera de esta versión',
            'empty' => 'El análisis funcional aún no está escrito.',
            'file' => 'sintesis-funcional',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function fr(): array
    {
        return [
            'title' => 'Synthèse d\'analyse fonctionnelle',
            'lead' => 'Ce que fait le produit, un point à la fois. D\'abord l\'indispensable, puis ce qui compte, puis ce qui peut attendre.',
            'product' => 'Produit',
            'date' => 'Date',
            'brief' => 'En une phrase',
            'who' => 'Qui l\'utilise',
            'what' => 'Ce qu\'il doit faire',
            'must' => 'Indispensable',
            'should' => 'Important',
            'could' => 'Utile, pas nécessaire',
            'wont' => 'Pas dans cette version',
            'open' => 'Priorité non indiquée',
            'included' => 'Dans cette version',
            'excluded' => 'Hors de cette version',
            'empty' => 'L\'analyse fonctionnelle n\'est pas encore écrite.',
            'file' => 'synthese-fonctionnelle',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function de(): array
    {
        return [
            'title' => 'Zusammenfassung der funktionalen Analyse',
            'lead' => 'Was das Produkt tut, Punkt für Punkt. Zuerst das Nötige, dann das Wichtige, dann was warten kann.',
            'product' => 'Produkt',
            'date' => 'Datum',
            'brief' => 'In einem Satz',
            'who' => 'Wer es nutzt',
            'what' => 'Was es tun muss',
            'must' => 'Unverzichtbar',
            'should' => 'Wichtig',
            'could' => 'Nützlich, nicht nötig',
            'wont' => 'Nicht in dieser Version',
            'open' => 'Priorität offen',
            'included' => 'In dieser Version',
            'excluded' => 'Außerhalb dieser Version',
            'empty' => 'Die funktionale Analyse ist noch nicht geschrieben.',
            'file' => 'funktionale-zusammenfassung',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function pt(): array
    {
        return [
            'title' => 'Síntese de análise funcional',
            'lead' => 'O que o produto faz, um ponto de cada vez. Primeiro o indispensável, depois o que importa, depois o que pode esperar.',
            'product' => 'Produto',
            'date' => 'Data',
            'brief' => 'Numa frase',
            'who' => 'Quem o usa',
            'what' => 'O que deve fazer',
            'must' => 'Indispensável',
            'should' => 'Importante',
            'could' => 'Útil, não necessário',
            'wont' => 'Não nesta versão',
            'open' => 'Prioridade por indicar',
            'included' => 'Nesta versão',
            'excluded' => 'Fora desta versão',
            'empty' => 'A análise funcional ainda não está escrita.',
            'file' => 'sintese-funcional',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function nl(): array
    {
        return [
            'title' => 'Samenvatting van de functionele analyse',
            'lead' => 'Wat het product doet, punt voor punt. Eerst het nodige, dan wat telt, dan wat kan wachten.',
            'product' => 'Product',
            'date' => 'Datum',
            'brief' => 'In één zin',
            'who' => 'Wie het gebruikt',
            'what' => 'Wat het moet doen',
            'must' => 'Onmisbaar',
            'should' => 'Belangrijk',
            'could' => 'Nuttig, niet nodig',
            'wont' => 'Niet in deze versie',
            'open' => 'Prioriteit niet gezet',
            'included' => 'In deze versie',
            'excluded' => 'Buiten deze versie',
            'empty' => 'De functionele analyse is nog niet geschreven.',
            'file' => 'functionele-samenvatting',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function pl(): array
    {
        return [
            'title' => 'Synteza analizy funkcjonalnej',
            'lead' => 'Co robi produkt, punkt po punkcie. Najpierw to, bez czego się nie da, potem to, co ważne, potem to, co może poczekać.',
            'product' => 'Produkt',
            'date' => 'Data',
            'brief' => 'W jednym zdaniu',
            'who' => 'Kto z tego korzysta',
            'what' => 'Co ma robić',
            'must' => 'Niezbędne',
            'should' => 'Ważne',
            'could' => 'Przydatne, niekonieczne',
            'wont' => 'Nie w tej wersji',
            'open' => 'Priorytet nieustalony',
            'included' => 'W tej wersji',
            'excluded' => 'Poza tą wersją',
            'empty' => 'Analiza funkcjonalna nie jest jeszcze napisana.',
            'file' => 'synteza-funkcjonalna',
        ];
    }
}
