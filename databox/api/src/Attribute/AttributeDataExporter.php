<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

class AttributeDataExporter
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function importAttributes(Asset $asset, array $data, ?string $locale): void
    {
        $workspaceId = $asset->getWorkspaceId();
        $repo = $this->em->getRepository(AttributeDefinition::class);

        foreach ($data as $key => $value) {
            $attributeDefinition = $repo->findOneBy([
                'workspace' => $workspaceId,
                'slug' => $key,
            ]);

            if (!$attributeDefinition instanceof AttributeDefinition) {
                continue;
            }

            if ($attributeDefinition->isMultiple()) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $this->createAttribute($asset, $attributeDefinition, $v, $locale);
                    }
                }
            } elseif (is_scalar($value)) {
                $this->createAttribute($asset, $attributeDefinition, $value, $locale);
            }
        }
    }

    private function createAttribute(Asset $asset, AttributeDefinition $definition, $value, ?string $locale): void
    {
        if (null === $value) {
            return;
        }
        $value = (string) $value;

        $attribute = new Attribute();
        $attribute->setAsset($asset);
        $attribute->setDefinition($definition);
        $attribute->setOrigin(Attribute::ORIGIN_HUMAN);
        $attribute->setValue($value);
        $attribute->setLocale($locale);

        $this->em->persist($attribute);
    }
}
