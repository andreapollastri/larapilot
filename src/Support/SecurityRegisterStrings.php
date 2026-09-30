<?php

declare(strict_types=1);

namespace Larapilot\Support;

/**
 * The words of the register of security findings given to the client. The
 * title of a finding, and the reason it was accepted with, stay as they were
 * written; only the frame is translated.
 */
class SecurityRegisterStrings
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
            'title' => 'Security findings register',
            'lead' => 'Every security finding of the repository, with its state and what was decided about it: what is open, what was resolved, and what was accepted with a reason.',
            'repository' => 'Repository',
            'branch' => 'Branch',
            'scanner' => 'Scanner',
            'scanner_value' => 'Aikido — dependencies, code, secrets, and configuration',
            'last_scan' => 'Last scan',
            'generated' => 'Generated',
            'summary' => 'Summary',
            'severity' => 'Severity',
            'open' => 'Open',
            'resolved' => 'Resolved',
            'ignored' => 'Ignored',
            'total' => 'Total',
            'open_heading' => 'Open findings',
            'resolved_heading' => 'Resolved findings',
            'ignored_heading' => 'Ignored findings',
            'ignored_lead' => 'Findings accepted as they are. Each one carries the reason it was accepted with.',
            'none_open' => 'No finding is open.',
            'none_resolved' => 'No finding was resolved yet.',
            'none_ignored' => 'No finding was ignored.',
            'col_kind' => 'Kind',
            'col_finding' => 'Finding',
            'col_first_seen' => 'First seen',
            'col_decision' => 'Decision',
            'col_resolved_on' => 'Resolved on',
            'col_resolved_by' => 'Resolved by',
            'col_ignored_on' => 'Ignored on',
            'col_reason' => 'Reason',
            'decision_none' => 'None yet',
            'decision_spec' => 'Fix planned: :spec',
            'decision_snoozed' => 'Postponed until :date',
            'decision_snoozed_open' => 'Postponed',
            'resolved_spec' => 'Fix delivered: :spec',
            'resolved_scanner' => 'No longer found by the scan',
            'reason_user' => 'Ignored by a person in Aikido, where the reason is kept.',
            'reason_rule' => 'Ignored by a rule of the workspace in Aikido.',
            'reason_auto' => 'Ignored by Aikido, which judged that it does not apply to this project.',
            'reason_unknown' => 'Ignored in Aikido, where the reason is kept.',
            'truncated' => 'The repository holds more findings than this register lists. Aikido holds the rest.',
            'footer' => 'The findings and their states are the ones Aikido reports for this repository. The decisions and their reasons are the ones recorded in the project.',
            'file' => 'security-register',
            'severity_critical' => 'Critical',
            'severity_high' => 'High',
            'severity_medium' => 'Medium',
            'severity_low' => 'Low',
            'type_open_source' => 'Vulnerable dependency',
            'type_leaked_secret' => 'Leaked secret',
            'type_sast' => 'Weakness in the code',
            'type_iac' => 'Infrastructure as code',
            'type_cloud' => 'Cloud configuration',
            'type_cloud_instance' => 'Cloud instance',
            'type_docker_container' => 'Container image',
            'type_surface_monitoring' => 'Exposed surface',
            'type_malware' => 'Malware in a dependency',
            'type_eol' => 'End-of-life runtime',
            'type_mobile' => 'Mobile application',
            'type_scm_security' => 'Repository settings',
            'type_ai_pentest' => 'Pentest finding',
            'type_license' => 'License risk',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function it(): array
    {
        return [
            'title' => 'Registro delle vulnerabilità',
            'lead' => 'Tutte le segnalazioni di sicurezza del repository, con il loro stato e la decisione presa: quelle aperte, quelle risolte e quelle accettate con una motivazione.',
            'repository' => 'Repository',
            'branch' => 'Branch',
            'scanner' => 'Strumento di analisi',
            'scanner_value' => 'Aikido — dipendenze, codice, segreti e configurazione',
            'last_scan' => 'Ultima scansione',
            'generated' => 'Generato il',
            'summary' => 'Riepilogo',
            'severity' => 'Gravità',
            'open' => 'Aperte',
            'resolved' => 'Risolte',
            'ignored' => 'Ignorate',
            'total' => 'Totale',
            'open_heading' => 'Segnalazioni aperte',
            'resolved_heading' => 'Segnalazioni risolte',
            'ignored_heading' => 'Segnalazioni ignorate',
            'ignored_lead' => 'Segnalazioni accettate così come sono. Ognuna riporta la motivazione con cui è stata accettata.',
            'none_open' => 'Nessuna segnalazione aperta.',
            'none_resolved' => 'Nessuna segnalazione risolta finora.',
            'none_ignored' => 'Nessuna segnalazione ignorata.',
            'col_kind' => 'Tipo',
            'col_finding' => 'Segnalazione',
            'col_first_seen' => 'Rilevata il',
            'col_decision' => 'Decisione',
            'col_resolved_on' => 'Risolta il',
            'col_resolved_by' => 'Risolta con',
            'col_ignored_on' => 'Ignorata il',
            'col_reason' => 'Motivazione',
            'decision_none' => 'Non ancora presa',
            'decision_spec' => 'Correzione pianificata: :spec',
            'decision_snoozed' => 'Rimandata al :date',
            'decision_snoozed_open' => 'Rimandata',
            'resolved_spec' => 'Correzione rilasciata: :spec',
            'resolved_scanner' => 'Non più rilevata dalla scansione',
            'reason_user' => 'Ignorata da una persona in Aikido, dove è conservata la motivazione.',
            'reason_rule' => 'Ignorata da una regola dello spazio di lavoro in Aikido.',
            'reason_auto' => 'Ignorata da Aikido, che l\'ha giudicata non applicabile a questo progetto.',
            'reason_unknown' => 'Ignorata in Aikido, dove è conservata la motivazione.',
            'truncated' => 'Il repository ha più segnalazioni di quelle elencate in questo registro. Le altre sono in Aikido.',
            'footer' => 'Le segnalazioni e i loro stati sono quelli che Aikido riporta per questo repository. Le decisioni e le motivazioni sono quelle registrate nel progetto.',
            'file' => 'registro-vulnerabilita',
            'severity_critical' => 'Critica',
            'severity_high' => 'Alta',
            'severity_medium' => 'Media',
            'severity_low' => 'Bassa',
            'type_open_source' => 'Dipendenza vulnerabile',
            'type_leaked_secret' => 'Segreto esposto',
            'type_sast' => 'Debolezza nel codice',
            'type_iac' => 'Infrastruttura come codice',
            'type_cloud' => 'Configurazione cloud',
            'type_cloud_instance' => 'Istanza cloud',
            'type_docker_container' => 'Immagine container',
            'type_surface_monitoring' => 'Superficie esposta',
            'type_malware' => 'Malware in una dipendenza',
            'type_eol' => 'Runtime a fine vita',
            'type_mobile' => 'Applicazione mobile',
            'type_scm_security' => 'Impostazioni del repository',
            'type_ai_pentest' => 'Esito di penetration test',
            'type_license' => 'Rischio di licenza',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function es(): array
    {
        return [
            'title' => 'Registro de vulnerabilidades',
            'lead' => 'Todos los hallazgos de seguridad del repositorio, con su estado y la decisión tomada: los abiertos, los resueltos y los aceptados con un motivo.',
            'repository' => 'Repositorio',
            'branch' => 'Rama',
            'scanner' => 'Herramienta de análisis',
            'scanner_value' => 'Aikido — dependencias, código, secretos y configuración',
            'last_scan' => 'Último análisis',
            'generated' => 'Generado el',
            'summary' => 'Resumen',
            'severity' => 'Gravedad',
            'open' => 'Abiertos',
            'resolved' => 'Resueltos',
            'ignored' => 'Ignorados',
            'total' => 'Total',
            'open_heading' => 'Hallazgos abiertos',
            'resolved_heading' => 'Hallazgos resueltos',
            'ignored_heading' => 'Hallazgos ignorados',
            'ignored_lead' => 'Hallazgos aceptados tal como están. Cada uno lleva el motivo con el que se aceptó.',
            'none_open' => 'Ningún hallazgo abierto.',
            'none_resolved' => 'Ningún hallazgo resuelto todavía.',
            'none_ignored' => 'Ningún hallazgo ignorado.',
            'col_kind' => 'Tipo',
            'col_finding' => 'Hallazgo',
            'col_first_seen' => 'Detectado el',
            'col_decision' => 'Decisión',
            'col_resolved_on' => 'Resuelto el',
            'col_resolved_by' => 'Resuelto con',
            'col_ignored_on' => 'Ignorado el',
            'col_reason' => 'Motivo',
            'decision_none' => 'Aún sin tomar',
            'decision_spec' => 'Corrección planificada: :spec',
            'decision_snoozed' => 'Aplazado hasta el :date',
            'decision_snoozed_open' => 'Aplazado',
            'resolved_spec' => 'Corrección entregada: :spec',
            'resolved_scanner' => 'Ya no lo detecta el análisis',
            'reason_user' => 'Ignorado por una persona en Aikido, donde se conserva el motivo.',
            'reason_rule' => 'Ignorado por una regla del espacio de trabajo en Aikido.',
            'reason_auto' => 'Ignorado por Aikido, que lo consideró no aplicable a este proyecto.',
            'reason_unknown' => 'Ignorado en Aikido, donde se conserva el motivo.',
            'truncated' => 'El repositorio tiene más hallazgos de los que recoge este registro. Los demás están en Aikido.',
            'footer' => 'Los hallazgos y sus estados son los que Aikido informa para este repositorio. Las decisiones y sus motivos son los registrados en el proyecto.',
            'file' => 'registro-vulnerabilidades',
            'severity_critical' => 'Crítica',
            'severity_high' => 'Alta',
            'severity_medium' => 'Media',
            'severity_low' => 'Baja',
            'type_open_source' => 'Dependencia vulnerable',
            'type_leaked_secret' => 'Secreto expuesto',
            'type_sast' => 'Debilidad en el código',
            'type_iac' => 'Infraestructura como código',
            'type_cloud' => 'Configuración de la nube',
            'type_cloud_instance' => 'Instancia en la nube',
            'type_docker_container' => 'Imagen de contenedor',
            'type_surface_monitoring' => 'Superficie expuesta',
            'type_malware' => 'Malware en una dependencia',
            'type_eol' => 'Entorno sin soporte',
            'type_mobile' => 'Aplicación móvil',
            'type_scm_security' => 'Ajustes del repositorio',
            'type_ai_pentest' => 'Resultado de prueba de penetración',
            'type_license' => 'Riesgo de licencia',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function fr(): array
    {
        return [
            'title' => 'Registre des vulnérabilités',
            'lead' => 'Tous les constats de sécurité du dépôt, avec leur état et la décision prise : ceux qui sont ouverts, ceux qui sont résolus et ceux qui sont acceptés avec un motif.',
            'repository' => 'Dépôt',
            'branch' => 'Branche',
            'scanner' => 'Outil d\'analyse',
            'scanner_value' => 'Aikido — dépendances, code, secrets et configuration',
            'last_scan' => 'Dernière analyse',
            'generated' => 'Généré le',
            'summary' => 'Synthèse',
            'severity' => 'Gravité',
            'open' => 'Ouverts',
            'resolved' => 'Résolus',
            'ignored' => 'Ignorés',
            'total' => 'Total',
            'open_heading' => 'Constats ouverts',
            'resolved_heading' => 'Constats résolus',
            'ignored_heading' => 'Constats ignorés',
            'ignored_lead' => 'Constats acceptés tels quels. Chacun porte le motif avec lequel il a été accepté.',
            'none_open' => 'Aucun constat ouvert.',
            'none_resolved' => 'Aucun constat résolu pour l\'instant.',
            'none_ignored' => 'Aucun constat ignoré.',
            'col_kind' => 'Type',
            'col_finding' => 'Constat',
            'col_first_seen' => 'Détecté le',
            'col_decision' => 'Décision',
            'col_resolved_on' => 'Résolu le',
            'col_resolved_by' => 'Résolu par',
            'col_ignored_on' => 'Ignoré le',
            'col_reason' => 'Motif',
            'decision_none' => 'Pas encore prise',
            'decision_spec' => 'Correction planifiée : :spec',
            'decision_snoozed' => 'Reporté au :date',
            'decision_snoozed_open' => 'Reporté',
            'resolved_spec' => 'Correction livrée : :spec',
            'resolved_scanner' => 'N\'est plus détecté par l\'analyse',
            'reason_user' => 'Ignoré par une personne dans Aikido, où le motif est conservé.',
            'reason_rule' => 'Ignoré par une règle de l\'espace de travail dans Aikido.',
            'reason_auto' => 'Ignoré par Aikido, qui l\'a jugé sans objet pour ce projet.',
            'reason_unknown' => 'Ignoré dans Aikido, où le motif est conservé.',
            'truncated' => 'Le dépôt compte plus de constats que ce registre n\'en liste. Les autres sont dans Aikido.',
            'footer' => 'Les constats et leurs états sont ceux qu\'Aikido rapporte pour ce dépôt. Les décisions et leurs motifs sont ceux qui sont enregistrés dans le projet.',
            'file' => 'registre-vulnerabilites',
            'severity_critical' => 'Critique',
            'severity_high' => 'Élevée',
            'severity_medium' => 'Moyenne',
            'severity_low' => 'Faible',
            'type_open_source' => 'Dépendance vulnérable',
            'type_leaked_secret' => 'Secret exposé',
            'type_sast' => 'Faiblesse dans le code',
            'type_iac' => 'Infrastructure en tant que code',
            'type_cloud' => 'Configuration cloud',
            'type_cloud_instance' => 'Instance cloud',
            'type_docker_container' => 'Image de conteneur',
            'type_surface_monitoring' => 'Surface exposée',
            'type_malware' => 'Logiciel malveillant dans une dépendance',
            'type_eol' => 'Environnement en fin de vie',
            'type_mobile' => 'Application mobile',
            'type_scm_security' => 'Réglages du dépôt',
            'type_ai_pentest' => 'Résultat de test d\'intrusion',
            'type_license' => 'Risque de licence',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function de(): array
    {
        return [
            'title' => 'Verzeichnis der Sicherheitsbefunde',
            'lead' => 'Alle Sicherheitsbefunde des Repositorys, mit ihrem Stand und der getroffenen Entscheidung: die offenen, die behobenen und die mit einer Begründung akzeptierten.',
            'repository' => 'Repository',
            'branch' => 'Branch',
            'scanner' => 'Analysewerkzeug',
            'scanner_value' => 'Aikido — Abhängigkeiten, Code, Geheimnisse und Konfiguration',
            'last_scan' => 'Letzter Scan',
            'generated' => 'Erstellt am',
            'summary' => 'Übersicht',
            'severity' => 'Schweregrad',
            'open' => 'Offen',
            'resolved' => 'Behoben',
            'ignored' => 'Ignoriert',
            'total' => 'Gesamt',
            'open_heading' => 'Offene Befunde',
            'resolved_heading' => 'Behobene Befunde',
            'ignored_heading' => 'Ignorierte Befunde',
            'ignored_lead' => 'Befunde, die so akzeptiert wurden, wie sie sind. Jeder trägt die Begründung, mit der er akzeptiert wurde.',
            'none_open' => 'Kein Befund ist offen.',
            'none_resolved' => 'Bisher wurde kein Befund behoben.',
            'none_ignored' => 'Kein Befund wurde ignoriert.',
            'col_kind' => 'Art',
            'col_finding' => 'Befund',
            'col_first_seen' => 'Entdeckt am',
            'col_decision' => 'Entscheidung',
            'col_resolved_on' => 'Behoben am',
            'col_resolved_by' => 'Behoben durch',
            'col_ignored_on' => 'Ignoriert am',
            'col_reason' => 'Begründung',
            'decision_none' => 'Noch keine',
            'decision_spec' => 'Behebung geplant: :spec',
            'decision_snoozed' => 'Zurückgestellt bis :date',
            'decision_snoozed_open' => 'Zurückgestellt',
            'resolved_spec' => 'Behebung ausgeliefert: :spec',
            'resolved_scanner' => 'Vom Scan nicht mehr gefunden',
            'reason_user' => 'Von einer Person in Aikido ignoriert; die Begründung liegt dort.',
            'reason_rule' => 'Von einer Regel des Arbeitsbereichs in Aikido ignoriert.',
            'reason_auto' => 'Von Aikido ignoriert, das ihn für dieses Projekt als nicht zutreffend einstufte.',
            'reason_unknown' => 'In Aikido ignoriert; die Begründung liegt dort.',
            'truncated' => 'Das Repository hat mehr Befunde, als dieses Verzeichnis aufführt. Die übrigen stehen in Aikido.',
            'footer' => 'Die Befunde und ihr Stand sind die, die Aikido für dieses Repository meldet. Die Entscheidungen und ihre Begründungen sind die, die im Projekt festgehalten sind.',
            'file' => 'sicherheitsbefunde',
            'severity_critical' => 'Kritisch',
            'severity_high' => 'Hoch',
            'severity_medium' => 'Mittel',
            'severity_low' => 'Niedrig',
            'type_open_source' => 'Verwundbare Abhängigkeit',
            'type_leaked_secret' => 'Offengelegtes Geheimnis',
            'type_sast' => 'Schwachstelle im Code',
            'type_iac' => 'Infrastruktur als Code',
            'type_cloud' => 'Cloud-Konfiguration',
            'type_cloud_instance' => 'Cloud-Instanz',
            'type_docker_container' => 'Container-Image',
            'type_surface_monitoring' => 'Exponierte Angriffsfläche',
            'type_malware' => 'Schadsoftware in einer Abhängigkeit',
            'type_eol' => 'Laufzeitumgebung ohne Support',
            'type_mobile' => 'Mobile Anwendung',
            'type_scm_security' => 'Einstellungen des Repositorys',
            'type_ai_pentest' => 'Ergebnis eines Penetrationstests',
            'type_license' => 'Lizenzrisiko',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function pt(): array
    {
        return [
            'title' => 'Registo de vulnerabilidades',
            'lead' => 'Todas as ocorrências de segurança do repositório, com o seu estado e a decisão tomada: as abertas, as resolvidas e as aceites com um motivo.',
            'repository' => 'Repositório',
            'branch' => 'Ramo',
            'scanner' => 'Ferramenta de análise',
            'scanner_value' => 'Aikido — dependências, código, segredos e configuração',
            'last_scan' => 'Última análise',
            'generated' => 'Gerado em',
            'summary' => 'Resumo',
            'severity' => 'Gravidade',
            'open' => 'Abertas',
            'resolved' => 'Resolvidas',
            'ignored' => 'Ignoradas',
            'total' => 'Total',
            'open_heading' => 'Ocorrências abertas',
            'resolved_heading' => 'Ocorrências resolvidas',
            'ignored_heading' => 'Ocorrências ignoradas',
            'ignored_lead' => 'Ocorrências aceites tal como estão. Cada uma traz o motivo com que foi aceite.',
            'none_open' => 'Nenhuma ocorrência aberta.',
            'none_resolved' => 'Nenhuma ocorrência resolvida até agora.',
            'none_ignored' => 'Nenhuma ocorrência ignorada.',
            'col_kind' => 'Tipo',
            'col_finding' => 'Ocorrência',
            'col_first_seen' => 'Detetada em',
            'col_decision' => 'Decisão',
            'col_resolved_on' => 'Resolvida em',
            'col_resolved_by' => 'Resolvida com',
            'col_ignored_on' => 'Ignorada em',
            'col_reason' => 'Motivo',
            'decision_none' => 'Ainda por tomar',
            'decision_spec' => 'Correção planeada: :spec',
            'decision_snoozed' => 'Adiada até :date',
            'decision_snoozed_open' => 'Adiada',
            'resolved_spec' => 'Correção entregue: :spec',
            'resolved_scanner' => 'Já não é detetada pela análise',
            'reason_user' => 'Ignorada por uma pessoa no Aikido, onde o motivo está guardado.',
            'reason_rule' => 'Ignorada por uma regra do espaço de trabalho no Aikido.',
            'reason_auto' => 'Ignorada pelo Aikido, que a considerou não aplicável a este projeto.',
            'reason_unknown' => 'Ignorada no Aikido, onde o motivo está guardado.',
            'truncated' => 'O repositório tem mais ocorrências do que as listadas neste registo. As restantes estão no Aikido.',
            'footer' => 'As ocorrências e os seus estados são os que o Aikido reporta para este repositório. As decisões e os seus motivos são os registados no projeto.',
            'file' => 'registo-vulnerabilidades',
            'severity_critical' => 'Crítica',
            'severity_high' => 'Alta',
            'severity_medium' => 'Média',
            'severity_low' => 'Baixa',
            'type_open_source' => 'Dependência vulnerável',
            'type_leaked_secret' => 'Segredo exposto',
            'type_sast' => 'Fraqueza no código',
            'type_iac' => 'Infraestrutura como código',
            'type_cloud' => 'Configuração da nuvem',
            'type_cloud_instance' => 'Instância na nuvem',
            'type_docker_container' => 'Imagem de contentor',
            'type_surface_monitoring' => 'Superfície exposta',
            'type_malware' => 'Malware numa dependência',
            'type_eol' => 'Ambiente em fim de vida',
            'type_mobile' => 'Aplicação móvel',
            'type_scm_security' => 'Definições do repositório',
            'type_ai_pentest' => 'Resultado de teste de intrusão',
            'type_license' => 'Risco de licença',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function nl(): array
    {
        return [
            'title' => 'Register van beveiligingsbevindingen',
            'lead' => 'Alle beveiligingsbevindingen van de repository, met hun status en het genomen besluit: wat openstaat, wat is opgelost en wat met een reden is geaccepteerd.',
            'repository' => 'Repository',
            'branch' => 'Branch',
            'scanner' => 'Analysetool',
            'scanner_value' => 'Aikido — afhankelijkheden, code, geheimen en configuratie',
            'last_scan' => 'Laatste scan',
            'generated' => 'Gemaakt op',
            'summary' => 'Overzicht',
            'severity' => 'Ernst',
            'open' => 'Open',
            'resolved' => 'Opgelost',
            'ignored' => 'Genegeerd',
            'total' => 'Totaal',
            'open_heading' => 'Open bevindingen',
            'resolved_heading' => 'Opgeloste bevindingen',
            'ignored_heading' => 'Genegeerde bevindingen',
            'ignored_lead' => 'Bevindingen die zijn geaccepteerd zoals ze zijn. Bij elke staat de reden waarmee ze is geaccepteerd.',
            'none_open' => 'Er staat geen bevinding open.',
            'none_resolved' => 'Er is nog geen bevinding opgelost.',
            'none_ignored' => 'Er is geen bevinding genegeerd.',
            'col_kind' => 'Soort',
            'col_finding' => 'Bevinding',
            'col_first_seen' => 'Gevonden op',
            'col_decision' => 'Besluit',
            'col_resolved_on' => 'Opgelost op',
            'col_resolved_by' => 'Opgelost door',
            'col_ignored_on' => 'Genegeerd op',
            'col_reason' => 'Reden',
            'decision_none' => 'Nog geen',
            'decision_spec' => 'Oplossing gepland: :spec',
            'decision_snoozed' => 'Uitgesteld tot :date',
            'decision_snoozed_open' => 'Uitgesteld',
            'resolved_spec' => 'Oplossing geleverd: :spec',
            'resolved_scanner' => 'Niet meer gevonden door de scan',
            'reason_user' => 'Genegeerd door een persoon in Aikido, waar de reden staat.',
            'reason_rule' => 'Genegeerd door een regel van de werkruimte in Aikido.',
            'reason_auto' => 'Genegeerd door Aikido, dat de bevinding niet van toepassing achtte op dit project.',
            'reason_unknown' => 'Genegeerd in Aikido, waar de reden staat.',
            'truncated' => 'De repository heeft meer bevindingen dan dit register toont. De rest staat in Aikido.',
            'footer' => 'De bevindingen en hun status zijn die welke Aikido voor deze repository meldt. De besluiten en hun redenen zijn die welke in het project zijn vastgelegd.',
            'file' => 'beveiligingsregister',
            'severity_critical' => 'Kritiek',
            'severity_high' => 'Hoog',
            'severity_medium' => 'Gemiddeld',
            'severity_low' => 'Laag',
            'type_open_source' => 'Kwetsbare afhankelijkheid',
            'type_leaked_secret' => 'Gelekt geheim',
            'type_sast' => 'Zwakke plek in de code',
            'type_iac' => 'Infrastructuur als code',
            'type_cloud' => 'Cloudconfiguratie',
            'type_cloud_instance' => 'Cloudinstantie',
            'type_docker_container' => 'Containerimage',
            'type_surface_monitoring' => 'Blootgesteld oppervlak',
            'type_malware' => 'Malware in een afhankelijkheid',
            'type_eol' => 'Runtime zonder ondersteuning',
            'type_mobile' => 'Mobiele applicatie',
            'type_scm_security' => 'Instellingen van de repository',
            'type_ai_pentest' => 'Bevinding uit een penetratietest',
            'type_license' => 'Licentierisico',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function pl(): array
    {
        return [
            'title' => 'Rejestr podatności',
            'lead' => 'Wszystkie zgłoszenia bezpieczeństwa repozytorium, ze stanem i podjętą decyzją: otwarte, rozwiązane i zaakceptowane z uzasadnieniem.',
            'repository' => 'Repozytorium',
            'branch' => 'Gałąź',
            'scanner' => 'Narzędzie analizy',
            'scanner_value' => 'Aikido — zależności, kod, sekrety i konfiguracja',
            'last_scan' => 'Ostatni skan',
            'generated' => 'Wygenerowano',
            'summary' => 'Podsumowanie',
            'severity' => 'Waga',
            'open' => 'Otwarte',
            'resolved' => 'Rozwiązane',
            'ignored' => 'Zignorowane',
            'total' => 'Razem',
            'open_heading' => 'Zgłoszenia otwarte',
            'resolved_heading' => 'Zgłoszenia rozwiązane',
            'ignored_heading' => 'Zgłoszenia zignorowane',
            'ignored_lead' => 'Zgłoszenia zaakceptowane w obecnym stanie. Przy każdym podano uzasadnienie, z którym je zaakceptowano.',
            'none_open' => 'Żadne zgłoszenie nie jest otwarte.',
            'none_resolved' => 'Żadne zgłoszenie nie zostało jeszcze rozwiązane.',
            'none_ignored' => 'Żadne zgłoszenie nie zostało zignorowane.',
            'col_kind' => 'Rodzaj',
            'col_finding' => 'Zgłoszenie',
            'col_first_seen' => 'Wykryto',
            'col_decision' => 'Decyzja',
            'col_resolved_on' => 'Rozwiązano',
            'col_resolved_by' => 'Rozwiązano przez',
            'col_ignored_on' => 'Zignorowano',
            'col_reason' => 'Uzasadnienie',
            'decision_none' => 'Jeszcze nie podjęto',
            'decision_spec' => 'Planowana poprawka: :spec',
            'decision_snoozed' => 'Odłożone do :date',
            'decision_snoozed_open' => 'Odłożone',
            'resolved_spec' => 'Dostarczona poprawka: :spec',
            'resolved_scanner' => 'Skan już tego nie wykrywa',
            'reason_user' => 'Zignorowane przez osobę w Aikido, gdzie zapisano uzasadnienie.',
            'reason_rule' => 'Zignorowane przez regułę przestrzeni roboczej w Aikido.',
            'reason_auto' => 'Zignorowane przez Aikido, które uznało, że nie dotyczy tego projektu.',
            'reason_unknown' => 'Zignorowane w Aikido, gdzie zapisano uzasadnienie.',
            'truncated' => 'Repozytorium ma więcej zgłoszeń, niż wymienia ten rejestr. Pozostałe są w Aikido.',
            'footer' => 'Zgłoszenia i ich stany pochodzą z tego, co Aikido raportuje dla tego repozytorium. Decyzje i ich uzasadnienia to te zapisane w projekcie.',
            'file' => 'rejestr-podatnosci',
            'severity_critical' => 'Krytyczna',
            'severity_high' => 'Wysoka',
            'severity_medium' => 'Średnia',
            'severity_low' => 'Niska',
            'type_open_source' => 'Podatna zależność',
            'type_leaked_secret' => 'Ujawniony sekret',
            'type_sast' => 'Słabość w kodzie',
            'type_iac' => 'Infrastruktura jako kod',
            'type_cloud' => 'Konfiguracja chmury',
            'type_cloud_instance' => 'Instancja w chmurze',
            'type_docker_container' => 'Obraz kontenera',
            'type_surface_monitoring' => 'Odsłonięta powierzchnia',
            'type_malware' => 'Złośliwe oprogramowanie w zależności',
            'type_eol' => 'Środowisko bez wsparcia',
            'type_mobile' => 'Aplikacja mobilna',
            'type_scm_security' => 'Ustawienia repozytorium',
            'type_ai_pentest' => 'Wynik testu penetracyjnego',
            'type_license' => 'Ryzyko licencyjne',
        ];
    }
}
