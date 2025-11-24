<?php

declare(strict_types=1);

namespace App\Integration\Matomo;

use Alchemy\Workflow\Executor\RunContext;
use App\Api\Model\Input\Attribute\AssetAttributeBatchUpdateInput;
use App\Api\Model\Input\Attribute\AttributeActionInput;
use App\Attribute\BatchAttributeManager;
use App\Entity\Core\Attribute;
use App\Integration\AbstractIntegrationAction;
use App\Integration\IfActionInterface;
use App\Repository\Core\AttributeDefinitionRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MatomoAction extends AbstractIntegrationAction implements IfActionInterface
{
    public function __construct(
        private readonly HttpClientInterface $matomoClient,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly BatchAttributeManager $batchAttributeManager,
        private string $matomoSiteId,
        private string $matomoAuthToken,
    ) {
    }

    public function doHandle(RunContext $context): void
    {
        $asset = $this->getAsset($context);
        $config = $this->getIntegrationConfig($context);

        $attrDef = $this->attributeDefinitionRepository
                ->getAttributeDefinitionBySlug($asset->getWorkspaceId(), $config['attribute'])
                    ?? throw new \InvalidArgumentException(sprintf('Attribute definition slug "%s" not found in workspace "%s"', $config['attribute'], $asset->getWorkspaceId()));

        $response = $this->matomoClient->request('GET', '/', [
            'query' => [
                'module' => 'API',
                'idSite' => $this->matomoSiteId,
                'method' => 'Contents.getContentNames',
                'format' => 'JSON',
                'token_auth' => $this->matomoAuthToken,
                'date' => '2025-11-01,'.date('Y-m-d'),
                'period' => 'range',
                'label' => $asset->getTrackingID(),
            ],
        ]);

        $stats = $response->toArray();

        unset($stats[0]['idsubdatatable']);
        unset($stats[0]['segment']);

        if (empty($stats)) {
            $attributeValue = '{}';
        } else {
            $attributeValue = json_encode($stats[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $input = new AssetAttributeBatchUpdateInput();

        $i = new AttributeActionInput();
        $i->definitionId = $attrDef->getId();
        $i->origin = Attribute::ORIGIN_MACHINE;
        $i->originVendor = MatomoIntegration::getName();
        $i->value = $attributeValue;

        $input->actions[] = $i;

        try {
            $this->batchAttributeManager->handleBatch(
                $asset->getWorkspaceId(),
                [$asset->getId()],
                $input,
                null
            );
        } catch (BadRequestHttpException $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        }

    }
}
