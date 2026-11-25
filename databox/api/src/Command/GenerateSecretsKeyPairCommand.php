<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Secrets\EncryptionManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:secrets:generate-key-pair')]
class GenerateSecretsKeyPairCommand extends Command
{
    public function __construct(
        private readonly EncryptionManager $encryptionManager,
    ) {
        parent::__construct();
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
