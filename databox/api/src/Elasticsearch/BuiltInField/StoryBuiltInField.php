<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Api\Traits\UserLocaleTrait;
use App\Asset\Attribute\AssetTitleResolver;
use App\Attribute\Type\TagAttributeType;
use App\Entity\Core\Asset;
use Doctrine\ORM\EntityManagerInterface;

final class StoryBuiltInField extends AbstractEntityBuiltInField
{
    use UserLocaleTrait;

    public function __construct(
        private AssetTitleResolver $assetTitleResolver,
        private EntityManagerInterface $em,
    ) {
        parent::__construct($em);
    }

    /**
     * @param Asset $value
     */
    public function resolveItem($value): array
    {
        return [
            'title' => $this->resolveLabel($value),
        ];
    }

    /**
     * @param Asset $value
     */
    protected function resolveLabel($value): string
    {
        return $this->assetTitleResolver->resolveTitleWithoutIndex($value, $this->getPreferredLocales($value->getWorkspace()));
    }

    protected function getEntityClass(): string
    {
        return Asset::class;
    }

    public function getFieldName(): string
    {
        return 'stories';
    }

    public static function getKey(): string
    {
        return '@story';
    }

    public function getType(): string
    {
        return TagAttributeType::NAME;
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getTags();
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'stories';
    }
}
