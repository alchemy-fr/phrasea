<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle\Service;

use Alchemy\TrackBundle\Entity\AbstractLog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

abstract readonly class AbstractLogManager
{
    public function __construct(
        protected Security $security,
        protected RequestStack $requestStack,
    ) {
    }

    public function fillLog(AbstractLog $log, array $meta = []): void
    {
        $user = $this->security->getUser();

        if (null !== $request = $this->requestStack->getCurrentRequest()) {
            $meta['uri'] = substr($request->getUri(), strlen($request->getSchemeAndHttpHost().$request->getBaseUrl()));
            $meta['_route'] = $request->attributes->get('_route');
            $meta['ip'] = $request->getClientIp();
        }

        $log->setMeta($meta);

        if ($user instanceof UserInterface) {
            $log->setUserId($user->getUserIdentifier());
        }
    }
}
