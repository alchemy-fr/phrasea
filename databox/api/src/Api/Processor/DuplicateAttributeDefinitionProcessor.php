<?php

declare(strict_types=1);

namespace App\Api\Processor;

use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Core\AttributeDefinition;
use App\Security\Voter\AbstractVoter;
use App\Service\Duplicate\CopyNameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

use function Symfony\Component\String\u;

/**
 * Creates a copy of an attribute definition in its workspace, without its key nor the attribute values.
 */
final class DuplicateAttributeDefinitionProcessor implements ProcessorInterface
{
    use SecurityAwareTrait;

    private const int NAME_MAX_LENGTH = 100;
    private const int SLUG_MAX_LENGTH = 100;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly CopyNameGenerator $copyNameGenerator,
    ) {
    }

    /**
     * @param AttributeDefinition $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AttributeDefinition
    {
        $copy = new AttributeDefinition();
        $copy->setWorkspace($data->getWorkspace());
        $copy->setName($this->copyNameGenerator->generate(
            (string) $data->getName(),
            fn (string $name): bool => $this->isNameTaken($data, $name),
            self::NAME_MAX_LENGTH,
        ));
        $copy->setPolicy($data->getPolicy());
        $copy->setEntityList($data->getEntityList());
        $copy->setWriteMetadataRenditions($data->getWriteMetadataRenditions());
        $copy->setFileType($data->getFileType());
        $copy->setType($data->getType());
        $copy->setSearchable($data->isSearchable());
        $copy->setFacetEnabled($data->isFacetEnabled());
        $copy->setSortable($data->isSortable());
        $copy->setTranslatable($data->isTranslatable());
        $copy->setMultiple($data->isMultiple());
        $copy->setAllowInvalid($data->isAllowInvalid());
        $copy->setSuggest($data->isSuggest());
        $copy->setEditable($data->isEditable());
        $copy->setEnabled($data->isEnabled());
        $copy->setFillFromName($data->isFillFromName());
        $copy->setNamePriority($data->getNamePriority());
        $copy->setSearchBoost($data->getSearchBoost());
        $copy->setInitialValues($data->getInitialValues());
        $copy->setReadFromMetadata($data->getReadFromMetadata());
        $copy->setWriteMetadata($data->getWriteMetadata());
        $copy->setFallback($data->getFallback());
        $copy->setLabels($data->getLabels());
        $copy->setOptions($data->getOptions());
        $copy->setTarget($data->getTarget());
        $copy->setPosition($data->getPosition());

        // The translated names would name the original
        $translations = $data->getTranslations() ?? [];
        unset($translations[AttributeDefinition::TR_FIELD_NAME]);
        $copy->setTranslations($translations ?: null);

        $this->denyAccessUnlessGranted(AbstractVoter::CREATE, $copy);
        $this->validator->validate($copy);

        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }

    /**
     * The slug, generated from the name on insert, is unique in the workspace as well.
     */
    private function isNameTaken(AttributeDefinition $source, string $name): bool
    {
        return 0 < (int) $this->em->getRepository(AttributeDefinition::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.workspace = :ws')
            ->andWhere('d.name = :name OR d.slug = :slug')
            ->setParameter('ws', $source->getWorkspaceId())
            ->setParameter('name', $name)
            ->setParameter('slug', self::slugify($name))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Same as the Gedmo sluggable listener with the AttributeDefinition::$slug options.
     */
    private static function slugify(string $name): string
    {
        $slug = new AsciiSlugger()->slug(u($name)->ascii()->toString(), '')->lower()->toString();

        return substr($slug, 0, self::SLUG_MAX_LENGTH);
    }
}
