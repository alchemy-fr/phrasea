<?php

declare(strict_types=1);

namespace App\Command\Attribute;

use App\Attribute\AttributeSplitter;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SplitAttributesCommand extends Command
{
    public function __construct(private readonly AttributeSplitter $attributeSplitter, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:attributes:split')
            ->setDescription('Transform single-valued attribute into multi-valued one')
            ->addArgument('attribute-definition-id', InputArgument::REQUIRED)
            ->addOption('delimiter', 'd', InputOption::VALUE_REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $attrDefId = $input->getArgument('attribute-definition-id');
        $attributeDefinition = $this->em->find(AttributeDefinition::class, $attrDefId);
        $delimiter = $input->getOption('delimiter') ?? ';';

        if (!$attributeDefinition instanceof AttributeDefinition) {
            throw new \InvalidArgumentException('AttributeDefinition '.$attrDefId.' not found');
        }

        $this->em->getConfiguration()->setSQLLogger();
        $this->attributeSplitter->splitAttributes($attributeDefinition, $delimiter);

        $output->writeln('Done.');

        return 0;
    }
}
