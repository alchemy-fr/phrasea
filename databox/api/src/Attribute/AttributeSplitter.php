<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AttributeSplitter
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function splitAttributes(AttributeDefinition $attributeDefinition, string $delimiter): void
    {
        if (!$attributeDefinition->isMultiple()) {
            throw new \InvalidArgumentException(sprintf('AttributeDefinition "%s" is not multi-valued', $attributeDefinition->getName()));
        }

        /** @var Attribute[] $attributes */
        $attributes = $this->em->createQueryBuilder()
            ->select('a.id')
            ->addSelect('a.value')
            ->from(Attribute::class, 'a')
            ->andWhere('a.definition = :def')
            ->setParameter('def', $attributeDefinition->getId())
            ->getQuery()
            ->toIterable();

        foreach ($attributes as $attr) {
            $value = $attr['value'];
            if (str_contains((string) $value, $delimiter)) {
                $attribute = $this->em->find(Attribute::class, $attr['id']);

                $parts = explode($delimiter, $attribute->getValue());
                foreach ($parts as $p) {
                    $a = new Attribute();
                    $a->setDefinition($attribute->getDefinition());
                    $a->setLocale($attribute->getLocale());
                    $a->setAsset($attribute->getAsset());
                    $a->setConfidence($attribute->getConfidence());
                    $a->setAssetAnnotations($attribute->getAssetAnnotations());
                    $a->setOrigin($attribute->getOrigin());
                    $a->setStatus($attribute->getStatus());
                    $a->setOriginUserId($attribute->getOriginUserId());
                    $a->setOriginVendor($attribute->getOriginVendor());
                    $a->setOriginVendorContext($attribute->getOriginVendorContext());
                    $a->setTranslationId($attribute->getTranslationId());
                    $a->setValue($p);

                    $this->em->persist($a);
                }
                $this->em->remove($attribute);

                $this->em->flush();
                $this->em->clear();
            }
        }
    }
}
