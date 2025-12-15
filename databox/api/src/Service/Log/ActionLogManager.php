<?php

namespace App\Service\Log;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Service\AbstractLogManager;
use App\Entity\Log\ActionLog;
use App\Model\ActionLogTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ActionLogManager extends AbstractLogManager
{
    public function __construct(
        protected ObjectMapping $objectMapping,
        protected EntityManagerInterface $em,
        Security $security,
        RequestStack $requestStack,
    ) {
        parent::__construct($security, $requestStack);
    }

    public function logAction(ActionLogTypeEnum $action, ?AbstractUuidEntity $object, array $data = [], array $meta = []): ActionLog
    {
        $log = new ActionLog();
        $log->setAction($action);
        $log->setObjectId($object->getId());
        $log->setObjectType($this->objectMapping->getObjectKey($object));
        $log->setData($data);

        $this->fillLog($log, $meta);

        $this->em->persist($log);

        return $log;
    }
}
