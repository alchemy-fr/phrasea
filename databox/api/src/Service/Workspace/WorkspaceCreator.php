<?php

declare(strict_types=1);

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
        $renditionPolicy = new RenditionPolicy();
        $renditionPolicy->setWorkspace($workspace);
        $renditionPolicy->setName('Public');
        $renditionPolicy->setPublic(true);
        $renditionPolicy->setEditable(true);
        $this->em->persist($renditionPolicy);

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

        $this->em->persist($nameAttribute);
        $this->em->persist($workspace);
    }
}
