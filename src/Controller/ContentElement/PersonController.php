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
use Contao\System;
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
 * Eine Rolle - "die Leitung der Gruppe X", wahlweise alle oder nur eine Person -
 * und/oder eine feste Person. Wechselt die Rolle in ChurchTools, wechselt die
 * Box, ohne dass jemand die Website anfasst.
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
        // Die Verbindung kommt ausschließlich von der Startseite dieser Website.
        // Am Element ist sie bewusst nicht wählbar - siehe tl_content.php.
        // Im Backend gibt es keine Seite im Request - dort über das Element auflösen.
        $isBackend = $this->isBackendScope($request);
        $connection = $isBackend
            ? $this->registry->forContentElement((int) $model->id)
            : $this->registry->forCurrentRootPage();

        $variant = $model->churchtoolsVariant ?: 'card';

        if (null === $connection) {
            $template->set('people', []);
            $template->set('variant', 'inline');
            $template->set('preview', $isBackend ? $this->buildPreview(null, $model, $variant, []) : null);

            return $template->getResponse();
        }

        [$people, $tags] = $this->resolvePeople($connection, $model);

        // Ohne Tagging fröre die Person in einer HTTP-gecachten Seite ein.
        if ([] !== $tags) {
            $this->tagResponse($tags);
        }

        $prepared = [];

        foreach ($people as $person) {
            $data = $person->toPublicArray($connection, $this->personProvider->isImageReleased($connection, $person->id));
            $data['imageUrl'] = $data['hasImage'] ? $this->renderer->avatarUrl($connection, $person->id) : null;
            $prepared[] = $data;
        }

        $template->set('people', $prepared);
        $template->set('variant', $variant);
        $template->set('preview', $isBackend ? $this->buildPreview($connection, $model, $variant, $people) : null);

        return $template->getResponse();
    }

    /**
     * Rückmeldung für Redakteure im Backend: welche Darstellung, wer erscheint,
     * wer warum nicht und warum ein Bild fehlt. Wird nur im Backend erzeugt.
     *
     * @param list<Person> $people
     *
     * @return array{variant: string, connection: string|null, notes: list<array{level: string, text: string}>}
     */
    private function buildPreview(Connection|null $connection, ContentModel $model, string $variant, array $people): array
    {
        System::loadLanguageFile('default');
        System::loadLanguageFile('tl_content');

        $l = $GLOBALS['TL_LANG']['MSC']['churchtools_preview'] ?? [];
        $t = static fn (string $key, mixed ...$args): string => vsprintf($l[$key] ?? $key, $args);
        $preview = [
            'variant' => $GLOBALS['TL_LANG']['tl_content']['churchtoolsVariantOptions'][$variant] ?? $variant,
            'connection' => $connection?->title,
            'notes' => [],
        ];

        if (null === $connection) {
            $preview['notes'][] = ['level' => 'error', 'text' => $t('noConnection')];

            return $preview;
        }

        $notes = [];

        if (!$connection->hasPersonRelease()) {
            $notes[] = ['level' => 'error', 'text' => $t('noRelease')];
        }

        $released = $this->personProvider->releasedIds($connection);
        $groupId = (int) $model->churchtoolsGroupId;

        if ($groupId > 0) {
            $roleId = (int) $model->churchtoolsRoleId ?: null;
            $members = $this->groupRoleResolver->membersForPreview($connection, $groupId, $roleId);
            $labels = $this->groupRoleResolver->labelsForPreview($connection, $groupId, $roleId);
            $hidden = array_values(array_filter($members, static fn (array $m): bool => !\in_array($m['id'], $released, true)));

            $notes[] = [
                'level' => [] === $members ? 'warning' : 'info',
                'text' => $roleId
                    ? $t('groupRole', $labels['group'], $labels['role'], \count($members), \count($members) - \count($hidden))
                    : $t('group', $labels['group'], \count($members), \count($members) - \count($hidden)),
            ];

            if ([] !== $hidden) {
                $names = array_map(static fn (array $m): string => $m['name'], \array_slice($hidden, 0, 8));
                $more = \count($hidden) > 8 ? ' '.$t('more', \count($hidden) - 8) : '';
                $notes[] = ['level' => 'warning', 'text' => $t('hidden', implode(', ', $names).$more)];
            }

            if ((int) $model->churchtoolsGroupLimit > 0) {
                $notes[] = ['level' => 'info', 'text' => $t('limitOne')];
            }
        }

        $personId = (int) $model->churchtoolsPersonId;

        if ($personId > 0 && !\in_array($personId, $released, true)) {
            $notes[] = ['level' => 'warning', 'text' => $t('personHidden', $personId)];
        }

        foreach ($people as $person) {
            $reason = match (true) {
                !$connection->allowsPersonField('image') => 'imageFieldOff',
                !$this->personProvider->isImageReleased($connection, $person->id) => 'imageNotInGroup',
                null === $person->imageUrl => 'imageNoPhoto',
                default => null,
            };

            if (null !== $reason) {
                $notes[] = ['level' => 'info', 'text' => $t($reason, $person->fullName(), (int) $connection->imageReleaseGroupId)];
            }
        }

        if ([] === $people) {
            $notes[] = ['level' => 'error', 'text' => $t('nobody')];
        }

        $preview['notes'] = $notes;

        return $preview;
    }

    /**
     * Personen aus Gruppe/Rolle, danach die feste Person - beides optional.
     *
     * @return array{0: list<Person>, 1: list<string>}
     */
    private function resolvePeople(Connection $connection, ContentModel $model): array
    {
        $people = [];
        $tags = [];

        $groupId = (int) $model->churchtoolsGroupId;

        if ($groupId > 0) {
            $roleId = (int) $model->churchtoolsRoleId ?: null;
            $fromGroup = $this->groupRoleResolver->resolve($connection, $groupId, $roleId);

            if ((int) $model->churchtoolsGroupLimit > 0) {
                $fromGroup = \array_slice($fromGroup, 0, (int) $model->churchtoolsGroupLimit);
            }

            $people = $fromGroup;
            $tags[] = $this->groupRoleResolver->cacheTag($connection, $groupId);
        }

        $personId = (int) $model->churchtoolsPersonId;

        if ($personId > 0) {
            $tags[] = $this->personProvider->cacheTag($connection, $personId);

            // Steht die feste Person schon über die Rolle drin, nicht doppelt zeigen.
            $alreadyShown = array_filter($people, static fn (Person $p): bool => $p->id === $personId);

            if ([] === $alreadyShown) {
                $person = $this->personProvider->get($connection, $personId);

                if (null !== $person) {
                    $people[] = $person;
                }
            }
        }

        foreach ($people as $person) {
            $tags[] = $this->personProvider->cacheTag($connection, $person->id);
        }

        return [$people, array_values(array_unique($tags))];
    }
}
