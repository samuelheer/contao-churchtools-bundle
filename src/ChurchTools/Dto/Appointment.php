<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto;

/**
 * Eine einzelne Termin-Instanz aus ChurchTools.
 *
 * ChurchTools löst Serien selbst auf und liefert je Instanz einen Eintrag:
 * "base" enthält die Stammdaten der Serie, "calculated" das Datum der Instanz.
 * Die base-ID wiederholt sich deshalb über alle Instanzen einer Serie - erst
 * zusammen mit dem Startdatum ist ein Termin eindeutig identifiziert.
 */
final class Appointment
{
    public function __construct(
        public readonly int $baseId,
        public readonly int $calendarId,
        public readonly \DateTimeImmutable $start,
        public readonly \DateTimeImmutable $end,
        public readonly string $caption,
        public readonly string|null $subtitle,
        public readonly string|null $description,
        public readonly string|null $information,
        public readonly string|null $link,
        public readonly string|null $location,
        public readonly string|null $address,
        public readonly bool $allDay,
        public readonly bool $isInternal,
        public readonly ImageReference|null $image,
    ) {
    }

    /**
     * @param array<string, mixed> $data Ein Element aus data[] des appointments-Endpunkts
     */
    public static function fromArray(array $data, int $calendarId): self|null
    {
        $base = $data['base'] ?? null;

        if (!\is_array($base) || !isset($base['id'])) {
            return null;
        }

        // Das Instanzdatum steht in "calculated"; nur wenn es fehlt, gilt das der Serie.
        $calculated = $data['calculated'] ?? [];
        $start = $calculated['startDate'] ?? $base['startDate'] ?? null;
        $end = $calculated['endDate'] ?? $base['endDate'] ?? $start;

        if (!\is_string($start)) {
            return null;
        }

        return new self(
            baseId: (int) $base['id'],
            calendarId: $calendarId,
            start: self::toDate($start),
            end: self::toDate(\is_string($end) ? $end : $start),
            caption: (string) ($base['caption'] ?? ''),
            subtitle: self::text($base['subtitle'] ?? null),
            description: self::text($base['description'] ?? null),
            information: self::text($base['information'] ?? null),
            link: self::text($base['link'] ?? null),
            location: self::text(\is_array($base['address'] ?? null) ? ($base['address']['meetingAt'] ?? null) : null),
            address: self::formatAddress($base['address'] ?? null),
            allDay: (bool) ($base['allDay'] ?? false),
            isInternal: (bool) ($base['isInternal'] ?? false),
            image: ImageReference::fromArray(\is_array($base['image'] ?? null) ? $base['image'] : null),
        );
        // Bewusst nicht übernommen: "note" - das ist das interne Notizfeld.
    }

    /**
     * Eindeutiger Schlüssel einer Instanz. Die base-ID allein genügt nicht,
     * weil alle Instanzen einer Serie sie teilen.
     */
    public function instanceKey(): string
    {
        return $this->baseId.'@'.$this->start->format('Y-m-d\TH:i:s');
    }

    /**
     * Erkennt Änderungen, ohne alle Felder einzeln zu vergleichen. Ist der Hash
     * gleich, wird nicht geschrieben - das hält den Cache ruhig.
     */
    public function contentHash(): string
    {
        return md5(serialize([
            $this->start->getTimestamp(),
            $this->end->getTimestamp(),
            $this->caption,
            $this->subtitle,
            $this->description,
            $this->information,
            $this->link,
            $this->location,
            $this->address,
            $this->allDay,
            $this->image?->filename,
            $this->image?->focusX,
            $this->image?->focusY,
        ]));
    }

    private static function toDate(string $value): \DateTimeImmutable
    {
        // ChurchTools liefert UTC ("...Z"). Die Umrechnung in die lokale Zeitzone
        // übernimmt der Sync, damit Sommer- und Winterzeit korrekt greifen.
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function text(mixed $value): string|null
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }

    /**
     * Die Anschrift ohne den Ortsnamen - der steht getrennt in location,
     * weil Contao beide Angaben separat führt.
     */
    private static function formatAddress(mixed $address): string|null
    {
        if (!\is_array($address)) {
            return null;
        }

        $parts = array_filter([
            self::text($address['street'] ?? null),
            self::text(trim(($address['zip'] ?? '').' '.($address['city'] ?? ''))),
        ]);

        return [] !== $parts ? implode(', ', $parts) : null;
    }
}
