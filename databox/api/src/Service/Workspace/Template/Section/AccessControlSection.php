<?php

declare(strict_types=1);

namespace App\Service\Workspace\Template\Section;

use Alchemy\AclBundle\AclObjectInterface;
use Alchemy\AclBundle\Security\PermissionManager;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Template\AssetDataTemplate;
use App\Service\Workspace\Template\TemplateImportContext;
use App\Service\Workspace\Template\WorkspaceTemplateOptions;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * ACEs of the workspace and of its configuration objects.
 * Imported last: the objects they apply to must all be registered.
 */
#[AsTaggedItem(priority: 10)]
final class AccessControlSection extends AbstractTemplateSection
{
    public function __construct(
        EntityManagerInterface $em,
        LoggerInterface $logger,
        private readonly PermissionManager $permissionManager,
    ) {
        parent::__construct($em, $logger);
    }

    public static function getKey(): string
    {
        return 'AccessControl';
    }

    public function export(Workspace $workspace, WorkspaceTemplateOptions $options): array
    {
        if (!$options->withAccessControl) {
            return [];
        }

        /** @var AclObjectInterface[] $objects */
        $objects = [
            $workspace,
            ...$this->findByWorkspace(RenditionPolicy::class, $workspace),
            ...$this->findByWorkspace(AttributePolicy::class, $workspace),
            ...$this->findByWorkspace(WorkspaceIntegration::class, $workspace),
            ...$this->findByWorkspace(AssetDataTemplate::class, $workspace),
        ];

        $o = [];
        foreach ($objects as $object) {
            foreach ($this->permissionManager->getObjectAces($object) as $ace) {
                // skip the ACEs granted on every object of the type
                if ($ace->getObjectId() !== $object->getId() || null !== $ace->getParentId()) {
                    continue;
                }

                $o[] = [
                    'objectType' => $ace->getObjectType(),
                    'objectId' => $ace->getObjectId(),
                    'userType' => $ace->getUserType(),
                    'userId' => $ace->getUserId(),
                    'mask' => $ace->getMask(),
                    'metadata' => $ace->getMetadata(),
                ];
            }
        }

        return $o;
    }

    public function import(array $data, TemplateImportContext $context): void
    {
        foreach ($data as $item) {
            $objectId = $context->getNewId($item['objectId']);
            if (null === $objectId) {
                $this->logger->warning(sprintf('Unknown %s "%s" for ACE', $item['objectType'], $item['objectId']));
                continue;
            }

            $this->permissionManager->updateOrCreateAce(
                $item['userType'],
                $item['userId'],
                $item['objectType'],
                $objectId,
                $item['mask'],
                $item['metadata'] ?? [],
            );
        }
    }
}
