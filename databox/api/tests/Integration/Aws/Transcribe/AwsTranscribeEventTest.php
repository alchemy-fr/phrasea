<?php

declare(strict_types=1);

namespace App\Tests\Integration\Aws\Transcribe;

use Alchemy\TestBundle\Helper\FixturesTrait;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationData;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Aws\Transcribe\AwsTranscribeIntegration;
use App\Integration\Aws\Transcribe\Consumer\AwsTranscribeEventHandler;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AwsTranscribeEventTest extends ApiTestCase
{
    use TestServicesTrait;
    use FixturesTrait;

    public function testSubscriptionUrlWillBeConfirmed(): void
    {
        self::enableFixtures();

        $apiClient = static::createClient();
        $apiClient->disableReboot();

        $wsIntegration = $this->createIntegration();

        $payload = <<<EOL
{
  "Type": "SubscriptionConfirmation",
  "MessageId": "425ad1a8-55ce-4186-b15c-01ef9e54301f",
  "Token": "2336412f37fb687f5d51e6e2425c464de4cb6b4581f9fe4b119f4cfe62b83c8530f3f564ddd9336f77a221839450831c26079f18bb8605467cb63af9a1ec59c6d9e494f18a98e524aa274c25cf41a7d5ee6924c6999d0d7fbd6f634d269ac3794dc3c3dec042271774e59f9d9cff49ac",
  "TopicArn": "arn:aws:sns:eu-west-3:122649456891:test",
  "Message": "You have chosen to subscribe to the topic arn:aws:sns:eu-west-3:122649456891:test.\\nTo confirm the subscription, visit the SubscribeURL included in this message.",
  "SubscribeURL": "https://sns.eu-west-3.amazonaws.com/?Action=ConfirmSubscription&TopicArn=arn:aws:sns:eu-west-3:122649456891:test&Token=2336412f37fb687f5d51e6e2425c464de4cb6b4581f9fe4b119f4cfe62b83c8530f3f564ddd9336f77a221839450831c26079f18bb8605467cb63af9a1ec59c6d9e494f18a98e524aa274c25cf41a7d5ee6924c6999d0d7fbd6f634d269ac3794dc3c3dec042271774e59f9d9cff49ac",
  "Timestamp": "2023-01-03T09:08:29.319Z",
  "SignatureVersion": "1",
  "Signature": "uUif47zkM5Bet6Rg7IsgmTYQa23OfZtFICOg5KOlh9ZuiJQoi21e/2+jcm58P8UBzfh1zwfy23XxeeMlRDBvYfxxjJgkfLhDggMZHyCkX7l8796ZCZqO0KrTxU5CDdrcsF+qTx51tlmFIBOKGsUNEaWW29CEWBR0Wk6dLH+KgalKFUWyWC1BlMR878Nowem4ZgFHT2Vu5DOWR2pU3OYXvLM4r0xdkoFkZLi6NmxH1zybJgyUsndU9xHUMTQnBH7fXHU1GtDS3Jk60CcPRDUwA3nav+tP07WnkV9/yb5Kslm2bJ6oMEZAajCDUPr6Jnop2Atd0RNc4iES8+JqQ/0tHA==",
  "SigningCertURL": "https://sns.eu-west-3.amazonaws.com/SimpleNotificationService-56e67fcb41f6fec09b0196692625d385.pem"
}
EOL;

        $this->triggerEvent($apiClient, $wsIntegration->getId(), $payload);
        $this->assertResponseStatusCodeSame(200);
        $this->assertHasData($wsIntegration->getId(), AwsTranscribeEventHandler::DATA_EVENT_MESSAGE, 1);
    }

    private function triggerEvent(Client $apiClient, string $integrationId, string $payload): ResponseInterface
    {
        return $apiClient->request(
            'POST',
            sprintf('/integrations/aws-transcribe/%s/events', $integrationId), [
            'body' => $payload,
        ]);
    }

    private function createIntegration(): WorkspaceIntegration
    {
        self::enableFixtures();
        $em = $this->getEntityManager();

        /** @var Workspace $workspace */
        $workspace = $em->getRepository(Workspace::class)->findOneBy([
            'slug' => 'test-workspace',
        ]);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setTitle('AWS Transcribe');
        $integration->setIntegration(AwsTranscribeIntegration::getName());
        $integration->setConfig([
            'accessKeyId' => '42',
            'accessKeySecret' => '42',
        ]);
        $em->persist($integration);
        $em->flush();

        return $integration;
    }

    private function assertHasData(string $integrationId, string $name, int $expectedCount): void
    {
        $em = $this->getEntityManager();
        $results = $em->getRepository(IntegrationData::class)
            ->findBy([
                'integration' => $integrationId,
                'name' => $name,
            ])
        ;

        $this->assertEquals($expectedCount, count($results));
    }

    protected static function bootKernel(array $options = []): KernelInterface
    {
        if (static::$kernel) {
            return static::$kernel;
        }
        static::bootKernelWithFixtures($options);

        return static::$kernel;
    }

    public function testNotificationWillBeHandled(): void
    {
        self::enableFixtures();

        $eventProducer = self::getService(EventProducer::class);
        $eventProducer->interceptEvents();

        $apiClient = static::createClient();
        $apiClient->disableReboot();

        $wsIntegration = $this->createIntegration();

        $payload = <<<EOL
{
  "Type": "Notification",
  "MessageId": "673bff65-e713-54b5-86bb-a34bbce0085d",
  "TopicArn": "arn:aws:sns:eu-west-3:122649456891:test",
  "Message": "{\"version\":\"0\",\"id\":\"d755e67c-5c9c-c8f3-8093-5c147166c776\",\"detail-type\":\"Transcribe Job State Change\",\"source\":\"aws.transcribe\",\"account\":\"122649456891\",\"time\":\"2023-01-03T16:37:06Z\",\"region\":\"eu-west-3\",\"resources\":[],\"detail\":{\"TranscriptionJobName\":\"f314ff7e597268634664f7bb6bb5d2a1\",\"TranscriptionJobStatus\":\"COMPLETED\"}}",
  "Timestamp": "2023-01-03T16:37:07.096Z",
  "SignatureVersion": "1",
  "Signature": "R0JqKN78zX25eWpJCEX/31IOQppG4xvkJCFu0ZknpES4oRxuEuA2ZPV8j/m45NnBTd4mIdGnUau5i6l+TdFcE0arVsURnuCDtKpVpxaNEtNCmnp29IyIHMRCT9X7ltCcHE9aiWNtE1a29ThHhGsyxk0J0YGWio79mABQbRB153sYGAgcFP3Eu7Di9ez7wDsESo64akgFnBW2nYmwEOX0tH1rQS1pUsE3nDnwrLYMEN3+nW0/aIkJSm2HswTufniuvaTKNf8mM9w8vDQd3NJsrhAgiiR3+kU4X4ZPgiq5Cb99yxDdiDQic63lw0HbAC/3sFVIFE2pbqnbp0kBNaNgMQ==",
  "SigningCertURL": "https://sns.eu-west-3.amazonaws.com/SimpleNotificationService-56e67fcb41f6fec09b0196692625d385.pem",
  "UnsubscribeURL": "https://sns.eu-west-3.amazonaws.com/?Action=Unsubscribe&SubscriptionArn=arn:aws:sns:eu-west-3:122649456891:test:a3a34216-da26-4feb-aef2-84890f73a989"
}
EOL;

        $this->triggerEvent($apiClient, $wsIntegration->getId(), $payload);
        $this->assertResponseStatusCodeSame(200);

        $eventMessage = $eventProducer->shiftEvent();
        self::assertEquals(AwsTranscribeEventHandler::EVENT, $eventMessage->getType());
        $this->consumeEvent($eventMessage);

        $this->assertHasData($wsIntegration->getId(), AwsTranscribeEventHandler::DATA_EVENT_MESSAGE, 1);
    }
}
