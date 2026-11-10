<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository\Core;

use App\Entity\Core\Attribute;
use App\Repository\Core\AttributeRepository;
use App\Tests\Functional\AbstractDataboxTestCase;

class AttributeRepositoryTest extends AbstractDataboxTestCase
{
    public function testCachedAssetAttributesAreServedFromThePrefetch(): void
    {
        $em = self::getEntityManager();
        $definition = $this->createAttributeDefinition(['name' => 'Title', 'multiple' => true]);
        $asset1 = $this->createAsset(['attributes' => [
            ['definition' => $definition, 'value' => 'A', 'position' => 0],
            ['definition' => $definition, 'value' => 'B', 'position' => 1],
        ]]);
        $asset2 = $this->createAsset(['attributes' => [
            ['definition' => $definition, 'value' => 'C'],
        ]]);
        $em->clear();

        /** @var AttributeRepository $repository */
        $repository = $em->getRepository(Attribute::class);
        $repository->prefetchAssetAttributes([$asset1->getId(), $asset2->getId()]);

        // Once the rows are gone, only the prefetched cache can still answer
        $em->createQuery('DELETE FROM '.Attribute::class.' a')->execute();

        $values = fn (string $assetId): array => array_map(fn (Attribute $a): ?string => $a->getValue(), array_values($repository->getCachedAssetAttributes($assetId)));
        $this->assertSame(['A', 'B'], $values($asset1->getId()));
        $this->assertSame(['C'], $values($asset2->getId()));

        $repository->resetAssetCache($asset1);
        $this->assertSame([], $values($asset1->getId()));
        $this->assertSame(['C'], $values($asset2->getId()));
    }

    public function testThePrefetchWithoutEmbeddingsLoadsThemOnlyWhenRead(): void
    {
        $em = self::getEntityManager();
        $definition = $this->createAttributeDefinition(['name' => 'Faces', 'multiple' => true]);
        $asset = $this->createAsset(['attributes' => [
            ['definition' => $definition, 'value' => 'Alice'],
        ]]);
        $vector = array_fill(0, 512, 0.1);
        $attribute = $em->getRepository(Attribute::class)->findOneBy(['asset' => $asset->getId()]);
        $this->assertInstanceOf(Attribute::class, $attribute);
        $attribute->setEmbedding(['m' => 't', 'v' => $vector]);
        $em->flush();
        $em->clear();

        /** @var AttributeRepository $repository */
        $repository = $em->getRepository(Attribute::class);
        $repository->prefetchAssetAttributes([$asset->getId()], withEmbeddings: false);

        $attributes = array_values($repository->getCachedAssetAttributes($asset->getId()));
        $this->assertCount(1, $attributes);
        $attribute = $attributes[0];
        $this->assertSame('Alice', $attribute->getValue());
        $this->assertSame($asset->getId(), $attribute->getAsset()->getId());
        $this->assertSame($definition->getId(), $attribute->getDefinition()->getId());

        $embedding = new \ReflectionProperty(Attribute::class, 'embedding');
        $this->assertTrue($embedding->isLazy($attribute), 'the embedding is not loaded by the prefetch');

        $this->assertSame(['m' => 't', 'v' => $vector], $attribute->getEmbedding());
        $this->assertFalse($embedding->isLazy($attribute));
        $this->assertSame('Alice', $attribute->getValue());
    }
}
