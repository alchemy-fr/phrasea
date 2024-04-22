<?php

declare(strict_types=1);

namespace App\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Asset\Attribute\InitialAttributeValuesResolver;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Workspace;
use Doctrine\ORM\EntityManagerInterface;

readonly class InitializeAttributesAction implements ActionInterface
{
    public function __construct(
        private InitialAttributeValuesResolver $initialValueResolver,
        private EntityManagerInterface $em,
    ) {
    }

    public function handle(RunContext $context): void
    {
        $inputs = $context->getInputs();
        $assetId = $inputs['assetId'];

        $asset = DoctrineUtil::findStrict($this->em, Asset::class, $assetId);
        $workspace = $asset->getWorkspace();
        if (!$workspace instanceof Workspace) {
            throw new \InvalidArgumentException(sprintf('%s %s not found', Workspace::class, $asset->getWorkspaceId()));
        }

        $attributes = $this->initialValueResolver->resolveInitialAttributes($asset);

        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $this->em->persist($attribute);
        }
        $this->em->flush();
    }
}
