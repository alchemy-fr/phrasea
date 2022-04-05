<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Elasticsearch\Listener\DeferredIndexListener;
use App\Entity\Core\AttributeDefinition;
use App\Migrations\AbstractServiceContainerMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20220404135920 extends AbstractServiceContainerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }

    public function postUp(Schema $schema): void
    {
        DeferredIndexListener::disable();

        $em = $this->getEntityManager();
        $em->getConnection()->getConfiguration()->setSQLLogger(null);

        /** @var AttributeDefinition[] $attributeDefinitions */
        $attributeDefinitions = $em->createQueryBuilder()
            ->select('d')
            ->from(AttributeDefinition::class, 'd')
            ->andWhere('d.slug IS NULL')
            ->getQuery()
            ->toIterable();

        foreach ($attributeDefinitions as $d) {
            if ($d->getSlug()) {
                continue;
            }

            $name = $d->getName();
            $d->setName($name.'__');
            $em->persist($d);
            $em->flush();
            $d->setName($name);
            $em->persist($d);
            $em->flush();
        }
    }
}
