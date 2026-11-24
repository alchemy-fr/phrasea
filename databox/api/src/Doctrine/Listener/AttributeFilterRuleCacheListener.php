<?php

declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Core\AttributeFilterRule;
use App\Entity\Core\AttributeFilterRuleTarget;
use App\Security\AssetAttributeFilterChecker;
use App\Security\AttributeFilterManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Forgets the rules and the read decisions cached for the request (or the message) as soon as a rule
 * or one of its targets is written, whatever writes it (API, admin, workspace template, workspace deletion…).
 *
 * Entity listener, lazy: the checker and its dependencies are only built when a rule is written.
 */
#[AsEntityListener(event: Events::postPersist, method: 'clearCaches', lazy: true, entity: AttributeFilterRule::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'clearCaches', lazy: true, entity: AttributeFilterRule::class)]
#[AsEntityListener(event: Events::postRemove, method: 'clearCaches', lazy: true, entity: AttributeFilterRule::class)]
#[AsEntityListener(event: Events::postPersist, method: 'clearCaches', lazy: true, entity: AttributeFilterRuleTarget::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'clearCaches', lazy: true, entity: AttributeFilterRuleTarget::class)]
#[AsEntityListener(event: Events::postRemove, method: 'clearCaches', lazy: true, entity: AttributeFilterRuleTarget::class)]
final readonly class AttributeFilterRuleCacheListener
{
    public function __construct(
        private AttributeFilterManager $attributeFilterManager,
        private AssetAttributeFilterChecker $assetAttributeFilterChecker,
    ) {
    }

    public function clearCaches(): void
    {
        $this->attributeFilterManager->clearCache();
        $this->assetAttributeFilterChecker->clearCache();
    }
}
