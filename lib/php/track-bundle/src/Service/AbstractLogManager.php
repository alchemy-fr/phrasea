<?php

declare(strict_types=1);

namespace Alchemy\TrackBundle\Service;

use Alchemy\TrackBundle\Entity\AbstractLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

abstract readonly class AbstractLogManager
{
    public function __construct(
        protected EntityManagerInterface $em,
        protected Security $security,
        protected RequestStack $requestStack,
    ) {
    }

    public function fillLog(
        AbstractLog $log,
        array $meta = [],
        bool $persist = false,
        bool $inOnFlush = false,
    ): void {
        $user = $this->security->getUser();

        if (null !== $request = $this->requestStack->getCurrentRequest()) {
            $meta['uri'] = substr($request->getUri(), strlen($request->getSchemeAndHttpHost().$request->getBaseUrl()));
            $meta['_route'] = $request->attributes->get('_route');
            $log->setIp($request->getClientIp());
        }

        $log->setMeta($meta);

        if ($user instanceof UserInterface) {
            $log->setUserId($user->getUserIdentifier());
        }

        if ($persist) {
            if ($inOnFlush) {
                $unitOfWork = $this->em->getUnitOfWork();
                $unitOfWork->persist($log);
                $metadata = $this->em->getClassMetadata($log::class);
                $unitOfWork->computeChangeSet($metadata, $log);
            } else {
                $this->em->persist($log);
            }
        }
    }
}
