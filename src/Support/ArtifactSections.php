<?php

declare(strict_types=1);

namespace Larapilot\Support;

class ArtifactSections
{
    /**
     * @return array<string, list<string>>
     */
    public static function prd(): array
    {
        return [
            'Elevator Pitch' => ['Elevator Pitch', 'Pitch', 'Sintesi', 'Accroche'],
            'Vision' => ['Vision', 'Visione', 'Visión'],
            'User Personas' => ['User Personas', 'Personas', 'Personas utente', 'Personas de usuario'],
            'Functional Requirements' => ['Functional Requirements', 'Requisiti funzionali', 'Requisitos funcionales', 'Exigences fonctionnelles'],
            'MVP Scope' => ['MVP Scope', 'Ambito MVP', 'Alcance MVP', 'Périmètre MVP'],
            'Technical Architecture' => ['Technical Architecture', 'Architettura tecnica', 'Arquitectura técnica', 'Architecture technique'],
        ];
    }

    /**
     * Sections a fresh inception writes but older PRDs may lack — reported as warnings.
     *
     * @return array<string, list<string>>
     */
    public static function prdRecommended(): array
    {
        return [
            'User Journeys' => ['User Journeys', 'Journeys', 'Percorsi utente', 'Recorridos de usuario', 'Parcours utilisateur'],
            'Domain Model' => ['Domain Model', 'Modello di dominio', 'Modelo de dominio', 'Modèle de domaine'],
            'Non-Functional Requirements' => ['Non-Functional Requirements', 'Non Functional Requirements', 'Requisiti non funzionali', 'Requisitos no funcionales', 'Exigences non fonctionnelles'],
            'Risks & Assumptions' => ['Risks & Assumptions', 'Risks and Assumptions', 'Rischi e assunzioni', 'Rischi e ipotesi', 'Riesgos y supuestos', 'Risques et hypothèses'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function spec(): array
    {
        return [
            'User Story' => ['User Story', 'Storia Utente', 'Historia de usuario', 'User story'],
            'Demonstrates' => ['Demonstrates', 'Dimostra', 'Demuestra', 'Démontre'],
            'Acceptance Criteria' => ['Acceptance Criteria', 'Criteri di Accettazione', 'Criterios de aceptación', 'Critères d\'acceptation'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function taskDescription(): array
    {
        return ['Description', 'Descrizione', 'Descripción', 'Description'];
    }

    public static function minimumPrdHeadings(): int
    {
        return count(self::prd());
    }

    public static function minimumSpecSections(): int
    {
        return count(self::spec());
    }
}
