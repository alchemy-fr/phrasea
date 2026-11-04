<?php

declare(strict_types=1);

namespace App\Service\Clip;

use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Model\BasketClip;
use FFMpeg\FFProbe;

/**
 * Cuts a temporary copy of a file to a basket clip (Expose synchronization, exports).
 * Nothing is stored: call cleanUp() once the result has been used.
 */
final class MediaClipper
{
    private ?FFProbe $ffprobe = null;

    public function __construct(
        private readonly RenditionCreator $renditionCreator,
        private readonly FileFamilyGuesser $fileFamilyGuesser,
        private readonly ClipBuildConfigFactory $buildConfigFactory,
    ) {
    }

    /**
     * @return ClipResult the source itself when the clip does not apply to the file
     *
     * @throws ClipException when the clip applies but the file could not be cut
     */
    public function apply(string $srcPath, string $mimeType, ?BasketClip $clip): ClipResult
    {
        $noop = new ClipResult($srcPath, $mimeType, null, false);
        if (null === $clip) {
            return $noop;
        }

        try {
            $family = $this->fileFamilyGuesser->getFamily($srcPath, $mimeType);
            $clip = $clip->forFamily($family);
            if (null === $clip) {
                return $noop;
            }

            $probe = FamilyEnum::Video === $family ? $this->probeVideo($srcPath) : null;
            $buildConfig = $this->buildConfigFactory->create($family, $mimeType, $clip, $probe);
            if (null === $buildConfig) {
                return $noop;
            }

            $output = $this->renditionCreator->createRendition($srcPath, $mimeType, $buildConfig, new CreateRenditionOptions());
        } catch (\Throwable $e) {
            throw new ClipException(sprintf('Unable to clip the "%s" file: %s', $mimeType, $e->getMessage()), 0, $e);
        }

        $extension = pathinfo($output->getPath(), PATHINFO_EXTENSION);

        return new ClipResult($output->getPath(), $output->getType(), '' !== $extension ? $extension : null, true);
    }

    /**
     * Removes the files produced by apply().
     */
    public function cleanUp(): void
    {
        $this->renditionCreator->cleanUp();
    }

    private function probeVideo(string $path): VideoProbe
    {
        $this->ffprobe ??= FFProbe::create();

        $stream = $this->ffprobe->streams($path)->videos()->first();
        if (null === $stream) {
            throw new \InvalidArgumentException('No video stream found');
        }

        $dimensions = $stream->getDimensions();
        $data = $stream->all();

        $rotation = 0;
        foreach ($data['side_data_list'] ?? [] as $sideData) {
            if (isset($sideData['rotation'])) {
                $rotation = (int) $sideData['rotation'];
                break;
            }
        }
        if (0 === $rotation && isset($data['tags']['rotate'])) {
            $rotation = (int) $data['tags']['rotate'];
        }

        $format = $this->ffprobe->format($path);
        $bitRate = $data['bit_rate'] ?? $format->get('bit_rate');
        $duration = $format->get('duration') ?? $data['duration'] ?? null;

        return new VideoProbe(
            $dimensions->getWidth(),
            $dimensions->getHeight(),
            $rotation,
            is_numeric($bitRate) ? (int) round($bitRate / 1000) : null,
            is_numeric($duration) ? (float) $duration : null,
        );
    }
}
