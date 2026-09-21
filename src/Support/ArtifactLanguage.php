<?php

declare(strict_types=1);

namespace Larapilot\Support;

class ArtifactLanguage
{
    public const DEFAULT = 'en';

    /**
     * Detect the artifact language from PRD / backlog markdown.
     * Distinctive heading labels beat generic word counts.
     */
    public static function detect(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return self::DEFAULT;
        }

        $scores = ['en' => 0, 'it' => 0, 'es' => 0, 'fr' => 0];

        foreach (self::headingHints() as $lang => $labels) {
            foreach ($labels as $label) {
                if (preg_match('/^(?:##|\*\*)\s*'.preg_quote($label, '/').'(?:\s*\*\*)?\s*$/mi', $content) === 1) {
                    $scores[$lang] += 3;
                }
            }
        }

        foreach (self::wordHints() as $lang => $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $scores[$lang] += 2;
            }
        }

        arsort($scores);
        $top = array_key_first($scores);

        if ($top === null || $scores[$top] === 0) {
            return self::DEFAULT;
        }

        return $top;
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function headingHints(): array
    {
        return [
            'en' => ['Elevator Pitch', 'Functional Requirements', 'MVP Scope', 'Technical Architecture', 'Acceptance Criteria', 'User Story'],
            'it' => ['Sintesi', 'Visione', 'Requisiti funzionali', 'Ambito MVP', 'Architettura tecnica', 'Criteri di Accettazione', 'Storia Utente', 'Personas utente'],
            'es' => ['Requisitos funcionales', 'Alcance MVP', 'Arquitectura técnica', 'Criterios de aceptación', 'Historia de usuario', 'Personas de usuario'],
            'fr' => ['Accroche', 'Exigences fonctionnelles', 'Périmètre MVP', 'Architecture technique', 'Critères d\'acceptation'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function wordHints(): array
    {
        return [
            'it' => '/\b(requisiti|visione|ambito|preventivo|utente|consegna|manutenzione)\b/iu',
            'es' => '/\b(requisitos|alcance|arquitectura|mantenimiento|entrega)\b/iu',
            'fr' => '/\b(exigences|périmètre|architecture|maintenance|livraison)\b/iu',
            'en' => '/\b(requirements|architecture|acceptance criteria|maintenance|deliverable)\b/iu',
        ];
    }
}
