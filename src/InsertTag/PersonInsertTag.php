<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\InsertTag;

use Contao\CoreBundle\DependencyInjection\Attribute\AsInsertTag;
use Contao\CoreBundle\InsertTag\InsertTagResult;
use Contao\CoreBundle\InsertTag\OutputType;
use Contao\CoreBundle\InsertTag\ResolvedInsertTag;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Person\GroupRoleResolver;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonRenderer;

/**
 * Personen im Fließtext.
 *
 *   {{ct_person::42}}                 Person 42 in der Standarddarstellung
 *   {{ct_person::42::card}}           Person 42 als Karte
 *   {{ct_person_group::17}}           alle freigegebenen Mitglieder der Gruppe 17
 *   {{ct_person_group::17::3}}        nur Rolle 3 (z. B. Leitung)
 *   {{ct_person_group::17::3::card}}  Rolle 3 als Karte
 *
 * Die Verbindung ergibt sich aus der Startseite der aktuellen Website.
 */
class PersonInsertTag
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly PersonProvider $personProvider,
        private readonly GroupRoleResolver $groupRoleResolver,
        private readonly PersonRenderer $renderer,
    ) {
    }

    #[AsInsertTag('ct_person')]
    public function replacePerson(ResolvedInsertTag $insertTag): InsertTagResult
    {
        $connection = $this->registry->forCurrentRootPage();
        $personId = (int) (string) $insertTag->getParameters()->get(0);

        if (null === $connection || $personId < 1) {
            return $this->empty();
        }

        $person = $this->personProvider->get($connection, $personId);

        if (null === $person) {
            return $this->empty();
        }

        $variant = (string) ($insertTag->getParameters()->get(1) ?? 'inline');

        return new InsertTagResult(
            $this->renderer->render($connection, [$person], $variant),
            OutputType::html,
            null,
            [$this->personProvider->cacheTag($connection, $personId)],
        );
    }

    #[AsInsertTag('ct_person_group')]
    public function replaceGroup(ResolvedInsertTag $insertTag): InsertTagResult
    {
        $connection = $this->registry->forCurrentRootPage();
        $groupId = (int) (string) $insertTag->getParameters()->get(0);

        if (null === $connection || $groupId < 1) {
            return $this->empty();
        }

        $roleId = (int) (string) ($insertTag->getParameters()->get(1) ?? 0) ?: null;
        $variant = (string) ($insertTag->getParameters()->get(2) ?? 'list');

        $people = $this->groupRoleResolver->resolve($connection, $groupId, $roleId);

        if ([] === $people) {
            return $this->empty();
        }

        $tags = [$this->groupRoleResolver->cacheTag($connection, $groupId)];

        foreach ($people as $person) {
            $tags[] = $this->personProvider->cacheTag($connection, $person->id);
        }

        return new InsertTagResult(
            $this->renderer->render($connection, $people, $variant),
            OutputType::html,
            null,
            $tags,
        );
    }

    /**
     * Keine Freigabe, keine Person, keine Verbindung: nichts ausgeben.
     * Eine Fehlermeldung im Fließtext hilft dem Besucher nicht.
     */
    private function empty(): InsertTagResult
    {
        return new InsertTagResult('', OutputType::html);
    }
}
