<?php

declare(strict_types=1);

use Larapilot\Services\EconomicsQuoteWriter;
use Larapilot\Services\MockupPackageService;
use Larapilot\Support\ArtifactLanguage;
use Larapilot\Support\EconomicsStrings;

/**
 * Larapilot writes prose in three places — the Economics page and engine, the
 * client quote, and the design presentation — each with its own vocabulary.
 * A language added to `ArtifactLanguage::SUPPORTED` but forgotten in one of
 * them would silently render English there, which is worse than not offering
 * the language at all. These tests make that impossible to ship.
 */

/**
 * @return array<string, mixed>
 */
function rawTable(object|string $target, string $method, mixed ...$args): array
{
    $reflection = new ReflectionMethod($target, $method);

    /** @var array<string, mixed> $result */
    $result = $reflection->invoke(is_string($target) ? null : $target, ...$args);

    return $result;
}

/**
 * @param  array<string, mixed>  $reference
 * @param  array<string, mixed>  $candidate
 * @return list<string>
 */
function structureGaps(array $reference, array $candidate, string $path = ''): array
{
    $gaps = [];

    foreach ($reference as $key => $value) {
        $here = $path === '' ? (string) $key : $path.'.'.$key;

        if (! array_key_exists($key, $candidate)) {
            $gaps[] = $here.' — missing';

            continue;
        }

        if (is_array($value)) {
            $gaps = array_merge($gaps, structureGaps($value, (array) $candidate[$key], $here));

            continue;
        }

        if (! is_string($candidate[$key]) || trim($candidate[$key]) === '') {
            $gaps[] = $here.' — empty';

            continue;
        }

        // A placeholder dropped in translation renders a sentence with a hole
        // in it, so the counts have to match the English exactly.
        if (substr_count((string) $value, '%s') !== substr_count($candidate[$key], '%s')) {
            $gaps[] = $here.' — %s placeholder count differs';
        }
    }

    return $gaps;
}

it('carries the whole Economics vocabulary in every supported language', function (): void {
    $english = rawTable(EconomicsStrings::class, 'en');
    $tables = rawTable(EconomicsStrings::class, 'table');

    expect($english)->not->toBeEmpty();

    foreach (ArtifactLanguage::SUPPORTED as $lang) {
        if ($lang === ArtifactLanguage::DEFAULT) {
            continue;
        }

        expect(array_key_exists($lang, $tables))->toBeTrue("no Economics table for '{$lang}'");

        $gaps = structureGaps($english, (array) $tables[$lang]);

        expect($gaps)->toBe([], "Economics strings for '{$lang}': ".implode(' · ', array_slice($gaps, 0, 8)));
    }
});

it('keeps the :name placeholders of every Economics sentence', function (): void {
    $english = rawTable(EconomicsStrings::class, 'en');
    $tables = rawTable(EconomicsStrings::class, 'table');

    $placeholders = static function (string $line): array {
        preg_match_all('/:[a-z_]+/', $line, $matches);
        $found = array_unique($matches[0]);
        sort($found);

        return $found;
    };

    foreach (ArtifactLanguage::SUPPORTED as $lang) {
        if ($lang === ArtifactLanguage::DEFAULT) {
            continue;
        }

        foreach ($english as $key => $line) {
            // LTV:CAC reads as a placeholder to the regex and carries none.
            if (str_contains((string) $line, 'LTV:CAC')) {
                continue;
            }

            $expected = $placeholders((string) $line);
            $actual = $placeholders((string) $tables[$lang][$key]);

            expect($actual)->toBe($expected, "{$lang} / {$key}: placeholders ".implode(',', $actual).' vs '.implode(',', $expected));
        }
    }
});

it('carries the whole client quote in every supported language', function (): void {
    $writer = (new ReflectionClass(EconomicsQuoteWriter::class))->newInstanceWithoutConstructor();
    $english = rawTable($writer, 'strings', 'en');

    expect($english)->not->toBeEmpty();

    foreach (ArtifactLanguage::SUPPORTED as $lang) {
        if ($lang === ArtifactLanguage::DEFAULT) {
            continue;
        }

        $table = rawTable($writer, 'strings', $lang);

        // An unsupported language falls through to the English arm, which is
        // exactly what this test exists to catch.
        expect($table['document_title'])->not->toBe($english['document_title'], "the quote has no '{$lang}' arm — it falls back to English");

        $gaps = structureGaps($english, $table);

        expect($gaps)->toBe([], "quote strings for '{$lang}': ".implode(' · ', array_slice($gaps, 0, 8)));
    }
});

it('carries the design presentation copy in every supported language', function (): void {
    $service = (new ReflectionClass(MockupPackageService::class))->newInstanceWithoutConstructor();
    $english = rawTable($service, 'copy', 'en');

    foreach (ArtifactLanguage::SUPPORTED as $lang) {
        if ($lang === ArtifactLanguage::DEFAULT) {
            continue;
        }

        $copy = rawTable($service, 'copy', $lang);

        expect($copy['kicker'])->not->toBe($english['kicker'], "the design presentation has no '{$lang}' arm");
        expect(structureGaps($english, $copy))->toBe([]);
    }
});

