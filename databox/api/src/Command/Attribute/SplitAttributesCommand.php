<?php

declare(strict_types=1);

namespace App\Command\Attribute;

use App\Attribute\AttributeSplitter;
use App\Entity\Core\AttributeDefinition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:attributes:split',
    description: 'Transform single-valued attribute into multi-valued one'
)]
class SplitAttributesCommand extends Command
{
    public function __construct(private readonly AttributeSplitter $attributeSplitter, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument]
        string $attributeDefinitionId,
        #[Option(shortcut: 'd')]
        string $delimiter = ';',
    ): int {
        $attributeDefinition = $this->em->find(AttributeDefinition::class, $attributeDefinitionId);

        if (!$attributeDefinition instanceof AttributeDefinition) {
            throw new \InvalidArgumentException('AttributeDefinition '.$attributeDefinitionId.' not found');
        }

        $this->attributeSplitter->splitAttributes($attributeDefinition, $delimiter);

        $output->writeln('Done.');

        return 0;
    }
}
