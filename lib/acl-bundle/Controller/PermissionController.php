<?php

declare(strict_types=1);

namespace Alchemy\AclBundle\Controller;

use Alchemy\AclBundle\Mapping\ObjectMapping;
use Alchemy\AclBundle\Model\AccessControlEntryInterface;
use Alchemy\AclBundle\Repository\GroupRepositoryInterface;
use Alchemy\AclBundle\Repository\PermissionRepositoryInterface;
use Alchemy\AclBundle\Repository\UserRepositoryInterface;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use Alchemy\AclBundle\Serializer\AceSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PermissionController extends AbstractController
{
    private PermissionManager $permissionManager;
    private EntityManagerInterface $em;
    private ObjectMapping $objectMapping;

    public function __construct(PermissionManager $permissionManager, EntityManagerInterface $em, ObjectMapping $objectMapping)
    {
        $this->permissionManager = $permissionManager;
        $this->em = $em;
        $this->objectMapping = $objectMapping;
    }

    private function validateAuthorization(?Request $request = null): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($request instanceof Request) {
            $objectType = $request->request->get('objectType');
            $objectId = $request->request->get('objectId');

            if ($objectType && $objectId) {
                $object = $this->em->find($this->objectMapping->getClassName($objectType), $objectId);
                if (
                    $object instanceof AccessControlEntryInterface
                    && $this->isGranted($object, PermissionInterface::OWNER)
                ) {
                    return;
                }
            }
        }

        throw new AccessDeniedHttpException();
    }

    /**
     * @Route("/ace", methods={"PUT"}, name="ace")
     */
    public function setAce(Request $request): Response
    {
        $this->validateAuthorization($request);

        $objectType = $request->request->get('objectType');
        $objectId = $request->request->get('objectId');
        $userType = $request->request->get('userType');
        $userId = $request->request->get('userId');
        $mask = (int) $request->request->get('mask', 0);

        $objectId = !empty($objectId) ? $objectId : null;

        $this->permissionManager->updateOrCreateAce($userType, $userId, $objectType, $objectId, $mask);

        return new JsonResponse(true);
    }

    /**
     * @Route("/aces", methods={"GET"}, name="aces_index")
     */
    public function indexAces(
        Request $request,
        PermissionRepositoryInterface $repository,
        AceSerializer $aceSerializer
    ): Response
    {
        $this->validateAuthorization($request);

        $params = [
            'objectType' => $request->query->get('objectType', false),
            'objectId' => $request->query->get('objectId', false),
            'userType' => $request->query->get('userType', false),
            'userId' => $request->query->get('userId', false),
        ];

        $params = array_filter($params, function ($entry): bool {
            return false !== $entry;
        });
        $params = array_map(function ($p): ?string {
            return '' === $p || 'null' === $p ? null: $p;
        }, $params);

        if (!empty($params['userType'])) {
            $params['userType'] = AccessControlEntryInterface::USER_TYPES[$params['userType']] ?? false;
            if (false === $params['userType']) {
                throw new BadRequestHttpException('Invalid userType');
            }
        }

        $aces = $repository->findAces($params);

        return new JsonResponse(array_map(function (AccessControlEntryInterface $ace) use ($aceSerializer): array {
            return $aceSerializer->serialize($ace);
        }, $aces));
    }

    /**
     * @Route("/ace", methods={"DELETE"}, name="ace_delete")
     */
    public function deleteAce(Request $request): Response
    {
        $this->validateAuthorization($request);
        $objectType = $request->request->get('objectType');
        $objectId = $request->request->get('objectId');
        $userType = $request->request->get('userType');
        $userId = $request->request->get('userId');

        $objectId = !empty($objectId) ? $objectId : null;

        $this->permissionManager->deleteAce($userType, $userId, $objectType, $objectId);

        return new JsonResponse(true);
    }

    /**
     * @Route("/users", methods={"GET"}, name="users")
     */
    public function getUsers(Request $request, UserRepositoryInterface $repository): Response
    {
        $this->validateAuthorization();
        $limit = $request->query->get('limit');
        $offset = $request->query->get('offset');

        return new JsonResponse($repository->getUsers($limit, $offset));
    }

    /**
     * @Route("/groups", methods={"GET"}, name="groups")
     */
    public function getGroups(Request $request, GroupRepositoryInterface $repository): Response
    {
        $this->validateAuthorization();
        $limit = $request->query->get('limit');
        $offset = $request->query->get('offset');

        return new JsonResponse($repository->getGroups($limit, $offset));
    }
}
