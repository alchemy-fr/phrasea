<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle\Service;

use Alchemy\CoreBundle\Mapping\ObjectMapping;
use Alchemy\TrackBundle\Entity\ChangeLog;
use Alchemy\TrackBundle\LoggableChangeSetInterface;
use Alchemy\TrackBundle\Model\TrackActionTypeEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ChangeLogManager
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private ObjectMapping $objectMapping,
    ) {
    }

    public function createChangeLog(TrackActionTypeEnum $action, LoggableChangeSetInterface $object, array $meta = [], array $changeSet = []): ChangeLog
    {
        $user = $this->security->getUser();

        $log = new ChangeLog();
        $log->setAction($action);
        $log->setObjectId($object->getId());
        $log->setObjectType($this->objectMapping->getObjectKey($object));

        if (null !== $request = $this->requestStack->getCurrentRequest()) {
            $meta['uri'] = substr($request->getUri(), strlen($request->getSchemeAndHttpHost().$request->getBaseUrl()));
            $meta['_route'] = $request->attributes->get('_route');
            $meta['ip'] = $request->getClientIp();
        }

        $log->setMeta($meta);
        $log->setChanges($changeSet);

        if ($user instanceof UserInterface) {
            $log->setUserId($user->getUserIdentifier());
            //            $log->setImpersonatorId() // TODO;
        }

        return $log;
    }
}
