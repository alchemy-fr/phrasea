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

    private function hashAttributeValue(Attribute $attribute): string
    {
        return hash('sha256', $attribute->getValue());
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

        /** @var array<string, array<string, Attribute>> $assetAttributes */
        $assetAttributes = [];

        /** @var Attribute $attribute */
        foreach ($asset->getAttributes() as $attribute) {
            $defId = $attribute->getDefinition()->getId();
            $assetAttributes[$defId] ??= [];
            if (!array_key_exists($this->hashAttributeValue($attribute), $assetAttributes[$defId])) {
                $assetAttributes[$defId][$this->hashAttributeValue($attribute)] = $attribute;
            } else {
                // existing double, fix
                $this->em->remove($attribute);
            }
        }

        $assetAttributesCleaned = [];
        /** @var Attribute $attribute */
        foreach ($this->initialValueResolver->resolveInitialAttributes($asset) as $attribute) {
            $defId = $attribute->getDefinition()->getId();
            $isMono = !$attribute->getDefinition()->isMultiple();

            $assetAttributes[$defId] ??= [];
            if (!array_key_exists($defId, $assetAttributesCleaned)) {
                $nMono = 0;
                foreach ($assetAttributes[$defId] ?? [] as $k => $assetAttribute) {
                    // remove /initial/ values and repair mono that contains multiple values (keep only the first one)
                    if (Attribute::ORIGIN_INITIAL === $assetAttribute->getOrigin() || ($isMono && $nMono++ > 0)) {
                        $this->em->remove($assetAttribute);
                        $assetAttributes[$defId][$k] = null;
                    }
                }
                $assetAttributes[$defId] = array_filter($assetAttributes[$defId], function ($a) {
                    return null !== $a;
                });
                $assetAttributesCleaned[$defId] = true;
            }

            if ($isMono) {
                if (empty($assetAttributes[$defId])) {
                    $this->em->persist($attribute);
                    $assetAttributes[$defId][$this->hashAttributeValue($attribute)] = $attribute;
                }
            } else {
                if (!array_key_exists($this->hashAttributeValue($attribute), $assetAttributes[$defId])) {
                    $this->em->persist($attribute);
                    $assetAttributes[$defId][$this->hashAttributeValue($attribute)] = $attribute;
                }
            }
        }

        $this->em->flush();
    }
}
