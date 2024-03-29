<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Core\Collection;
use App\Entity\Core\Tag;
use App\Entity\Core\TagFilterRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class TagFilterRuleConstraintValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param TagFilterRule $value
     */
    public function validate($value, Constraint $constraint): void
    {
        if (TagFilterRule::TYPE_COLLECTION === $value->getObjectType()) {
            $collection = $this->em->getRepository(Collection::class)->find($value->getObjectId());
            if (!$collection instanceof Collection) {
                throw new \RuntimeException('Collection not found when validating tag filter rule');
            }
            $workspaceId = $collection->getWorkspaceId();
        } else {
            $workspaceId = $value->getObjectId();
        }

        foreach ($value->getInclude() as $t) {
            if ($t->getWorkspace()->getId() !== $workspaceId) {
                $this->addTagViolation($t, $workspaceId);
            }
        }
        foreach ($value->getExclude() as $t) {
            if ($t->getWorkspace()->getId() !== $workspaceId) {
                $this->addTagViolation($t, $workspaceId);
            }
        }
    }

    private function addTagViolation(Tag $tag, string $workspaceId): void
    {
        $this->context
            ->buildViolation(sprintf('Tag #%s is not part of workspace %s', $tag->getId(), $workspaceId))
            ->addViolation();
    }
}
