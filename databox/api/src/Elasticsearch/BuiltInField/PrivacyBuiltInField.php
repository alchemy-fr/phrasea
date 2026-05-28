<?php

declare(strict_types=1);

namespace App\Elasticsearch\BuiltInField;

use App\Attribute\Type\PrivacyAttributeType;
use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PrivacyBuiltInField extends AbstractLabelledBuiltInField
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param int $value
     */
    public function resolveLabel($value): string
    {
        return $this->translator->trans(sprintf('privacy.%s', WorkspaceItemPrivacyInterface::KEYS[$value]));
    }

    public function getType(): string
    {
        return PrivacyAttributeType::NAME;
    }

    protected function resolveKey($value): string
    {
        return (string) $value;
    }

    public static function getName(): string
    {
        return 'privacy';
    }

    public static function getKey(): string
    {
        return '@privacy';
    }

    public function getValueFromAsset(Asset $asset): mixed
    {
        return $asset->getPrivacy();
    }

    protected function getAggregationTranslationKey(): string
    {
        return 'privacy';
    }

    protected function getAggregationSize(): int
    {
        return count(WorkspaceItemPrivacyInterface::KEYS);
    }
}
