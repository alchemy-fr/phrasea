<?php

declare(strict_types=1);

namespace App\Notification;

use Alchemy\NotifierBundle\Manager\NotifierManager;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\IntegrationRegistry;
use App\Service\ErrorDisableHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExceptionNotifier
{
    public function __construct(
        private NotifierManager $notifier,
        private ErrorDisableHandler $errorDisableHandler,
        // Lazy: the integrations it instantiates may themselves depend on this notifier
        #[Autowire(lazy: true)]
        private IntegrationRegistry $integrationRegistry,
        #[Autowire(param: 'alchemy_notifier.client_url')]
        private string $clientUrl = '',
    ) {
    }

    public function notifyException(UserNotifyableException $exception): void
    {
        if ($exception instanceof EntityDisableNotifyableException) {
            if (!$exception->getEntity()->isEnabled()) {
                return;
            }

            $this->describeEntity($exception);
            $this->errorDisableHandler->handleError($exception->getEntity(), $exception);
        }

        $payload = [
            'subject' => $exception->getSubject(),
            'message' => $exception->getMessage(),
            'details' => $this->getDetails($exception),
        ];
        if (null !== $uri = $exception->getUri()) {
            $payload['url'] = $uri;
            if (null !== $action = $exception->getAction()) {
                $payload['action'] = $action;
            }
        }

        foreach ($exception->getSubscribers() as $subscriber) {
            $this->notifier->notifyUser($subscriber, $exception->getNotificationId(), $payload);
        }
    }

    /**
     * A list rather than a map: the order is kept once the payload is stored as JSON.
     *
     * @return list<array{key: string, value: string}>
     */
    private function getDetails(UserNotifyableException $exception): array
    {
        $details = [];
        $instance = rtrim($this->clientUrl, '/');
        if ('' !== $instance) {
            $details['instance'] = $instance;
        }
        $details = array_merge($details, $exception->getDetails());

        return array_map(
            static fn (string $key, string $value): array => ['key' => $key, 'value' => $value],
            array_keys($details),
            array_values($details),
        );
    }

    private function describeEntity(EntityDisableNotifyableException $exception): void
    {
        $entity = $exception->getEntity();
        $workspace = $entity->getWorkspace();
        $exception->addDetail('workspace', $workspace?->getName());

        $uri = null;
        $action = null;
        if ($entity instanceof WorkspaceIntegration) {
            $type = $entity->getIntegration();
            $exception->addDetail('integration', $entity->getName() ?? $type);
            if (null !== $type) {
                $integration = $this->integrationRegistry->getIntegration($type);
                $exception->addDetail('integration_type', null !== $integration ? $integration::getDisplayName() : $type);
            }

            $uri = null !== $workspace
                ? sprintf('/workspaces/%s/manage/integrations/%s', $workspace->getId(), $entity->getId())
                : sprintf('/admin/integrations/%s', $entity->getId());
            $action = 'edit_integration';
        } elseif ($entity instanceof AttributeDefinition) {
            $exception->addDetail('attribute_definition', $entity->getName());
            if (null !== $workspace) {
                $uri = sprintf('/workspaces/%s/manage/attributes/%s', $workspace->getId(), $entity->getId());
                $action = 'edit_attribute_definition';
            }
        }

        if (null === $exception->getUri() && null !== $uri) {
            $exception->setUri($uri);
            $exception->setAction($action);
        }
    }
}
