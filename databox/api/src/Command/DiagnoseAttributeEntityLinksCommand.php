<?php

namespace App\Command;

use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\Entity\Core\AttributeEntity;
use App\Repository\Core\AttributeDefinitionRepository;
use App\Repository\Core\AttributeEntityRepository;
use App\Repository\Core\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:diagnose:attribute-entity-links',
    description: 'Diagnose broken AttributeEntity links from Attribute value.'
)]
class DiagnoseAttributeEntityLinksCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeDefinitionRepository $definitionRepository,
        private readonly AttributeRepository $attributeRepository,
        private readonly AttributeEntityRepository $entityRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Remove invalid Attributes referencing missing AttributeEntity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fix = $input->getOption('fix');
        $output->writeln('<info>Diagnosing broken AttributeEntity links...</info>');

        $definitions = $this->definitionRepository->createQueryBuilder('d')
            ->andWhere('d.entityList IS NOT NULL')
            ->getQuery()
            ->getResult();

        if (empty($definitions)) {
            $output->writeln('<comment>No AttributeDefinitions with entityList found.</comment>');

            return Command::SUCCESS;
        }

        $broken = [];
        $toRemove = [];
        foreach ($definitions as $definition) {
            assert($definition instanceof AttributeDefinition);
            // 2. Get all Attributes for this definition
            $attributes = $this->attributeRepository->createQueryBuilder('a')
                ->andWhere('a.definition = :definition')
                ->setParameter('definition', $definition->getId())
                ->getQuery()
                ->getResult();

            foreach ($attributes as $attr) {
                assert($attr instanceof Attribute);
                $value = $attr->getValue();
                if (empty($value)) {
                    continue;
                }

                $entity = $this->entityRepository->find($value);
                if (!$entity instanceof AttributeEntity) {
                    $broken[] = [
                        'attribute_id' => $attr->getId(),
                        'definition_id' => $definition->getId(),
                        'broken_entity_uuid' => $value,
                    ];
                    $toRemove[] = $attr;
                }
            }
        }

        if (empty($broken)) {
            $output->writeln('<info>No broken links found.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Broken AttributeEntity links found:</error>');
        foreach ($broken as $row) {
            $output->writeln(sprintf(
                'Attribute ID: %s, Definition ID: %s, Broken Entity UUID: %s',
                $row['attribute_id'],
                $row['definition_id'],
                $row['broken_entity_uuid']
            ));
        }
        $output->writeln(sprintf('<comment>Total broken links: %d</comment>', count($broken)));

        if ($fix) {
            $output->writeln('<info>Removing invalid Attributes...</info>');
            foreach ($toRemove as $attr) {
                $output->writeln(sprintf('Removing Attribute ID: %s', $attr->getId()));
                $this->em->remove($attr);
            }
            $this->em->flush();
            $output->writeln(sprintf('<info>Removed %d invalid Attributes.</info>', count($toRemove)));

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
