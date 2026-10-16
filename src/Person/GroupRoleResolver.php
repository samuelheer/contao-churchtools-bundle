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

    /**
     * Alle aktiven Mitglieder samt Namen - OHNE Freigabefilter. Nur für die
     * Backend-Vorschau, damit Redakteure sehen, wer warum nicht erscheint.
     * Niemals ins Frontend geben.
     *
     * @return list<array{id: int, name: string}>
     */
    public function membersForPreview(Connection $connection, int $groupId, int|null $roleId = null): array
    {
        $key = $this->cacheTag($connection, $groupId).($roleId ? '.role.'.$roleId : '').'.preview';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        try {
            $members = $this->client->getGroupMembers($connection, $groupId);
        } catch (ChurchToolsException) {
            return [];
        }

        $result = [];

        foreach ($members as $member) {
            if (!isset($member['personId'])) {
                continue;
            }

            if (null !== $roleId && (int) ($member['groupTypeRoleId'] ?? 0) !== $roleId) {
                continue;
            }

            $person = \is_array($member['person'] ?? null) ? $member['person'] : [];
            $attributes = \is_array($person['domainAttributes'] ?? null) ? $person['domainAttributes'] : [];
            $name = trim(($attributes['firstName'] ?? '').' '.($attributes['lastName'] ?? '')) ?: (string) ($person['title'] ?? '');

            $result[] = ['id' => (int) $member['personId'], 'name' => '' !== $name ? $name : '#'.$member['personId']];
        }

        $item->set($result);
        $item->expiresAfter(300);
        $item->tag([$this->cacheTag($connection, $groupId)]);
        $this->cache->save($item);

        return $result;
    }

    /**
     * Klartextnamen von Gruppe und Rolle für die Backend-Vorschau - Redakteure
     * sehen in ChurchTools keine IDs. Fällt bei einem Fehler auf die ID zurück.
     *
     * @return array{group: string, role: string|null}
     */
    public function labelsForPreview(Connection $connection, int $groupId, int|null $roleId = null): array
    {
        $key = $this->cacheTag($connection, $groupId).($roleId ? '.role.'.$roleId : '').'.labels';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $labels = ['group' => '#'.$groupId, 'role' => $roleId ? '#'.$roleId : null];

        try {
            $group = $this->client->getGroup($connection, $groupId);
            $labels['group'] = trim((string) ($group['name'] ?? '')) ?: $labels['group'];

            if ($roleId) {
                foreach ($this->client->getGroupTypeRoles($connection) as $role) {
                    if ($role['id'] === $roleId && '' !== $role['name']) {
                        $labels['role'] = $role['name'];
                    }
                }
            }
        } catch (ChurchToolsException) {
            return $labels;
        }

        $item->set($labels);
        $item->expiresAfter(self::TTL);
        $item->tag([$this->cacheTag($connection, $groupId)]);
        $this->cache->save($item);

        return $labels;
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
