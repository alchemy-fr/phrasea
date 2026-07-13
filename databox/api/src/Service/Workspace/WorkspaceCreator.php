<?php

declare(strict_types=1);

namespace App\Service\Workspace;

use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionDefinition;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Entity\Integration\WorkspaceIntegration;
use App\Integration\Core\FileAnalyzer\FileAnalyzerIntegration;
use App\Integration\Core\ReadMetadata\ReadMetadataIntegration;
use App\Integration\Core\Rendition\RenditionIntegration;
use App\Model\AssetTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Yaml;

final readonly class WorkspaceCreator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createWorkspace(Workspace $workspace): void
    {
        $renditionPolicy = new RenditionPolicy();
        $renditionPolicy->setWorkspace($workspace);
        $renditionPolicy->setName('Public');
        $renditionPolicy->setPublic(true);
        $renditionPolicy->setEditable(true);
        $this->em->persist($renditionPolicy);

        $previousRendition = null;
        $renditionDefs = [];
        foreach (['main', 'preview', 'thumbnail'] as $renditionName) {
            $rendition = new RenditionDefinition();
            $rendition->setParent($previousRendition);
            $renditionDefs[$renditionName] = $rendition;
            $previousRendition = $rendition;
            $rendition->setName(ucfirst($renditionName));
            $rendition->setPolicy($renditionPolicy);
            $rendition->setWorkspace($workspace);
            $rendition->setUseAsMain('main' === $renditionName);
            $rendition->setUseAsPreview('preview' === $renditionName);
            $rendition->setUseAsThumbnail('thumbnail' === $renditionName);
            $buildFile = __DIR__.'/renditions/'.$renditionName.'.yaml';
            if (file_exists($buildFile)) {
                $rendition->setBuildMode(RenditionDefinition::BUILD_MODE_CUSTOM);
                $rendition->setDefinition(file_get_contents($buildFile));
            } else {
                $rendition->setBuildMode(RenditionDefinition::BUILD_MODE_PICK_SOURCE);
            }
            $rendition->setTarget(AssetTypeEnum::Both);
            $rendition->setKey($renditionName);
            $rendition->setSubstitutable(true);
            $this->em->persist($rendition);
        }

        $attributePolicy = new AttributePolicy();
        $attributePolicy->setWorkspace($workspace);
        $attributePolicy->setPublic(true);
        $attributePolicy->setEditable(true);
        $attributePolicy->setName('Public');
        $this->em->persist($attributePolicy);

        $nameAttribute = new AttributeDefinition();
        $nameAttribute->setWorkspace($workspace);
        $nameAttribute->setPolicy($attributePolicy);
        $nameAttribute->setName('Name');
        $nameAttribute->setSlug('name');
        $nameAttribute->setType(TextAttributeType::NAME);
        $nameAttribute->setTarget(AssetTypeEnum::Both);
        $nameAttribute->setEnabled(true);
        $nameAttribute->setNamePriority(0);
        $nameAttribute->setFillFromName(true);
        $nameAttribute->setPosition(0);
        $nameAttribute->setEditable(true);
        $nameAttribute->setEditableInGui(true);
        $nameAttribute->setMultiple(false);

        $readMetadataIntegration = new WorkspaceIntegration();
        $readMetadataIntegration->setOwnerId($workspace->getOwnerId());
        $readMetadataIntegration->setPublic(false);
        $readMetadataIntegration->setIntegration(ReadMetadataIntegration::getName());
        $readMetadataIntegration->setWorkspace($workspace);
        $this->em->persist($readMetadataIntegration);

        $renditionIntegration = new WorkspaceIntegration();
        $renditionIntegration->setOwnerId($workspace->getOwnerId());
        $renditionIntegration->setPublic(true);
        $renditionIntegration->setWorkspace($workspace);
        $renditionIntegration->setIntegration(RenditionIntegration::getName());

        if ($workspace->isFileAnalysisRequired()) {
            $fileAnalyzerIntegration = new WorkspaceIntegration();
            $fileAnalyzerIntegration->setOwnerId($workspace->getOwnerId());
            $fileAnalyzerIntegration->setPublic(false);
            $fileAnalyzerIntegration->setIntegration(FileAnalyzerIntegration::getName());
            $fileAnalyzerIntegration->setWorkspace($workspace);
            $fileAnalyzerIntegration->setConfig(Yaml::parse(file_get_contents(__DIR__.'/fileAnalyzers.yaml')));
            $fileAnalyzerIntegration->getNeeds()->add($readMetadataIntegration);
            $this->em->persist($fileAnalyzerIntegration);

            $renditionBaseIntegration = new WorkspaceIntegration();
            $renditionBaseIntegration->setName('Base Renditions');
            $renditionBaseIntegration->setConfig(['renditions' => [$renditionDefs['thumbnail']->getId()]]);
            $renditionBaseIntegration->setOwnerId($workspace->getOwnerId());
            $renditionBaseIntegration->setPublic(true);
            $renditionBaseIntegration->setWorkspace($workspace);
            $renditionBaseIntegration->setIntegration(RenditionIntegration::getName());
            $this->em->persist($renditionBaseIntegration);

            $renditionIntegration->getNeeds()->add($fileAnalyzerIntegration);
            $renditionIntegration->getNeeds()->add($renditionBaseIntegration);
        }

        $this->em->persist($renditionIntegration);

        $this->em->persist($nameAttribute);
        $this->em->persist($workspace);
    }
}
