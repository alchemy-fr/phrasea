<?php

declare(strict_types=1);

namespace App\OperationTask\Task;

use App\OperationTask\OperationTaskInterface;
use App\OperationTask\RunContext;
use App\Service\Asset\WorkspaceAssetIngester;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsTaggedItem(index: self::NAME)]
final readonly class IngestWorkspaceAssetsTask implements OperationTaskInterface
{
    public const string NAME = 'ingest_workspace_assets';

    public function __construct(
        private WorkspaceAssetIngester $workspaceAssetIngester,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function validate(array $payload): void
    {
        if (empty($payload['workspaceId'] ?? null)) {
            throw new BadRequestHttpException('workspaceId is required');
        }
    }

    public function handle(array $payload, RunContext $context): void
    {
        $this->workspaceAssetIngester->ingestWorkspaceAssets(
            $payload['workspaceId'],
            $context,
        );
    }
}
