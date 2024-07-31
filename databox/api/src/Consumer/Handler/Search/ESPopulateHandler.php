<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Entity\Admin\PopulatePass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ESPopulateHandler
{
    public function __construct(
        private KernelInterface $kernel,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ESPopulate $message): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            'command' => 'fos:elastica:populate',
        ]);
        $code = $application->run($input, new NullOutput());

        if (0 !== $code) {
            $unterminated = $this->em->getRepository(PopulatePass::class)->findBy([
                'endedAt' => null,
            ]);
            foreach ($unterminated as $pp) {
                $pp->setError(sprintf('Unexpected command return code %d (expected 0)', $code));
                $pp->setEndedAt(new \DateTimeImmutable());
                $this->em->persist($pp);
            }
            $this->em->flush();
        }
    }
}
