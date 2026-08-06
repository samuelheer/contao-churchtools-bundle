<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Twig\FragmentTemplate;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto\Person;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Person\GroupRoleResolver;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ansprechpartner aus ChurchTools.
 *
 * Entweder eine feste Person oder - und das ist der eigentliche Gewinn - eine
 * Rolle: "die Leitung der Gruppe X". Wechselt die Rolle in ChurchTools, wechselt
 * die Box, ohne dass jemand die Website anfasst.
 */
#[AsContentElement('churchtools_person', category: 'churchtools')]
class PersonController extends AbstractContentElementController
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly PersonProvider $personProvider,
        private readonly GroupRoleResolver $groupRoleResolver,
        private readonly PersonRenderer $renderer,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $connection = $this->resolveConnection($model);

        if (null === $connection) {
            $template->set('people', []);
            $template->set('variant', 'inline');

            return $template->getResponse();
        }

        [$people, $tags] = $this->resolvePeople($connection, $model);

        // Ohne Tagging fröre die Person in einer HTTP-gecachten Seite ein.
        if ([] !== $tags) {
            $this->tagResponse($tags);
        }

        $prepared = [];

        foreach ($people as $person) {
            $data = $person->toPublicArray($connection);
            $data['imageUrl'] = $data['hasImage'] ? $this->renderer->avatarUrl($connection, $person->id) : null;
            $prepared[] = $data;
        }

        $template->set('people', $prepared);
        $template->set('variant', $model->churchtoolsVariant ?: 'card');
        $template->set('headline', $model->headline);

        return $template->getResponse();
    }

    private function resolveConnection(ContentModel $model): Connection|null
    {
        // Am Element gesetzt schlägt die Startseite - sonst gilt der Kontext der Website.
        return $this->registry->find((int) $model->churchtoolsConnection)
            ?? $this->registry->forCurrentRootPage();
    }

    /**
     * @return array{0: list<Person>, 1: list<string>}
     */
    private function resolvePeople(Connection $connection, ContentModel $model): array
    {
        if ('group' === $model->churchtoolsSource) {
            $groupId = (int) $model->churchtoolsGroupId;

            if ($groupId < 1) {
                return [[], []];
            }

            $roleId = (int) $model->churchtoolsRoleId ?: null;
            $people = $this->groupRoleResolver->resolve($connection, $groupId, $roleId);

            $tags = [$this->groupRoleResolver->cacheTag($connection, $groupId)];

            foreach ($people as $person) {
                $tags[] = $this->personProvider->cacheTag($connection, $person->id);
            }

            return [$people, $tags];
        }

        $personId = (int) $model->churchtoolsPersonId;

        if ($personId < 1) {
            return [[], []];
        }

        $person = $this->personProvider->get($connection, $personId);

        return [
            null !== $person ? [$person] : [],
            [$this->personProvider->cacheTag($connection, $personId)],
        ];
    }
}
