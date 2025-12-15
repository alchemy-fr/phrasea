<?php

namespace App\Service\Log;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\AlchemyTrackBundle;
use Alchemy\TrackBundle\Service\AbstractLogManager;
use App\Entity\Log\ActionLog;
use App\Model\ActionLogTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ActionLogManager extends AbstractLogManager
{
    public function __construct(
        #[Autowire(service: AlchemyTrackBundle::OBJECT_MAPPING_SERVICE_ID)]
        protected ObjectMapping $objectMapping,
        protected EntityManagerInterface $em,
        Security $security,
        RequestStack $requestStack,
    ) {
        parent::__construct($security, $requestStack);
    }

    public function createLogAction(
        ActionLogTypeEnum $action,
        ?AbstractUuidEntity $object,
        array $data = [],
        array $meta = [],
        bool $inOnFlush = false,
    ): ActionLog {
        $log = new ActionLog();
        $log->setAction($action);
        $log->setObjectId($object->getId());
        $log->setObjectType($this->objectMapping->getObjectKey($object));
        $log->setData($data);

        $this->fillLog($log, $meta);

        if ($inOnFlush) {
            $unitOfWork = $this->em->getUnitOfWork();
            $unitOfWork->persist($log);
            $metadata = $this->em->getClassMetadata($log::class);
            $unitOfWork->computeChangeSet($metadata, $log);
        } else {
            $this->em->persist($log);
        }

        return $log;
    }
}
