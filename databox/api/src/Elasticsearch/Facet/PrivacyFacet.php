<?php

declare(strict_types=1);

namespace App\Elasticsearch\Facet;

use App\Entity\Core\Asset;
use App\Entity\Core\WorkspaceItemPrivacyInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PrivacyFacet extends AbstractLabelledFacet
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

    protected function resolveKey($value): string
    {
        return (string) $value;
    }

    public function getFieldName(): string
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

    protected function getAggregationTitle(): string
    {
        return 'Privacy';
    }

    protected function getAggregationSize(): int
    {
        return count(WorkspaceItemPrivacyInterface::KEYS);
    }
}
