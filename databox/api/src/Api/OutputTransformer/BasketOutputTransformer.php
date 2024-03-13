<?php

declare(strict_types=1);

namespace App\Api\OutputTransformer;

use App\Api\Model\Output\BasketOutput;
use App\Api\Traits\UserLocaleTrait;
use App\Entity\Basket\Basket;
use App\Entity\Basket\BasketAsset;
use App\Security\Voter\AbstractVoter;
use App\Security\Voter\BasketVoter;
use App\Util\SecurityAwareTrait;
use Doctrine\ORM\EntityManagerInterface;

class BasketOutputTransformer implements OutputTransformerInterface
{
    use SecurityAwareTrait;
    use UserOutputTransformerTrait;
    use UserLocaleTrait;
    use GroupsHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(string $outputClass, object $data): bool
    {
        return BasketOutput::class === $outputClass && $data instanceof Basket;
    }

    /**
     * @param Basket $data
     */
    public function transform(object $data, string $outputClass, array &$context = []): object
    {
        $output = new BasketOutput();
        $output->setCreatedAt($data->getCreatedAt());
        $output->setUpdatedAt($data->getUpdatedAt());
        $output->setId($data->getId());

        $highlights = $data->getElasticHighlights();
        $output->setTitle($data->getTitle());
        $output->setTitleHighlight($highlights['title'] ?? $data->getTitle());

        if ($this->hasGroup([
            Basket::GROUP_READ,
        ], $context)) {
            $output->owner = $this->transformUser($data->getOwnerId());
            $output->assetCount = (int) $this->em->getRepository(BasketAsset::class)
                ->createQueryBuilder('t')
                ->select('COUNT(t.id) as total')
                ->andWhere('t.basket = :b')
                ->setParameter('b', $data->getId())
                ->getQuery()
                ->getSingleScalarResult()
            ;
        }

        if ($this->hasGroup([Basket::GROUP_LIST, Basket::GROUP_READ], $context)) {
            $output->setCapabilities([
                'canEdit' => $this->isGranted(AbstractVoter::EDIT, $data),
                'canShare' => $this->isGranted(BasketVoter::SHARE, $data),
                'canDelete' => $this->isGranted(AbstractVoter::DELETE, $data),
                'canEditPermissions' => $this->isGranted(AbstractVoter::EDIT_PERMISSIONS, $data),
            ]);
        }

        return $output;
    }
}
