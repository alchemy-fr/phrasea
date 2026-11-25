<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-target',
    description: 'Creates a new upload target',
    help: <<<EOT
The <info>%command.name%</info> command creates a new upload target.

<info>php %command.full_name% <slug> <name> <target-url> [--hidden]</info>

EOT,
)]
class CreateTargetCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The target unique slug')]
        string $slug,
        #[Argument('The target name')]
        string $name,
        #[Argument]
        string $targetUrl,
        #[Option('Whether the target is hidden')]
        bool $hidden = false,
    ): int {
        /** @var Target $target */
        $target = $this->em->getRepository(Target::class)->findOneBy([
            'slug' => $slug,
        ]);

        if (null === $target) {
            $target = new Target();
            $target->setSlug($slug);
        }

        $target->setTargetUrl($targetUrl);
        $target->setHidden($hidden);
        $target->setName($name);
        $this->em->persist($target);
        $this->em->flush();

        $output->writeln('OK');

        return 0;
    }
}
