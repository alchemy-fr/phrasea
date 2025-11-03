<?php

declare(strict_types=1);

namespace App\Service\Workflow\Action;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use Alchemy\Workflow\Executor\Action\ActionInterface;
use Alchemy\Workflow\Executor\RunContext;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\Workspace;
use App\Service\Asset\Attribute\InitialAttributeValuesResolver;
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

        /** @var array<string, bool> $assetAttributesExists */
        $assetAttributesExists = [];

        /** @var Attribute $attribute */
        foreach ($asset->getAttributes() as $attribute) {
            $assetAttributesExists[$attribute->getDefinition()->getId()] = true;
        }

        /** @var Attribute $attribute */
        foreach ($this->initialValueResolver->resolveInitialAttributes($asset) as $attribute) {
            if (!isset($assetAttributesExists[$attribute->getDefinition()->getId()])) {
                $this->em->persist($attribute);
            }
        }

        $this->em->flush();
    }
}
