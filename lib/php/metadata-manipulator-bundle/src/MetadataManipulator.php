<?php

namespace Alchemy\MetadataManipulatorBundle;

use Alchemy\MetadataManipulatorBundle\Exception\UnknownTagGroupNameException;
use Exception;
use PHPExiftool\Driver\Metadata\Metadata;
use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\Driver\TagGroupInterface;
use PHPExiftool\PHPExiftool;
use PHPExiftool\Reader;
use PHPExiftool\Writer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MetadataManipulator
{
    private ?LoggerInterface $logger = null;
    private static ?array $knownTagGroups = null;  // cache

    /**
     * @required
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getKnownTagGroups(): array
    {
        if (null === self::$knownTagGroups) {
            self::$knownTagGroups = PHPExiftool::getKnownTagGroups();
        }

        return self::$knownTagGroups;
    }

    public function getClassnameFromTagGroupName(string $tagGroupName)
    {
        return PHPExiftool::tagGroupIdToClassname($tagGroupName);
    }

    public function getReader(): Reader
    {
        return Reader::create($this->logger ?? new NullLogger());
    }

    public function getWriter(): Writer
    {
        return Writer::create($this->logger ?? new NullLogger());
    }

    public function createTagGroup(string $tagGroupName): TagGroupInterface
    {
        $className = $this->getClassnameFromTagGroupName($tagGroupName);

        return new $className();
    }

    public function createMetadata(string $tagGroupName): Metadata
    {
        $className = $this->getClassnameFromTagGroupName($tagGroupName);
        if (class_exists($className)) {
            return new Metadata(new $className());
        } else {
            throw new UnknownTagGroupNameException(sprintf('Unknown tagGroupName "%s"', $tagGroupName));
        }
    }

    public function getAllMetadata(\SplFileObject $file): MetadataBag
    {
        $reader = $this->getReader();
        $reader->files($file->getRealPath());

        return $reader->first()->getMetadatas();
    }

    /**
     * @throws Exception
     */
    public function setMetadata(\SplFileObject $file, MetadataBag $bag): void
    {
        $writer = $this->getWriter();
        $writer->write($file->getRealPath(), $bag);
    }
}