it('names the quote file and writes its date in every supported language', function (): void {
    $writer = (new ReflectionClass(EconomicsQuoteWriter::class))->newInstanceWithoutConstructor();
    $dateMethod = new ReflectionMethod($writer, 'formatDate');
    $when = new DateTimeImmutable('2026-03-14');

    $filenames = [];
    $dates = [];

    foreach (ArtifactLanguage::SUPPORTED as $lang) {
        $filenames[$lang] = $writer->filename('# Star Service', $lang);
        $dates[$lang] = (string) $dateMethod->invoke($writer, $when, $lang);

        expect($dates[$lang])->toStartWith('14 ')->toEndWith(' 2026');
    }

    // Italian and Spanish genuinely share "marzo", so the guard is that no
    // language silently falls back to the English month, not that all eight
    // differ from each other.
    foreach (ArtifactLanguage::SUPPORTED as $lang) {
        if ($lang === ArtifactLanguage::DEFAULT) {
            continue;
        }

        expect($dates[$lang])->not->toBe($dates['en'], "the quote date has no '{$lang}' month names");
        expect($filenames[$lang])->not->toBe($filenames['en'], "the quote filename has no '{$lang}' suffix");
    }

    expect($filenames['de'])->toBe('star-service-angebot.md')
        ->and($filenames['pl'])->toBe('star-service-oferta.md')
        ->and($dates['pl'])->toBe('14 marca 2026')
        ->and($dates['nl'])->toBe('14 maart 2026');
});

it('detects each supported language from a PRD written in it', function (string $lang, string $prd): void {
    expect(ArtifactLanguage::detect($prd))->toBe($lang);
})->with([
    ['en', "# Management system\n\n## Overview\nThe system manages customers, jobs and invoicing for the company. Each user only sees the data that is assigned to them.\n\n## Business Goals\n- Reduce the time it takes to enter a job\n- Have a searchable history of the customers\n\n## Functional Requirements\n- Customer records with filters and export\n- Job management with attachments"],
    ['it', "# Gestionale\n\n## Panoramica\nIl gestionale serve a gestire clienti, interventi e fatturazione per l'azienda. Ogni utente vede solo i dati che gli sono assegnati.\n\n## Obiettivi di business\n- Ridurre il tempo di inserimento degli interventi\n- Avere uno storico consultabile dei clienti\n\n## Funzionalità principali\n- Anagrafica clienti con filtri ed esportazione\n- Gestione interventi con allegati"],
    ['es', "# Sistema de gestión\n\n## Resumen\nEl sistema sirve para gestionar clientes, intervenciones y facturación de la empresa. Cada usuario solo ve los datos que tiene asignados.\n\n## Objetivos\n- Reducir el tiempo de introducción de las intervenciones\n- Tener un histórico consultable de los clientes\n\n## Funcionalidades\n- Ficha de clientes con filtros y exportación\n- Gestión de intervenciones con adjuntos"],
    ['fr', "# Logiciel de gestion\n\n## Synthèse\nLe logiciel sert à gérer les clients, les interventions et la facturation de l'entreprise. Chaque utilisateur ne voit que les données qui lui sont attribuées.\n\n## Objectifs\n- Réduire le temps de saisie des interventions\n- Disposer d'un historique consultable des clients\n\n## Fonctionnalités\n- Fiches clients avec filtres et export\n- Gestion des interventions avec pièces jointes"],
    ['de', "# Verwaltungssystem\n\n## Überblick\nDas System verwaltet Kunden, Einsätze und die Rechnungsstellung für das Unternehmen. Jeder Benutzer kann nur die Daten sehen, die ihm zugewiesen sind.\n\n## Geschäftsziele\n- Die Zeit für die Erfassung der Einsätze reduzieren\n- Eine durchsuchbare Historie der Kunden haben\n\n## Funktionen\n- Kundenstammdaten mit Filtern und Export\n- Verwaltung der Einsätze mit Anhängen"],
    ['pt', "# Sistema de gestão\n\n## Visão geral\nO sistema serve para gerir clientes, intervenções e faturação da empresa. Cada utilizador só vê os dados que lhe são atribuídos.\n\n## Objetivos de negócio\n- Reduzir o tempo de introdução das intervenções\n- Ter um histórico consultável dos clientes\n\n## Funcionalidades\n- Ficha de clientes com filtros e exportação\n- Gestão das intervenções com anexos"],
    ['nl', "# Beheersysteem\n\n## Overzicht\nHet systeem beheert klanten, interventies en de facturatie van het bedrijf. Elke gebruiker ziet alleen de gegevens die aan hem zijn toegewezen.\n\n## Bedrijfsdoelen\n- De tijd voor het invoeren van interventies verkorten\n- Een doorzoekbare historie van de klanten hebben\n\n## Functionaliteiten\n- Klantenbestand met filters en export\n- Beheer van interventies met bijlagen"],
    ['pl', "# System zarządzania\n\n## Przegląd\nSystem służy do zarządzania klientami, interwencjami i fakturowaniem w firmie. Każdy użytkownik widzi tylko te dane, które są mu przypisane.\n\n## Cele biznesowe\n- Skrócić czas wprowadzania interwencji\n- Mieć przeszukiwalną historię klientów\n\n## Funkcjonalności\n- Kartoteka klientów z filtrami oraz eksportem\n- Zarządzanie interwencjami wraz z załącznikami"],
]);
