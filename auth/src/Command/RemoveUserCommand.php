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

class RemoveUserCommand extends Command
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
            ->setName('app:user:remove')
            ->setDescription('Remove user from database')
            ->addArgument('email', InputArgument::REQUIRED, 'The user email (used a login)')
            ;
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

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(sprintf(
            '<question>Are you sure you want to delete user %s from database? [yN]</question>',
            $user->getEmail()
        ), false);

        if (!$helper->ask($input, $output, $question)) {
            return 1;
        }

        $this->userManager->removeUser($user);

        $output->writeln('User removed!');

        return 0;
    }
}
