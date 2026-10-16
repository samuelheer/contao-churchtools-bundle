<?php

declare(strict_types=1);

/*
 * This file is part of samuelheer/contao-churchtools-bundle.
 *
 * @license LGPL-3.0-or-later
 */

namespace SamuelHeer\ContaoChurchToolsBundle\Controller;

use SamuelHeer\ContaoChurchToolsBundle\ChurchTools\ChurchToolsClient;
use SamuelHeer\ContaoChurchToolsBundle\Connection\ConnectionRegistry;
use SamuelHeer\ContaoChurchToolsBundle\Exception\ChurchToolsException;
use SamuelHeer\ContaoChurchToolsBundle\Person\PersonProvider;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Liefert Personenbilder aus ChurchTools aus.
 *
 * Personenbilder in ChurchTools sind nicht öffentlich abrufbar, deshalb dieser
 * Umweg. Das Bild wird nie im Web-Verzeichnis abgelegt - es kommt aus dem Cache
 * und geht direkt an den Browser. Fehlt die Freigabe, antwortet die Route mit
 * 404 statt mit einem Platzhalter, der die Existenz der Person bestätigen würde.
 */
class AvatarController
{
    /**
     * Ausgelieferte Kantenlänge in Pixeln - doppelt so groß wie die übliche
     * Darstellung, damit das Bild auf hochauflösenden Displays scharf bleibt.
     */
    private const SIZES = [
        'small' => 200,
        'medium' => 600,
        'large' => 1200,
    ];

    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly PersonProvider $personProvider,
        private readonly ChurchToolsClient $client,
        private readonly TagAwareAdapterInterface $cache,
    ) {
    }

    public function __invoke(int $connection, int $personId, string $size): Response
    {
        $conn = $this->registry->find($connection);

        if (null === $conn) {
            throw new NotFoundHttpException();
        }

        $person = $this->personProvider->get($conn, $personId);

        // Nicht freigegeben, nicht in der Bildfreigabegruppe oder kein Bild: alles 404.
        if (null === $person || null === $person->imageUrl || !$conn->allowsPersonField('image') || !$this->personProvider->isImageReleased($conn, $personId)) {
            throw new NotFoundHttpException();
        }

        $width = self::SIZES[$size] ?? self::SIZES['medium'];
        $key = $this->personProvider->cacheTag($conn, $personId).'.avatar.'.$width;
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $payload = $item->get();
        } else {
            try {
                // imageUrl ist nur ein 150-px-Vorschaubild. Mit w/h liefert ChurchTools
                // das Original quadratisch um den gepflegten Fokuspunkt zugeschnitten.
                $original = $this->client->download($conn, $this->sizedUrl($person->imageUrl, $width));
            } catch (ChurchToolsException) {
                throw new NotFoundHttpException();
            }

            $payload = $this->scale($original, $width);

            $item->set($payload);
            $item->expiresAfter(86400);
            // Verliert die Person ihre Freigabe, verschwindet mit dem Personen-Tag
            // auch das Bild aus dem Cache.
            $item->tag([$this->personProvider->cacheTag($conn, $personId)]);
            $this->cache->save($item);
        }

        $response = new Response($payload['content']);
        $response->headers->set('Content-Type', $payload['mime']);
        $response->setMaxAge(3600);
        $response->headers->addCacheControlDirective('private');

        return $response;
    }

    private function sizedUrl(string $imageUrl, int $width): string
    {
        return $imageUrl.(str_contains($imageUrl, '?') ? '&' : '?').http_build_query(['w' => $width, 'h' => $width]);
    }

    /**
     * Skaliert im Arbeitsspeicher. Bewusst ohne Contaos Bildverarbeitung: die
     * legt Ableitungen unter assets/images ab, wo sie auch nach einem Widerruf
     * der Freigabe liegen blieben.
     *
     * @return array{content: string, mime: string}
     */
    private function scale(string $content, int $width): array
    {
        if (!\function_exists('imagecreatefromstring')) {
            return ['content' => $content, 'mime' => $this->detectMime($content)];
        }

        $image = @imagecreatefromstring($content);

        if (false === $image) {
            return ['content' => $content, 'mime' => $this->detectMime($content)];
        }

        $originalWidth = imagesx($image);

        if ($originalWidth <= $width) {
            imagedestroy($image);

            return ['content' => $content, 'mime' => $this->detectMime($content)];
        }

        $height = (int) round(imagesy($image) * ($width / $originalWidth));
        $scaled = imagescale($image, $width, $height);
        imagedestroy($image);

        if (false === $scaled) {
            return ['content' => $content, 'mime' => $this->detectMime($content)];
        }

        ob_start();
        imagejpeg($scaled, null, 82);
        $out = (string) ob_get_clean();
        imagedestroy($scaled);

        return ['content' => $out, 'mime' => 'image/jpeg'];
    }

    private function detectMime(string $content): string
    {
        $info = @getimagesizefromstring($content);

        return \is_array($info) && isset($info['mime']) ? (string) $info['mime'] : 'image/jpeg';
    }
}
