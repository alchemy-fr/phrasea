<?php

declare(strict_types=1);

namespace App\Command;

use App\User\UserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnableUserCommand extends Command
{
    public function __construct(private readonly UserManager $userManager)
    {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:user:enable')
            ->setDescription('Enable user')
            ->addArgument('username', InputArgument::REQUIRED, 'The username')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Disable the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');

        $user = $this->userManager->findUserByUsername($username);
        if (null === $user) {
            throw new \Exception(sprintf('User with username "%s" does not exist', $username));
        }

        $enabled = !$input->getOption('disable', false);

        $user->setEnabled($enabled);
        $this->userManager->persistUser($user);

        $output->writeln(sprintf(
            'User has been %s!',
            $enabled ? 'enabled' : 'disabled'
        ));

        return 0;
    }
}
