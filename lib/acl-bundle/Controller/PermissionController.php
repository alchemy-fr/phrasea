<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends AbstractController
{
    private ObjectMapping $objectMapping;
    private PermissionRepositoryInterface $repository;

    public function __construct(ObjectMapping $objectMapping, PermissionRepositoryInterface $repository)
    {
        $this->objectMapping = $objectMapping;
        $this->repository = $repository;
    }

    public function acl(string $entityClass, string $id): Response
    {
        $objectKey = $this->objectMapping->getObjectKey($entityClass);

        $permissions = PermissionInterface::PERMISSIONS;

        $aces = $this->repository->getObjectAces($objectKey.':'.$id);

        $users = [];

        return $this->render('@AlchemyAcl/permissions/acl.html.twig', [
            'object_key' => $objectKey,
            'object_id' => $id,
            'permissions' => $permissions,
            'aces' => $aces,
            'users' => $users,
        ]);
    }
}
