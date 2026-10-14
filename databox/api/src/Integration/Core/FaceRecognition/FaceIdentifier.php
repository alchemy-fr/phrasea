<?php

declare(strict_types=1);

namespace App\Integration\Core\FaceRecognition;

use App\Entity\Core\AssetFace;
use App\Integration\Core\FaceRecognition\Message\FaceRecognitionPropagate;
use App\Integration\IntegrationConfig;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Applies a user decision on the identity of a face, from the asset view or the faces page.
 */
final readonly class FaceIdentifier
{
    public function __construct(
        private FaceRecognitionAnalyzer $analyzer,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Sets (or clears, with a null/empty identity) the identity of a face on behalf of a user,
     * then propagates the decision to the similar faces of the workspace when the integration says so.
     *
     * @return array the faces summary of the asset
     */
    public function identify(AssetFace $face, ?string $identity, IntegrationConfig $config): array
    {
        $summary = $this->analyzer->identify($face, $identity, $config);

        if ($config['autoPropagate']) {
            $this->bus->dispatch(new FaceRecognitionPropagate($face->getId(), $config->getIntegrationId()));
        }

        return $summary;
    }
}
