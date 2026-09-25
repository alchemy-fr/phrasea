<?php

namespace Alchemy\MetadataManipulatorBundle;

use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroupInterface;
use PHPExiftool\Exception\ExceptionInterface as PHPExiftoolExceptionInterface;
use PHPExiftool\PHPExiftool;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MetadataManipulator
{
    private static ?array $knownTagGroups = null;  // cache
    private readonly PHPExiftool $phpExifTool;
    private readonly LoggerInterface $logger;

    public function __construct(
        string $classesDirectory,
        ?LoggerInterface $logger = null,
        bool $debug = false,
    ) {
        $this->phpExifTool = new PHPExiftool($classesDirectory);
        $this->logger = $logger ?? new NullLogger();

        if ($debug) {
            $this->phpExifTool->setLogger($this->logger);
        }
    }

    public static function getKnownTagGroups(): array
    {
        if (null === self::$knownTagGroups) {
            self::$knownTagGroups = PHPExiftool::getKnownTagGroups();
        }

        return self::$knownTagGroups;
    }

    public function createTagGroup(string $tagGroupName): TagGroupInterface
    {
        return $this->phpExifTool->getFactory()->createTagGroup($tagGroupName);
    }

    public function createMetadata(string $tagGroupName): Metadata
    {
        return new Metadata($this->createTagGroup($tagGroupName));
    }

    public function getAllMetadata(\SplFileObject $file): MetadataBag
    {
        $reader = $this->phpExifTool->getFactory()->createReader();

        $reader->files($file->getRealPath());

        try {
            return $reader->first()->getMetadatas();
        } catch (PHPExiftoolExceptionInterface $e) {
            // exiftool exits with an error (and no output) on empty, truncated or
            // unknown files: treat it as "no metadata" rather than failing the caller.
            $this->logger->warning('Unable to read metadata with exiftool, returning an empty bag', [
                'file' => $file->getRealPath(),
                'error' => $e->getMessage(),
            ]);

            return new MetadataBag();
        }
    }

    public function createWriter(): Writer
    {
        return $this->phpExifTool->getFactory()->createWriter();
    }

    /**
     * @throws \Exception
     */
    public function setMetadata(\SplFileObject $file, MetadataBag $bag): void
    {
        $writer = $this->phpExifTool->getFactory()->createWriter();
        $writer->write($file->getRealPath(), $bag);
    }

    public function getClassesDirectory(): string
    {
        return $this->phpExifTool->getClassesRootDirectory();
    }

    public function getPhpExifTool(): PHPExiftool
    {
        return $this->phpExifTool;
    }
}
