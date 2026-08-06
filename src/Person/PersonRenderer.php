<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Person;

use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto\Person;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Rendert Personen in die vorgesehenen Darstellungen.
 */
class PersonRenderer
{
    /**
     * Feste Liste, damit über den Variantennamen kein beliebiger Template-Pfad
     * angesteuert werden kann.
     */
    private const VARIANTS = ['inline', 'card', 'contact', 'list'];

    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param list<Person> $people
     */
    public function render(Connection $connection, array $people, string $variant = 'inline'): string
    {
        if ([] === $people) {
            return '';
        }

        if (!\in_array($variant, self::VARIANTS, true)) {
            $variant = 'inline';
        }

        $prepared = [];

        foreach ($people as $person) {
            $data = $person->toPublicArray($connection);
            $data['imageUrl'] = $data['hasImage'] ? $this->avatarUrl($connection, $person->id) : null;
            $prepared[] = $data;
        }

        return $this->twig->render('@Contao/churchtools/person_'.$variant.'.html.twig', [
            'people' => $prepared,
            'person' => $prepared[0],
        ]);
    }

    public function avatarUrl(Connection $connection, int $personId, string $size = 'medium'): string
    {
        return $this->urlGenerator->generate('contao_churchtools_avatar', [
            'connection' => $connection->id,
            'personId' => $personId,
            'size' => $size,
        ]);
    }
}
