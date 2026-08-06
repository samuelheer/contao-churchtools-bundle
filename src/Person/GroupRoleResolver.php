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
 * Löst "die Leitung der Gruppe X" zu Personen auf.
 *
 * Damit bleibt eine Ansprechpartner-Box richtig, wenn die Rolle in ChurchTools
 * wechselt - ohne dass jemand die Website anfasst.
 */
class GroupRoleResolver
{
    private const TTL = 3600;

    public function __construct(
        private readonly ChurchToolsClient $client,
        private readonly PersonProvider $personProvider,
        private readonly TagAwareAdapterInterface $cache,
    ) {
    }

    /**
     * Personen einer Gruppe, optional auf eine Rolle eingegrenzt.
     *
     * Das Ergebnis ist immer eine Liste: Sind zwei Personen als Leitung
     * eingetragen, erscheinen beide; ist niemand eingetragen, bleibt sie leer.
     *
     * @return list<Person>
     */
    public function resolve(Connection $connection, int $groupId, int|null $roleId = null): array
    {
        $personIds = $this->memberIds($connection, $groupId, $roleId);

        return $this->personProvider->getMany($connection, $personIds);
    }

    public function cacheTag(Connection $connection, int $groupId): string
    {
        return $connection->cachePrefix().'.group.'.$groupId;
    }

    /**
     * @return list<int>
     */
    private function memberIds(Connection $connection, int $groupId, int|null $roleId): array
    {
        $key = $this->cacheTag($connection, $groupId).($roleId ? '.role.'.$roleId : '');
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        try {
            $members = $this->client->getGroupMembers($connection, $groupId);
        } catch (ChurchToolsException) {
            $stale = $this->cache->getItem($key.'.stale');

            return $stale->isHit() ? $stale->get() : [];
        }

        $ids = [];

        foreach ($members as $member) {
            if (!isset($member['personId'])) {
                continue;
            }

            if (null !== $roleId && (int) ($member['groupTypeRoleId'] ?? 0) !== $roleId) {
                continue;
            }

            $ids[] = (int) $member['personId'];
        }

        $item->set($ids);
        $item->expiresAfter(self::TTL);
        $item->tag([$this->cacheTag($connection, $groupId)]);
        $this->cache->save($item);

        $stale = $this->cache->getItem($key.'.stale');
        $stale->set($ids);
        $stale->tag([$this->cacheTag($connection, $groupId)]);
        $this->cache->save($stale);

        return $ids;
    }
}
