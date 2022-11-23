<?php

declare(strict_types=1);

namespace App\Api\Filter\Group;

use App\Attribute\Type\AttributeTypeInterface;
use App\Elasticsearch\Mapping\FieldNameResolver;
use App\Entity\Core\Asset;
use Pagerfanta\Adapter\AdapterInterface;

class AssetGroupAdapter implements AdapterInterface
{
    private string $property;
    private AdapterInterface $decorated;
    private FieldNameResolver $fieldNameResolver;

    public function __construct(string $property, FieldNameResolver $fieldNameResolver, AdapterInterface $decorated)
    {
        $this->property = $property;
        $this->decorated = $decorated;
        $this->fieldNameResolver = $fieldNameResolver;
    }

    public function getNbResults()
    {
        return $this->decorated->getNbResults();
    }

    public function getSlice($offset, $length)
    {
        $arr = $this->decorated->getSlice($offset, $length);

        $groupContext = null;

        /** @var AttributeTypeInterface $type */
        ['field' => $field, 'type' => $type] = $this->fieldNameResolver->getFieldFromName($this->property);

        return array_map(function (Asset $asset) use (&$groupContext, $type): Asset {
            return $asset;
        }, $arr);
    }
}
