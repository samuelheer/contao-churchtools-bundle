<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;

/**
 * Backend-Verhalten der Verbindungsverwaltung.
 */
class ConnectionDcaListener
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly ChurchToolsClient $client,
    ) {
    }

    /**
     * Gruppen der Instanz mit Namen, damit niemand IDs nachschlagen muss.
     *
     * Ist ChurchTools nicht erreichbar oder das Token noch nicht gesetzt, bleibt
     * die gespeicherte ID als Option erhalten. Sonst würde ein Speichern in genau
     * diesem Moment die Freigabegruppe leeren - und dann erscheint niemand mehr.
     *
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_churchtools_connection', target: 'fields.publicPersonGroupId.options')]
    #[AsCallback(table: 'tl_churchtools_connection', target: 'fields.imageReleaseGroupId.options')]
    public function getGroups(DataContainer|null $dc = null): array
    {
        $options = [];
        $connection = $dc?->id ? $this->registry->find((int) $dc->id) : null;

        if (null !== $connection) {
            try {
                foreach ($this->client->getGroups($connection) as $group) {
                    if (isset($group['id'])) {
                        $options[(int) $group['id']] = ($group['name'] ?? '').' ('.$group['id'].')';
                    }
                }
            } catch (ChurchToolsException) {
                // Fällt unten auf die gespeicherten Werte zurück.
            }
        }

        natcasesort($options);

        foreach (['publicPersonGroupId', 'imageReleaseGroupId'] as $field) {
            $current = (int) ($dc?->activeRecord?->{$field} ?? 0);

            if ($current > 0 && !isset($options[$current])) {
                $options[$current] = \sprintf('Gruppe %d (Name nicht abrufbar)', $current);
            }
        }

        return $options;
    }

    /**
     * Zeigt in der Übersicht an, ob das Token tatsächlich in der Umgebung liegt.
     * Ohne diesen Hinweis merkt man eine fehlende Variable erst, wenn nichts
     * synchronisiert wird - und sucht den Fehler an der falschen Stelle.
     */
    #[AsCallback(table: 'tl_churchtools_connection', target: 'list.label.label')]
    public function addTokenState(array $row, string $label): string
    {
        $connection = $this->registry->find((int) $row['id']);

        if (null === $connection) {
            return $label;
        }

        if ($connection->hasToken()) {
            return $label.' <span style="color:#589e4a">&#10003; Token gefunden</span>';
        }

        return $label.' <span style="color:#c33">&#10007; Umgebungsvariable '.htmlspecialchars($connection->tokenEnvVar).' fehlt</span>';
    }

    /**
     * Ein abschließender Schrägstrich in der Basis-URL erzeugt sonst doppelte
     * Schrägstriche in jedem API-Pfad.
     */
    #[AsCallback(table: 'tl_churchtools_connection', target: 'fields.baseUrl.save')]
    public function normalizeBaseUrl(mixed $value): string
    {
        return rtrim((string) $value, '/');
    }

    /**
     * Der Zielordner liegt immer unterhalb von files/ und darf nicht ausbrechen.
     */
    #[AsCallback(table: 'tl_churchtools_connection', target: 'fields.filesPath.save')]
    public function normalizeFilesPath(mixed $value): string
    {
        $path = trim(str_replace('\\', '/', (string) $value), '/');
        $path = preg_replace('#\.\.+/#', '', $path) ?? '';

        return $path;
    }
}
