<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Group;
use App\Security\Voter\GroupVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GetGroupsAction extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route(path="/groups")
     */
    public function __invoke(Request $request)
    {
        $limit = $request->query->get('limit', 200);
        $offset = $request->query->get('offset', 0);
        $limit = $limit > 200 ? 200 : $limit;

        $users = $this->em
            ->getRepository(Group::class)
            ->findBy(
                [],
                ['name' => 'ASC'],
                $limit,
                $offset
            );

        $this->denyAccessUnlessGranted(GroupVoter::LIST_GROUPS);

        return new JsonResponse(array_map(fn (Group $group): array => [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'userCount' => $group->getUserCount(),
        ], $users));
    }
}
