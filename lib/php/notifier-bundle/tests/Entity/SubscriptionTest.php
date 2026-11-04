<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Tests\Entity;

use Alchemy\NotifierBundle\Entity\Subscriber;
use Alchemy\NotifierBundle\Entity\Subscription;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    private const string USER_ID = '11111111-1111-1111-1111-111111111111';

    public function testSubscriptionScopedToAnObject(): void
    {
        $subscription = new Subscription($this->subscriber(), 'asset_added', 'collection', '42');

        self::assertSame('asset_added', $subscription->getEvent());
        self::assertSame('collection', $subscription->getObjectType());
        self::assertSame('42', $subscription->getObjectId());
    }

    public function testSubscriptionWithoutObjectIsGlobalToTheEvent(): void
    {
        $subscription = new Subscription($this->subscriber(), 'asset_added');

        self::assertSame('asset_added', $subscription->getEvent());
        self::assertNull($subscription->getObjectType());
        self::assertNull($subscription->getObjectId());
    }

    public function testEmptyEventThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Subscription($this->subscriber(), '');
    }

    private function subscriber(): Subscriber
    {
        return new Subscriber(self::USER_ID);
    }
}
