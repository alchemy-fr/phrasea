<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Secrets\EncryptionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSecretsKeyPairCommand extends Command
{
    public function __construct(
        private readonly EncryptionManager $encryptionManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:secrets:generate-key-pair')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keyPair = $this->encryptionManager->generateKeyPair();

        $output->writeln('<comment>Public key:</comment>');
        $output->writeln($keyPair->getPublic());
        $output->writeln('<comment>Secret key:</comment>');
        $output->writeln($keyPair->getSecret());

        return 0;
    }
}
