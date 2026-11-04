<?php

declare(strict_types=1);

namespace App\Tests\Functional\Webhook;

use Alchemy\MessengerBundle\Transport\TestTransport;
use Alchemy\WebhookBundle\Consumer\SerializeObject;
use Alchemy\WebhookBundle\Consumer\SerializeObjectHandler;
use Alchemy\WebhookBundle\Consumer\WebhookTriggerMessage;
use Alchemy\WebhookBundle\Doctrine\EntitySerializer;
use Alchemy\WebhookBundle\Doctrine\Listener\EntityListener;
use Alchemy\WebhookBundle\Entity\Webhook;
use App\Entity\Core\AssetRendition;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Model\AssetTypeEnum;
use App\Tests\Functional\AbstractDataboxTestCase;

class SerializeObjectHandlerTest extends AbstractDataboxTestCase
{
    public function testManyToOneAssociationsAreSerialized(): void
    {
        self::bootKernel();
        $em = self::getEntityManager();
        // The listener would load (and cache) the active webhooks before this one is inserted
        EntityListener::$enabled = false;

        $webhook = new Webhook();
        $webhook->setUrl('https://webhook.test/hook');
        $webhook->setEvents(['rendition:create']);
        $em->persist($webhook);

        $asset = $this->createAsset(['no_flush' => true]);
        $workspace = $asset->getWorkspace();

        $policy = new RenditionPolicy();
        $policy->setWorkspace($workspace);
        $policy->setName('policy');
        $policy->setPublic(false);
        $policy->setEditable(false);
        $em->persist($policy);

        $definition = new RenditionDefinition();
        $definition->setWorkspace($workspace);
        $definition->setPolicy($policy);
        $definition->setName('preview');
        $definition->setTarget(AssetTypeEnum::Both);
        $em->persist($definition);

        $rendition = new AssetRendition();
        $rendition->setAsset($asset);
        $rendition->setDefinition($definition);
        $em->persist($rendition);
        try {
            $em->flush();
        } finally {
            EntityListener::$enabled = true;
        }

        /** @var EntitySerializer $entitySerializer */
        $entitySerializer = self::getContainer()->get(EntitySerializer::class);
        $data = $entitySerializer->convertToDatabaseValue(
            AssetRendition::class,
            $em->getUnitOfWork()->getOriginalEntityData($rendition),
        );

        /** @var TestTransport $p2 */
        $p2 = self::getContainer()->get('messenger.transport.p2');
        $sent = $p2->intercept();

        /** @var SerializeObjectHandler $handler */
        $handler = self::getContainer()->get(SerializeObjectHandler::class);
        $handler(new SerializeObject(AssetRendition::class, 'rendition:create', $data));

        $messages = array_values(array_filter(
            array_map(static fn ($e) => $e->getMessage(), $sent->getSent()),
            static fn ($m): bool => $m instanceof WebhookTriggerMessage,
        ));
        $this->assertCount(1, $messages);
        $payload = $messages[0]->getPayload()['data'];
        $this->assertSame($rendition->getId(), $payload['id']);
        $this->assertStringContainsString($asset->getId(), json_encode($payload['asset']));
    }
}
