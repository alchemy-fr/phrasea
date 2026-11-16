<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use App\Attribute\AttributeInterface;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Tests\Functional\AbstractSearchTestCase;
use Elastica\Query\Term;

/**
 * The attribute fields of the asset index, shared by every workspace, are mapped dynamically:
 * past the field limit, an asset must still be indexed, its permissions above all.
 */
class AssetIndexFieldLimitTest extends AbstractSearchTestCase
{
    public function testTheIndexBoundsItsFieldsWithoutRejectingDocuments(): void
    {
        self::bootKernel();
        $settings = self::$documentIndices['asset']->getSettings();

        $this->assertSame('5000', $settings->get('mapping.total_fields.limit'));
        $this->assertTrue($settings->getBool('mapping.total_fields.ignore_dynamic_beyond_limit'));
    }

    public function testAnAssetBringingFieldsBeyondTheLimitIsStillIndexedWithItsPermissions(): void
    {
        // Booting populates the indices, recreating them with the configured settings
        self::bootKernel();
        $index = self::$documentIndices['asset'];
        // No new field may be mapped any more
        $index->setSettings(['index.mapping.total_fields.limit' => 1]);
        $this->assertSame('1', $index->getSettings()->get('mapping.total_fields.limit'));

        $definition = $this->createAttributeDefinition([
            'name' => 'Overflow',
            'slug' => 'overflow',
        ]);
        $asset = $this->createAsset([
            'name' => 'Beyond the limit',
            'ownerId' => 'another_owner',
            'attributes' => [
                ['definition' => $definition, 'value' => 'Unmapped value'],
            ],
        ]);
        $this->grantUserOnObject(KeycloakClientTestMock::USER_UID, $asset, PermissionInterface::VIEW);
        self::forceNewEntitiesToBeIndexed();
        self::waitForESIndex('asset');

        $this->assertSame(
            [$asset->getId()],
            array_map(
                fn ($result): string => (string) $result->getId(),
                $index->search(new Term(['users' => KeycloakClientTestMock::USER_UID]))->getResults(),
            ),
            'the document and its permissions are indexed',
        );

        /** @var FieldNameResolver $fieldNameResolver */
        $fieldNameResolver = self::getService(FieldNameResolver::class);
        $fieldName = $fieldNameResolver->getFieldNameFromDefinition($definition);
        $attributesMapping = $index->getMapping()['properties'][AttributeInterface::ATTRIBUTES_FIELD]['properties'] ?? [];
        $this->assertArrayNotHasKey(
            $fieldName,
            $attributesMapping[AttributeInterface::NO_LOCALE]['properties'] ?? [],
            'the new attribute field is left unmapped',
        );
    }
}
