<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Person;

use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto\Person;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

/**
 * Liefert Personen für die Website - aber nur freigegebene.
 *
 * Personendaten werden bewusst nicht in die Contao-Datenbank gespiegelt: Sie
 * hätten auf dem Webserver nichts dauerhaft verloren, und ein Spiegel müsste bei
 * jedem Austritt nachgezogen werden. Stattdessen kurzlebiger Cache mit einem
 * Rückfall auf den letzten guten Stand, damit ein ChurchTools-Ausfall die Seite
 * nicht zerlegt.
 */
class PersonProvider
{
    private const TTL = 3600;

    public function __construct(
        private readonly ChurchToolsClient $client,
        private readonly TagAwareAdapterInterface $cache,
    ) {
    }

    /**
     * Eine einzelne Person, sofern sie freigegeben ist.
     */
    public function get(Connection $connection, int $personId): Person|null
    {
        if (!$this->isReleased($connection, $personId)) {
            return null;
        }

        return $this->fetch($connection, $personId);
    }

    /**
     * @param list<int> $personIds
     *
     * @return list<Person>
     */
    public function getMany(Connection $connection, array $personIds): array
    {
        $people = [];

        foreach ($personIds as $personId) {
            $person = $this->get($connection, $personId);

            if (null !== $person) {
                $people[] = $person;
            }
        }

        return $people;
    }

    /**
     * Ausgeliefert wird ausschließlich, wer in der hinterlegten Freigabegruppe
     * steht. Ist keine Gruppe konfiguriert, wird niemand ausgeliefert - eine
     * fehlende Einstellung darf nicht zur stillschweigenden Vollfreigabe werden.
     */
    public function isReleased(Connection $connection, int $personId): bool
    {
        if (!$connection->hasPersonRelease()) {
            return false;
        }

        return \in_array($personId, $this->releasedIds($connection), true);
    }

    /**
     * @return list<int>
     */
    public function releasedIds(Connection $connection): array
    {
        $groupId = $connection->publicPersonGroupId;

        if (null === $groupId) {
            return [];
        }

        return $this->remember(
            $connection->cachePrefix().'.release',
            [$connection->cachePrefix().'.release'],
            function () use ($connection, $groupId): array {
                $ids = [];

                foreach ($this->client->getGroupMembers($connection, $groupId) as $member) {
                    if (isset($member['personId'])) {
                        $ids[] = (int) $member['personId'];
                    }
                }

                return $ids;
            },
        ) ?? [];
    }

    /**
     * Cache-Tag einer Person - damit gerenderte Seiten gezielt invalidiert werden können.
     */
    public function cacheTag(Connection $connection, int $personId): string
    {
        return $connection->cachePrefix().'.person.'.$personId;
    }

    private function fetch(Connection $connection, int $personId): Person|null
    {
        return $this->remember(
            $this->cacheTag($connection, $personId),
            [$this->cacheTag($connection, $personId)],
            function () use ($connection, $personId): Person|null {
                $data = $this->client->getPerson($connection, $personId);

                return null !== $data ? Person::fromArray($data) : null;
            },
        );
    }

    /**
     * Holt aus dem Cache oder frisch. Schlägt der Abruf fehl, greift der
     * unbefristet abgelegte letzte gute Stand.
     *
     * @param list<string> $tags
     */
    private function remember(string $key, array $tags, callable $fetch): mixed
    {
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $staleKey = $key.'.stale';

        try {
            $value = $fetch();
        } catch (ChurchToolsException) {
            $stale = $this->cache->getItem($staleKey);

            return $stale->isHit() ? $stale->get() : null;
        }

        $item->set($value);
        $item->expiresAfter(self::TTL);
        $item->tag($tags);
        $this->cache->save($item);

        $stale = $this->cache->getItem($staleKey);
        $stale->set($value);
        $stale->tag($tags);
        $this->cache->save($stale);

        return $value;
    }
}
