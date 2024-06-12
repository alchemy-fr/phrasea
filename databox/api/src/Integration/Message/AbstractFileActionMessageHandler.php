<?php

namespace App\Integration\Message;

use Alchemy\CoreBundle\Util\DoctrineUtil;
use App\Entity\Core\File;
use App\Integration\IntegrationConfig;
use App\Integration\IntegrationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractFileActionMessageHandler
{
    private EntityManagerInterface $em;
    private IntegrationManager $integrationManager;

    #[Required]
    public function setEm(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setIntegrationManager(IntegrationManager $integrationManager): void
    {
        $this->integrationManager = $integrationManager;
    }

    protected function getFile(AbstractFileActionMessage $message): File
    {
        return DoctrineUtil::findStrict($this->em, File::class, $message->getFileId());
    }

    protected function getConfig(AbstractFileActionMessage $message): IntegrationConfig
    {
        $workspaceIntegration = $this->integrationManager->loadIntegration($message->getIntegrationId());

        return $this->integrationManager->getIntegrationConfiguration($workspaceIntegration);
    }
}
