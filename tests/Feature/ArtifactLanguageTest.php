<?php

declare(strict_types=1);

use Larapilot\Support\ArtifactLanguage;

it('detects the language from function words, not from technical vocabulary', function (): void {
    $italian = <<<'MD'
# Gestionale interventi

## Panoramica
Il gestionale serve a registrare i clienti e gli interventi dei tecnici.

## Funzionalità principali
- Anagrafica dei clienti con storico
- Gestione degli interventi e degli allegati

## Architettura
Laravel 12 con MySQL. Requirements: PHP 8.3. Acceptance criteria nel backlog.
MD;

    $english = <<<'MD'
# Field service app

## Overview
The app records the customers and the work each technician does on site.

## Functional Requirements
- Customer list with history
- Work orders with attachments
MD;

    $spanish = <<<'MD'
# Gestión de intervenciones

## Resumen
La aplicación registra los clientes y las intervenciones de los técnicos.

## Funcionalidades
- Listado de clientes con historial
- Gestión de las intervenciones y de los adjuntos
MD;

    $french = <<<'MD'
# Gestion des interventions

## Synthèse
L'application enregistre les clients et les interventions que les techniciens réalisent.

## Fonctionnalités
- Liste des clients avec l'historique
- Gestion des interventions et des pièces jointes
MD;

    expect(ArtifactLanguage::detect($italian))->toBe('it')
        ->and(ArtifactLanguage::detect($english))->toBe('en')
        ->and(ArtifactLanguage::detect($spanish))->toBe('es')
        ->and(ArtifactLanguage::detect($french))->toBe('fr');
});

it('defaults to English when there is nothing to read', function (): void {
    expect(ArtifactLanguage::detect(null))->toBe('en')
        ->and(ArtifactLanguage::detect(''))->toBe('en')
        ->and(ArtifactLanguage::detect("# Tool\n\n```php\n\$x = 1;\n```"))->toBe('en');
});

it('accepts any language tag for a document an agent wrote', function (): void {
    expect(ArtifactLanguage::normalizeTag('pt_BR'))->toBe('pt-br')
        ->and(ArtifactLanguage::normalizeTag('DE'))->toBe('de')
        ->and(ArtifactLanguage::normalizeTag('  it  '))->toBe('it')
        ->and(ArtifactLanguage::normalizeTag('not a language'))->toBeNull()
        ->and(ArtifactLanguage::normalizeTag(null))->toBeNull();
});
