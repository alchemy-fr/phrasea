<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Asset;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class AssetDeleteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(AssetDelete $message): void
    {
        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $message->getId());
        $this->em->remove($asset);
        $this->em->flush();
    }
}
