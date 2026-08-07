<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\ChurchTools;

use Psr\Log\LoggerInterface;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsUnavailableException;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Zugriff auf die ChurchTools-REST-API.
 *
 * Jeder Aufruf bekommt die Verbindung explizit übergeben - so ist an jeder
 * Aufrufstelle sichtbar, für welchen Mandanten Daten geholt werden.
 */
class ChurchToolsClient
{
    private const PAGE_SIZE = 100;

    /** @var array<int, HttpClientInterface> */
    private array $clients = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Prüft die Verbindung. Liefert den Namen des angemeldeten Dienstkontos.
     */
    public function whoami(Connection $connection): string|null
    {
        $data = $this->get($connection, '/api/whoami');

        if (!isset($data['data'])) {
            return null;
        }

        $person = $data['data'];

        return trim(($person['firstName'] ?? '').' '.($person['lastName'] ?? '')) ?: null;
    }

    /**
     * Alle Kalender der Instanz.
     *
     * @return list<array{id: int, name: string}>
     */
    public function getCalendars(Connection $connection): array
    {
        $data = $this->get($connection, '/api/calendars');

        $calendars = [];

        foreach ($data['data'] ?? [] as $calendar) {
            $calendars[] = [
                'id' => (int) $calendar['id'],
                'name' => (string) ($calendar['name'] ?? ''),
            ];
        }

        return $calendars;
    }

