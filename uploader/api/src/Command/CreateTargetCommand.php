<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTargetCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:create-target')
            ->setDescription('Creates a new upload target')
            ->addArgument('slug', InputArgument::REQUIRED, 'The target unique slug')
            ->addArgument('name', InputArgument::REQUIRED, 'The target name')
            ->addArgument('target-url', InputArgument::REQUIRED)
            ->addOption('hidden', null, InputOption::VALUE_NONE, 'Whether the target is hidden')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command creates a new upload target.

<info>php %command.full_name% [--redirect-uri=...] [--grant-type=...]</info>

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = $input->getArgument('slug');

        /** @var Target $target */
        $target = $this->em->getRepository(Target::class)->findOneBy([
            'slug' => $slug,
        ]);

        if (null === $target) {
            $target = new Target();
            $target->setSlug($slug);
        }

        $target->setTargetUrl($input->getArgument('target-url'));
        $target->setHidden((bool) $input->getOption('hidden'));
        $target->setName($input->getArgument('name'));
        $this->em->persist($target);
        $this->em->flush();

        $output->writeln('OK');

        return 0;
    }
}
