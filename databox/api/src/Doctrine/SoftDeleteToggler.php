<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Doctrine\Listener\SoftDeleteListener;
use Doctrine\ORM\EntityManagerInterface;

class SoftDeleteToggler
{
    private const FILTER_NAME = 'softdeleteable';
    private EntityManagerInterface $em;
    private SoftDeleteListener $softDeleteListener;

    public function __construct(EntityManagerInterface $em, SoftDeleteListener $softDeleteListener)
    {
        $this->em = $em;
        $this->softDeleteListener = $softDeleteListener;
    }

    public function enable(): void
    {
        $this->softDeleteListener->enable();
        $this->em->getFilters()->enable(self::FILTER_NAME);
    }

    public function disable(): void
    {
        $this->softDeleteListener->disable();
        $this->em->getFilters()->disable(self::FILTER_NAME);
    }
}
