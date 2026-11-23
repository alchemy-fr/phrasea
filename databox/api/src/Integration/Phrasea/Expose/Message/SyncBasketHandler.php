<?php

declare(strict_types=1);

namespace App\Integration\Phrasea\Expose\Message;

use App\Automation\Exception\AutomationOwnerUnavailableException;
use App\Consumer\MessageDeduplication;
use App\Entity\Basket\Basket;
use App\Entity\Integration\IntegrationData;
use App\Integration\Auth\MissingIntegrationTokenException;
use App\Integration\IntegrationDataManager;
use App\Integration\Phrasea\Expose\ExposeIntegration;
use App\Integration\Phrasea\Expose\ExposeSynchronizer;
use App\Integration\PusherTrait;
use App\Notification\ExceptionNotifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class SyncBasketHandler
{
    use PusherTrait;

    public function __construct(
        private readonly IntegrationDataManager $integrationDataManager,
        private readonly ExposeSynchronizer $exposeSynchronizer,
        private readonly MessageBusInterface $bus,
        private readonly ExceptionNotifier $exceptionNotifier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncBasket $message): void
    {
        $integrationData = $this->integrationDataManager->getByIdTrusted($message->getId());
        try {
            $this->exposeSynchronizer->synchronize($integrationData);
        } catch (TooManyRequestsHttpException $e) {
            $delay = $e->getHeaders()['Retry-After'] ?? 300;

            $this->bus->dispatch(MessageDeduplication::coalesce($message, max(900, $delay + 60))->with(new DelayStamp($delay * 1000)));
        } catch (MissingIntegrationTokenException $e) {
            // The user must authenticate again: retrying would fail the same way.
            $this->abort($integrationData, 'no valid integration token', 'missing-token');
            $this->exceptionNotifier->notifyException($e);
        } catch (AutomationOwnerUnavailableException $e) {
            // The user is missing or disabled in the IdP: retrying would fail the same way.
            $this->abort($integrationData, 'user unavailable', 'user-unavailable', $e);
        }
    }

    private function abort(IntegrationData $integrationData, string $cause, string $reason, ?\Throwable $exception = null): void
    {
        $this->logger->warning(sprintf('Expose basket synchronization aborted: %s', $cause), [
            'integrationDataId' => $integrationData->getId(),
            'userId' => $integrationData->getUserId(),
        ] + ($exception ? ['exception' => $exception] : []));

        /** @var Basket $basket */
        $basket = $integrationData->getObject();
        $this->triggerBasketPush(ExposeIntegration::getName(), $basket, [
            'id' => $integrationData->getId(),
            'action' => 'sync-failed',
            'reason' => $reason,
        ], direct: true);
    }
}
