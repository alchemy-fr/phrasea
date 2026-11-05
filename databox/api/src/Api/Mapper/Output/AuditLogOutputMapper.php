<?php

declare(strict_types=1);

namespace App\Api\Mapper\Output;

use App\Api\Model\Output\AuditLogOutput;
use App\Entity\Log\AuditLog;
use App\Service\Log\AuditLogManager;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(index: AuditLogOutput::class)]
final readonly class AuditLogOutputMapper implements OutputMapperInterface
{
    public function __construct(
        private AuditLogManager $auditLogManager,
    ) {
    }

    public function supports(object $data): bool
    {
        return $data instanceof AuditLog;
    }

    /**
     * @param AuditLog $data
     */
    public function map(object $data, array $context = []): object
    {
        $output = new AuditLogOutput();
        $output->setId($data->getId());
        $output->date = $data->getDate();
        $output->action = $data->getAction()->getKey();
        $output->actorType = $data->getActorType()->getKey();
        // The username kept in the entry: the user may have been removed since
        $output->user = null !== $data->getUserId() ? [
            'id' => $data->getUserId(),
            'username' => $data->getUserName(),
        ] : null;
        $output->impersonatorId = $data->getImpersonatorId();
        $output->clientId = $data->getClientId();
        $output->ip = $data->getIp();
        $output->objectType = $this->auditLogManager->getObjectTypeKeyOf($data->getObjectType());
        $output->objectId = $data->getObjectId();
        $output->objectLabel = $data->getObjectLabel();
        $output->workspaceId = $data->getWorkspaceId();
        $output->correlationId = $data->getCorrelationId();
        $output->data = $data->getData();
        $output->meta = $data->getMeta();

        return $output;
    }
}
