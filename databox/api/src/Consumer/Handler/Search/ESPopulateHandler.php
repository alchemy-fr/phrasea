<?php

declare(strict_types=1);

namespace App\Consumer\Handler\Search;

use App\Entity\Admin\PopulatePass;
use Arthem\Bundle\RabbitBundle\Consumer\Event\AbstractEntityManagerHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class ESPopulateHandler extends AbstractEntityManagerHandler
{
    final public const EVENT = 'es_populate';

    public function __construct(private readonly KernelInterface $kernel, private readonly EntityManagerInterface $em)
    {
    }

    public function handle(EventMessage $message): void
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

    public static function createEvent(): EventMessage
    {
        return new EventMessage(self::EVENT, []);
    }

    public static function getHandledEvents(): array
    {
        return [self::EVENT];
    }
}
