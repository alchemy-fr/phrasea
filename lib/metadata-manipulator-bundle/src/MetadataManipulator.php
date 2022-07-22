<?php

namespace Alchemy\MetadataManipulatorBundle;

use PHPExiftool\Driver\Metadata\MetadataBag;
use PHPExiftool\PHPExiftool;
use PHPExiftool\Reader;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MetadataManipulator
{
    private ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getKnownTagGroups(): array
    {
        return PHPExiftool::getKnownTagGroups();
    }

    public function getReader(): Reader
    {
        return Reader::create($this->logger ?: new NullLogger());
    }

    public function getMetadatas(\SplFileObject $file): MetadataBag
    {
        $reader = $this->getReader();
        $reader->files($file->getRealPath());

        return $reader->first()->getMetadatas();
    }
}
