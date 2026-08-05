<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Doctrine;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBatch
{
    private readonly \Closure $restoreLogger;
    private int $i = 0;
    private bool $terminated = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private array $entitiesToKeep = [], // Cannot be readonly (don't listen to PHPStorm :)
        private readonly int $batchSize = 50,
        private readonly bool $readOnly = false,
    ) {
        $this->restoreLogger = DoctrineUtil::disableLogger($em);
    }

    public function iterate(): void
    {
        if ($this->terminated) {
            throw new \RuntimeException('This batch was terminated');
        }

        ++$this->i;
        if (($this->i % $this->batchSize) === 0) {
            $this->flush();
        }
    }

    private function flush(): void
    {
        if (!$this->readOnly) {
            $this->em->flush();
        }
        $this->em->clear();
        foreach ($this->entitiesToKeep as &$object) {
            $object = $this->em->find($object::class, $object->getId());
        }
    }

    public function terminate(): void
    {
        if ($this->terminated) {
            throw new \RuntimeException('This batch was already terminated');
        }

        $this->terminated = true;
        $this->flush();
        ($this->restoreLogger)();
    }

    public function __destruct()
    {
        if ($this->terminated) {
            return;
        }

        $this->terminate();
    }
}
