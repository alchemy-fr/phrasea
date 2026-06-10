<?php

namespace App\Service\Workspace;

use App\Attribute\Type\TextAttributeType;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributePolicy;
use App\Entity\Core\RenditionPolicy;
use App\Entity\Core\Workspace;
use App\Model\AssetTypeEnum;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WorkspaceCreator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function createWorkspace(Workspace $workspace): void
    {
        $publicRenditionPolicy = new RenditionPolicy();
        $publicRenditionPolicy->setWorkspace($workspace);
        $publicRenditionPolicy->setName('Public');
        $publicRenditionPolicy->setPublic(true);
        $this->em->persist($publicRenditionPolicy);

        $publicPolicy = new AttributePolicy();
        $publicPolicy->setWorkspace($workspace);
        $publicPolicy->setPublic(true);
        $publicPolicy->setEditable(true);
        $publicPolicy->setName('Public');
        $this->em->persist($publicPolicy);

        $nameAttribute = new AttributeDefinition();
        $nameAttribute->setWorkspace($workspace);
        $nameAttribute->setPolicy($publicPolicy);
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

        $this->em->persist($nameAttribute);
        $this->em->persist($workspace);
    }
}
