<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto;

/**
 * Ein Bild aus ChurchTools (Termin-Flyer oder Personenbild).
 *
 * ChurchTools liefert zum Bild einen Fokuspunkt mit. Der wird auf Contaos
 * Bild-Fokuspunkt übertragen, damit Hochformat-Flyer in Listenansichten nicht
 * mittig zerschnitten werden.
 */
final class ImageReference
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $filename,
        public readonly string $imageUrl,
        public readonly float $focusX = 0.5,
        public readonly float $focusY = 0.5,
        public readonly int|null $width = null,
        public readonly int|null $height = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(array|null $data): self|null
    {
        if (null === $data || !isset($data['id'], $data['imageUrl'])) {
            return null;
        }

        $focus = $data['imageOption']['focus'] ?? [];

        return new self(
            id: (int) $data['id'],
            name: (string) ($data['name'] ?? ''),
            filename: (string) ($data['filename'] ?? ''),
            imageUrl: (string) $data['imageUrl'],
            focusX: (float) ($focus['x'] ?? 0.5),
            focusY: (float) ($focus['y'] ?? 0.5),
            width: isset($data['imageMetadata']['width']) ? (int) $data['imageMetadata']['width'] : null,
            height: isset($data['imageMetadata']['height']) ? (int) $data['imageMetadata']['height'] : null,
        );
    }

    /**
     * Dateiendung aus dem Originalnamen, da der Speichername ein Hash ohne Endung ist.
     */
    public function extension(): string
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));

        return \in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true) ? $extension : 'jpg';
    }

    /**
     * Stabiler Dateiname: ändert sich nur, wenn in ChurchTools ein anderes Bild
     * hinterlegt wurde. Damit wird nicht bei jedem Lauf neu heruntergeladen.
     */
    public function targetFilename(): string
    {
        return $this->id.'-'.substr($this->filename, 0, 16).'.'.$this->extension();
    }
}
