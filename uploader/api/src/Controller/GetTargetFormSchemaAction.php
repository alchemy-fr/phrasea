<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FormSchema;
use App\Entity\Target;
use App\Security\Voter\TargetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetTargetFormSchemaAction extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(string $id, Request $request): ?FormSchema
    {
        $target = $this->em->find(Target::class, $id);
        if (!$target instanceof Target) {
            throw new NotFoundHttpException(sprintf('Target "%s" not found', $id));
        }
        $this->denyAccessUnlessGranted(TargetVoter::READ, $target);

        return $this->em->getRepository(FormSchema::class)
            ->getSchemaForLocale($target->getId(), $request->getLocale());
    }
}
