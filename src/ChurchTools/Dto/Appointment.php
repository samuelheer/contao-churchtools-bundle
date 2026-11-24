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
        /** @var list<int> */
        public readonly array $tagIds = [],
        /** Letzter Tag der Serie, null bei Einzelterminen */
        public readonly \DateTimeImmutable|null $seriesEnd = null,
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
            tagIds: self::tagIds($data['tags'] ?? null),
            seriesEnd: self::seriesEnd($base),
        );
        // Bewusst nicht übernommen: "note" - das ist das interne Notizfeld.
    }

    /**
     * Trägt der Termin mindestens einen der Tags?
     *
     * @param list<int> $tagIds
     */
    public function hasAnyTag(array $tagIds): bool
    {
        return [] !== array_intersect($this->tagIds, $tagIds);
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
            $this->seriesEnd?->format('Y-m-d'),
        ]));
    }

    private static function toDate(string $value): \DateTimeImmutable
    {
        // ChurchTools liefert UTC ("...Z"). Die Umrechnung in die lokale Zeitzone
        // übernimmt der Sync, damit Sommer- und Winterzeit korrekt greifen.
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    /**
     * Ende einer Serie als reines Datum („Kurs bis 18. März"). repeatUntil ist
     * ein Kalendertag ohne Uhrzeit, deshalb ohne Zeitzonen-Umrechnung.
     * repeatId ist das Intervall in Tagen (1 täglich, 7 wöchentlich, 31 monatlich).
     *
     * @param array<string, mixed> $base
     */
    private static function seriesEnd(array $base): \DateTimeImmutable|null
    {
        $until = $base['repeatUntil'] ?? null;

        $repeat = (int) ($base['repeatId'] ?? 0);

        // Jährliche Serien (repeatId 365, etwa Heiligabend) haben kein sinnvolles
        // „bis" - sie erscheinen im Zeitfenster ohnehin nur einmal.
        if ($repeat < 1 || $repeat >= 365 || !\is_string($until) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $until)) {
            return null;
        }

        return new \DateTimeImmutable(substr($until, 0, 10).' 00:00:00', new \DateTimeZone(date_default_timezone_get()));
    }

    /**
     * Tags stehen neben "base", nicht darin, und kommen nur mit include[]=tags.
     * Ohne Tags liefert ChurchTools null statt einer leeren Liste.
     *
     * @return list<int>
     */
    private static function tagIds(mixed $tags): array
    {
        if (!\is_array($tags)) {
            return [];
        }

        $ids = [];

        foreach ($tags as $tag) {
            if (\is_array($tag) && isset($tag['id'])) {
                $ids[] = (int) $tag['id'];
            }
        }

        return $ids;
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
