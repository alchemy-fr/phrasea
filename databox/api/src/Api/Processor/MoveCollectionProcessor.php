<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Core\Collection;
use App\Security\Voter\AbstractVoter;
use App\Util\SecurityAwareTrait;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MoveCollectionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    public function __construct(
        private readonly EventProducer $eventProducer,
        private readonly EntityManagerInterface $em,
        private readonly IriConverterInterface $iriConverter
    ) {
    }

    /**
     * @param Collection $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $data);
        $dest = $uriVariables['dest'];
        $isRoot = 'root' === $dest;

        if ($isRoot) {
            $destination = null;
        } else {
            $destination = $this->em->find(Collection::class, $dest);
            if (!$destination instanceof Collection) {
                throw new NotFoundHttpException(sprintf('Collection destination "%s" not found', $dest));
            }
            $this->denyAccessUnlessGranted(AbstractVoter::EDIT, $destination);
        }

        if ($destination === $data) {
            throw new \InvalidArgumentException('Cannot reference parent to itself!');
        }

        $data->setParent($destination);

        $this->em->persist($data);
        $this->em->flush();

        return new Response('', 204);
    }
}
