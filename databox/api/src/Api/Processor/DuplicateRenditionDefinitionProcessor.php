<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Core\RenditionDefinition;
use App\Security\Voter\AbstractVoter;
use App\Service\Duplicate\CopyNameGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a copy of a rendition definition in its workspace, without its key nor the asset renditions.
 */
final class DuplicateRenditionDefinitionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    private const int NAME_MAX_LENGTH = 80;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly CopyNameGenerator $copyNameGenerator,
    ) {
    }

    /**
     * @param RenditionDefinition $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RenditionDefinition
    {
        $repository = $this->em->getRepository(RenditionDefinition::class);

        $copy = new RenditionDefinition();
        $copy->setWorkspace($data->getWorkspace());
        $copy->setName($this->copyNameGenerator->generate(
            $data->getName(),
            fn (string $name): bool => null !== $repository->findOneBy([
                'workspace' => $data->getWorkspaceId(),
                'name' => $name,
            ]),
            self::NAME_MAX_LENGTH,
        ));
        $copy->setPolicy($data->getPolicy());
        $copy->setParent($data->getParent());
        $copy->setDefinition($data->getDefinition());
        $copy->setConfig($data->getConfig());
        $copy->setBuildMode($data->getBuildMode());
        $copy->setSubstitutable($data->isSubstitutable());
        $copy->setDownload($data->isDownload());
        $copy->setPriority($data->getPriority());
        $copy->setUseAsMain($data->isUseAsMain());
        $copy->setUseAsPreview($data->isUseAsPreview());
        $copy->setUseAsThumbnail($data->isUseAsThumbnail());
        $copy->setUseAsAnimatedThumbnail($data->isUseAsAnimatedThumbnail());
        $copy->setLabels($data->getLabels());
        $copy->setTarget($data->getTarget());

        // The translated names would name the original
        $translations = $data->getTranslations() ?? [];
        unset($translations[RenditionDefinition::TR_FIELD_NAME]);
        $copy->setTranslations($translations ?: null);

        $this->denyAccessUnlessGranted(AbstractVoter::CREATE, $copy);
        $this->validator->validate($copy);

        $this->em->persist($copy);

        $attributeDefinitions = $data->getWriteMetadataAttributes()->toArray();
        $copy->setWriteMetadataAttributes($attributeDefinitions);
        foreach ($attributeDefinitions as $attributeDefinition) {
            // AttributeDefinition owns the relation and is tracked with DEFERRED_EXPLICIT
            $this->em->persist($attributeDefinition);
        }

        $this->em->flush();

        return $copy;
    }
}
