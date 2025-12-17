<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle\Service;

use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Entity\ChangeLog;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use Alchemy\TrackBundle\Model\TrackActionTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class ChangeLogManager extends AbstractLogManager
{
    public function __construct(
        protected ObjectMapping $objectMapping,
        EntityManagerInterface $em,
        Security $security,
        RequestStack $requestStack,
    ) {
        parent::__construct($em, $security, $requestStack);
    }

    public function createChangeLog(
        TrackActionTypeEnum $action,
        LoggableChangeSetInterface $object,
        array $meta = [],
        array $changeSet = [],
        bool $persist = true,
        bool $inOnFlush = true,
    ): ChangeLog {
        $log = new ChangeLog();
        $log->setAction($action);
        $log->setObjectId($object->getId());
        $log->setObjectType($this->objectMapping->getObjectKey($object));
        $log->setChanges($changeSet);

        $this->fillLog(
            $log,
            $meta,
            $persist,
            $inOnFlush,
        );

        return $log;
    }
}
