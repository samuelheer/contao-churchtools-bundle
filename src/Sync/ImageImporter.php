<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Sync;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\FilesModel;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\Dto\ImageReference;
use SamuelHeer\ContaoChurchToolsBundle\Connection\Connection;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Holt Flyer aus ChurchTools in die Contao-Dateiverwaltung.
 *
 * Heruntergeladen wird nur, wenn die Datei fehlt - der Dateiname enthält den
 * ChurchTools-Hash, ändert sich also genau dann, wenn dort ein anderes Bild liegt.
 */
class ImageImporter
{
    private string|null $lastError = null;

    public function __construct(
        private readonly ChurchToolsClient $client,
        private readonly ContaoFramework $framework,
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return string|null Binäre UUID der Datei für singleSRC
     */
    public function lastError(): string|null
    {
        return $this->lastError;
    }

    public function import(Connection $connection, ImageReference $image, bool $dryRun = false): string|null
    {
        $this->framework->initialize();
        $this->lastError = null;

        $relativePath = $this->relativePath($connection, $image);
        $absolutePath = $this->projectDir.'/'.$relativePath;

        $existing = FilesModel::findByPath($relativePath);

        if (null !== $existing && is_file($absolutePath)) {
            $this->applyFocusPoint($existing, $image);

            return $existing->uuid;
        }

        if ($dryRun) {
            return null;
        }

        try {
            $content = $this->client->download($connection, $image->imageUrl);
        } catch (ChurchToolsException $e) {
            // Die Ursache weiterreichen - sonst steht im Bericht nur, dass es
            // nicht ging, und niemand weiß, wo er suchen soll.
            $this->lastError = $e->getMessage();

            return null;
        }

        if ('' === $content) {
            return null;
        }

        $this->filesystem->mkdir(\dirname($absolutePath));
        $this->filesystem->dumpFile($absolutePath, $content);

        // Dbafs vergibt die UUID und legt den Datensatz in tl_files an.
        $model = Dbafs::addResource($relativePath);

        if (null === $model) {
            return null;
        }

        $this->applyFocusPoint($model, $image);

        return $model->uuid;
    }

    /**
     * Überträgt den Fokuspunkt aus ChurchTools auf Contaos wichtigen Bildbereich.
     * Ohne das schneidet Contao Hochformat-Flyer in Listenansichten mittig durch.
     */
    private function applyFocusPoint(FilesModel $model, ImageReference $image): void
    {
        // Ein Punkt, kein Bereich: Contao rechnet daraus den erhaltenswerten Ausschnitt.
        $x = max(0.0, min(1.0, $image->focusX));
        $y = max(0.0, min(1.0, $image->focusY));

        if (abs((float) $model->importantPartX - $x) < 0.0001 && abs((float) $model->importantPartY - $y) < 0.0001) {
            return;
        }

        $model->importantPartX = $x;
        $model->importantPartY = $y;
        $model->importantPartWidth = 0.0;
        $model->importantPartHeight = 0.0;
        $model->save();
    }

    private function relativePath(Connection $connection, ImageReference $image): string
    {
        $base = $connection->filesPath;

        if ('' === $base) {
            $base = 'files/churchtools';
        }

        if (!str_starts_with($base, 'files/')) {
            $base = 'files/'.$base;
        }

        return $base.'/'.$image->targetFilename();
    }
}
