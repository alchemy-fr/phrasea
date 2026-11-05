<?php

declare(strict_types=1);

namespace App\Service\Clip;

use Alchemy\CoreBundle\Util\FilesystemUtils;
use Alchemy\RenditionFactory\DTO\CreateRenditionOptions;
use Alchemy\RenditionFactory\DTO\FamilyEnum;
use Alchemy\RenditionFactory\FileFamilyGuesser;
use Alchemy\RenditionFactory\RenditionCreator;
use App\Model\MediaClip;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

/**
 * Cuts a temporary copy of a file to a clip (region of interest of an asset, clip of a basket item)
 * for the Expose synchronization and the exports. Several time ranges are cut one by one, with
 * the same settings, then concatenated in their order: the result is still a single file.
 * Nothing is stored: call cleanUp() once the result has been used.
 */
final class MediaClipper
{
    private const int FFMPEG_TIMEOUT = 3600;

    private ?FFProbe $ffprobe = null;

    /**
     * @var string[] directories of the concatenated files
     */
    private array $workingDirectories = [];

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
    public function apply(string $srcPath, string $mimeType, ?MediaClip $clip): ClipResult
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

            if (count($clip->getTimeRanges()) > 1) {
                $ranges = $this->getRangesWithinMedia($clip, $srcPath, $probe);
                if (count($ranges) > 1) {
                    return $this->cutAndConcat($srcPath, $mimeType, $family, $clip, $ranges, $probe);
                }

                $clip = $clip->withTimeRanges($ranges);
            }

            $buildConfig = $this->buildConfigFactory->create($family, $mimeType, $clip, $probe);
            if (null === $buildConfig) {
                return $noop;
            }

            $output = $this->renditionCreator->createRendition($srcPath, $mimeType, $buildConfig, new CreateRenditionOptions());
        } catch (ClipException $e) {
            throw $e;
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

        foreach ($this->workingDirectories as $dir) {
            if (is_dir($dir)) {
                FilesystemUtils::rrmdir($dir);
            }
        }
        $this->workingDirectories = [];
    }

    /**
     * @return list<array{s: float, e: float}> the ranges starting before the end of the media
     *
     * @throws ClipException when none does
     */
    private function getRangesWithinMedia(MediaClip $clip, string $srcPath, ?VideoProbe $probe): array
    {
        $duration = null !== $probe ? $probe->duration : $this->probeDuration($srcPath);
        $ranges = $clip->getTimeRanges();
        if (null === $duration || $duration <= 0) {
            return $ranges;
        }

        $ranges = array_values(array_filter($ranges, static fn (array $range): bool => $range['s'] < $duration));
        if ([] === $ranges) {
            throw new ClipException(sprintf('Every time range of the clip starts after the end of the media (%.2fs)', $duration));
        }

        return $ranges;
    }

    /**
     * @param list<array{s: float, e: float}> $ranges
     */
    private function cutAndConcat(string $srcPath, string $mimeType, FamilyEnum $family, MediaClip $clip, array $ranges, ?VideoProbe $probe): ClipResult
    {
        $parts = [];
        $outputType = null;
        $extension = null;
        foreach ($ranges as $range) {
            $buildConfig = $this->buildConfigFactory->create($family, $mimeType, $clip->withTimeRanges([$range]), $probe)
                ?? throw new \LogicException('A time range applies to a video or an audio file');

            $output = $this->renditionCreator->createRendition($srcPath, $mimeType, $buildConfig, new CreateRenditionOptions());
            $parts[] = $output->getPath();
            $outputType ??= $output->getType();
            $extension ??= pathinfo($output->getPath(), PATHINFO_EXTENSION);
        }

        $dir = sys_get_temp_dir().'/'.uniqid('media-clip', true);
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory', $dir));
        }
        $this->workingDirectories[] = $dir;
        $outputPath = $dir.'/clip'.('' !== $extension ? '.'.$extension : '');

        // The parts share their codecs and settings: their streams are copied as is
        FFMpeg::create(['timeout' => self::FFMPEG_TIMEOUT], null, $this->getFFProbe())
            ->open($parts[0])
            ->concat($parts)
            ->saveFromSameCodecs($outputPath, true);

        if (!is_file($outputPath) || 0 === filesize($outputPath)) {
            throw new \RuntimeException('The concatenated file is empty');
        }

        return new ClipResult($outputPath, $outputType, '' !== $extension ? $extension : null, true);
    }

    private function getFFProbe(): FFProbe
    {
        return $this->ffprobe ??= FFProbe::create();
    }

    private function probeDuration(string $path): ?float
    {
        $duration = $this->getFFProbe()->format($path)->get('duration');

        return is_numeric($duration) ? (float) $duration : null;
    }

    private function probeVideo(string $path): VideoProbe
    {
        $ffprobe = $this->getFFProbe();

        $stream = $ffprobe->streams($path)->videos()->first();
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

        $format = $ffprobe->format($path);
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
