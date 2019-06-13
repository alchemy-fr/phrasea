<?php

declare(strict_types=1);

namespace App\Command;

use App\User\UserManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnableUserCommand extends Command
{
    private $userManager;

    public function __construct(UserManager $userManager)
    {
        parent::__construct();

        $this->userManager = $userManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:user:enable')
            ->setDescription('Enable user')
            ->addArgument('email', InputArgument::REQUIRED, 'The user email (used a login)')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Disable the user');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');

        $user = $this->userManager->findUserByEmail($email);
        if (null === $user) {
            throw new Exception(sprintf('User with email "%s" does not exist', $email));
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
