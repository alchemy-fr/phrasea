<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Model\Workflow;
use App\Entity\Core\File;
use App\Integration\AbstractFileAction;
use App\Integration\IntegrationConfig;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoveBgIntegration extends AbstractFileAction implements WorkflowIntegrationInterface
{
    private const ACTION_PROCESS = 'process';

    public function __construct(
        private readonly RemoveBgProcessor $removeBgProcessor,
    ) {
    }

    public function buildConfiguration(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('apiKey')
                ->defaultValue('${REMOVE_BG_API_KEY}')
                ->cannotBeEmpty()
            ->end()
            ->booleanNode('processIncoming')
                ->defaultFalse()
            ->end()
        ;

        $builder->append($this->createBudgetLimitConfigNode(
            true,
            5,
            '1 day'
        ));
    }

    public function getWorkflowJobDefinitions(IntegrationConfig $config, Workflow $workflow): iterable
    {
        if ($config['processIncoming']) {
            yield WorkflowHelper::createIntegrationJob(
                $config,
                RemoveBgAction::class,
            );
        }
    }

    public function handleFileAction(string $action, Request $request, File $file, IntegrationConfig $config): ?Response
    {
        switch ($action) {
            case self::ACTION_PROCESS:
                $file = $this->removeBgProcessor->process($file, $config);

                // TODO websocket

                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }

        return null;
    }

    public function supportsFileActions(File $file, IntegrationConfig $config): bool
    {
        return FileUtil::isImageType($file->getType());
    }

    public static function getName(): string
    {
        return 'remove.bg';
    }

    public static function getTitle(): string
    {
        return 'Remove BG';
    }
}
