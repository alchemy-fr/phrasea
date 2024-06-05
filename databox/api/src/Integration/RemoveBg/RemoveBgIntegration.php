<?php

declare(strict_types=1);

namespace App\Integration\RemoveBg;

use Alchemy\StorageBundle\Util\FileUtil;
use Alchemy\Workflow\Model\Workflow;
use App\Entity\Core\File;
use App\Integration\AbstractIntegration;
use App\Integration\Action\FileActionsTrait;
use App\Integration\ActionsIntegrationInterface;
use App\Integration\IntegrationConfig;
use App\Integration\PusherTrait;
use App\Integration\RemoveBg\Message\RemoveBgCall;
use App\Integration\WorkflowHelper;
use App\Integration\WorkflowIntegrationInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoveBgIntegration extends AbstractIntegration implements WorkflowIntegrationInterface, ActionsIntegrationInterface
{
    use PusherTrait;
    use FileActionsTrait;
    private const ACTION_PROCESS = 'process';

    public function __construct(
        private readonly RemoveBgProcessor $removeBgProcessor,
        private readonly MessageBusInterface $bus,
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

    public function handleAction(string $action, Request $request, IntegrationConfig $config): ?Response
    {
        $file = $this->getFile($request);

        switch ($action) {
            case self::ACTION_PROCESS:
                $this->bus->dispatch(new RemoveBgCall($file->getId(), $config->getIntegrationId()));
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported action "%s"', $action));
        }

        return null;
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