    /**
     * Termine eines Kalenders im Zeitraum.
     *
     * Bewusst ein Aufruf je Kalender: Der Parameter calendar_ids[] wird von diesem
     * Endpunkt nachweislich ignoriert (geprüft gegen ChurchTools 3.135.1 - die Antwort
     * ist mit und ohne den Parameter identisch). Nebeneffekt: Die Kalenderzugehörigkeit
     * ist eindeutig, ohne sie aus der Antwort ableiten zu müssen.
     *
     * @return list<array<string, mixed>>
     */
    public function getAppointmentsForCalendar(Connection $connection, int $calendarId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $data = $this->get($connection, '/api/calendars/'.$calendarId.'/appointments', [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        return array_values($data['data'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPerson(Connection $connection, int $personId): array|null
    {
        $data = $this->get($connection, '/api/persons/'.$personId);

        return $data['data'] ?? null;
    }

    /**
     * Personensuche über die zentrale ChurchTools-Suche - dieselbe, die auch die
     * Oberfläche dort benutzt. Liefert Name, ID und Bild in einem Rutsch.
     *
     * @return list<array{id: int, name: string, firstName: string, lastName: string}>
     */
    public function searchPersons(Connection $connection, string $query, int $limit = 15): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $data = $this->get($connection, '/api/search', [
            'query' => $query,
            'domainTypes[]' => 'person',
        ]);

        $results = [];

        foreach ($data['data'] ?? [] as $hit) {
            if ('person' !== ($hit['domainType'] ?? null)) {
                continue;
            }

            // Archivierte und verstorbene Personen gehören nicht auf eine Website.
            $attributes = $hit['domainAttributes'] ?? [];

            if (($attributes['isArchived'] ?? false) || null !== ($attributes['dateOfDeath'] ?? null)) {
                continue;
            }

            $results[] = [
                'id' => (int) ($hit['domainIdentifier'] ?? 0),
                'name' => (string) ($hit['title'] ?? ''),
                'firstName' => (string) ($attributes['firstName'] ?? ''),
                'lastName' => (string) ($attributes['lastName'] ?? ''),
            ];

            if (\count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGroup(Connection $connection, int $groupId): array|null
    {
        $data = $this->get($connection, '/api/groups/'.$groupId);

        return $data['data'] ?? null;
    }

    /**
     * Mitglieder einer Gruppe, seitenweise vollständig geholt.
     *
     * @return list<array<string, mixed>>
     */
    public function getGroupMembers(Connection $connection, int $groupId): array
    {
        return $this->getPaginated($connection, '/api/groups/'.$groupId.'/members');
    }

    /**
     * Gruppen der Instanz, seitenweise vollständig geholt.
     *
     * @return list<array<string, mixed>>
     */
    public function getGroups(Connection $connection): array
    {
        return $this->getPaginated($connection, '/api/groups');
    }

    /**
     * Rollen eines Gruppentyps - für die Auswahl "welche Rolle gilt als Leitung".
     *
     * @return list<array<string, mixed>>
     */
    public function getGroupTypeRoles(Connection $connection): array
    {
        $data = $this->get($connection, '/api/masterdata/groups/roles');

        return array_values($data['data'] ?? []);
    }

    /**
     * Lädt eine Datei (Flyer, Personenbild) herunter.
     *
     * Zuerst ohne Anmeldung: Die Bild-URLs von ChurchTools sind öffentlich, und
     * der Login-Header führt dort nachweislich zu einer Weiterleitung statt zum
     * Bild (geprüft gegen 3.135.1 - mit Header HTTP 302, ohne Header HTTP 200).
     * Erst wenn das abgewiesen wird, greift der angemeldete Versuch.
     */
    public function download(Connection $connection, string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'max_redirects' => 5,
            ]);

            if ($response->getStatusCode() < 400) {
                return $response->getContent();
            }
        } catch (HttpExceptionInterface) {
            // Weiter zum angemeldeten Versuch.
        }

        try {
            $response = $this->client($connection)->request('GET', $url, ['max_redirects' => 5]);
            $status = $response->getStatusCode();

            if ($status >= 400) {
                throw new ChurchToolsUnavailableException(\sprintf('Download fehlgeschlagen (HTTP %d).', $status));
            }

            return $response->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new ChurchToolsUnavailableException('Download aus ChurchTools fehlgeschlagen.', 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function get(Connection $connection, string $path, array $query = []): array
    {
        try {
            $response = $this->client($connection)->request('GET', $path, ['query' => $query]);
            $status = $response->getStatusCode();

            if (404 === $status) {
                return [];
            }

            if ($status >= 400) {
                throw new ChurchToolsUnavailableException(\sprintf('ChurchTools antwortete auf %s mit HTTP %d.', $path, $status));
            }

            return $response->toArray();
        } catch (HttpExceptionInterface $e) {
            // Bewusst ohne Antwortinhalt - der könnte Personendaten enthalten.
            $this->logger?->warning('ChurchTools-Anfrage fehlgeschlagen', [
                'connection' => $connection->title,
                'path' => $path,
            ]);

            throw new ChurchToolsUnavailableException(\sprintf('ChurchTools ist nicht erreichbar (%s).', $path), 0, $e);
        }
    }

    /**
     * Holt alle Seiten einer paginierten Liste. ChurchTools deckelt die Seitengröße;
     * ein zu großes Limit kann leer zurückkommen, deshalb 100er-Seiten.
     *
     * @param array<string, mixed> $query
     *
     * @return list<array<string, mixed>>
     */
    private function getPaginated(Connection $connection, string $path, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $data = $this->get($connection, $path, [...$query, 'page' => $page, 'limit' => self::PAGE_SIZE]);
            $chunk = $data['data'] ?? [];

            foreach ($chunk as $item) {
                $items[] = $item;
            }

            $lastPage = (int) ($data['meta']['pagination']['lastPage'] ?? $page);
            ++$page;
        } while ($page <= $lastPage && [] !== $chunk && $page < 100);

        return $items;
    }

    private function client(Connection $connection): HttpClientInterface
    {
        if (isset($this->clients[$connection->id])) {
            return $this->clients[$connection->id];
        }

        if (!$connection->hasToken()) {
            throw new ChurchToolsUnavailableException(\sprintf('Für die Verbindung "%s" ist kein Token gesetzt. Erwartet wird die Umgebungsvariable %s.', $connection->title, $connection->tokenEnvVar));
        }

        $scoped = $this->httpClient->withOptions([
            'base_uri' => $connection->baseUrl.'/',
            'headers' => [
                'Authorization' => 'Login '.$connection->token,
                'Accept' => 'application/json',
            ],
            'timeout' => 10,
            'max_duration' => 30,
        ]);

        return $this->clients[$connection->id] = new RetryableHttpClient($scoped, maxRetries: 2);
    }
}
