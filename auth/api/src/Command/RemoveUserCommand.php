<?php

declare(strict_types=1);

namespace App\Command;

use App\User\UserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RemoveUserCommand extends Command
{
    private $userManager;

    public function __construct(UserManager $userManager)
    {
        parent::__construct();

        $this->userManager = $userManager;
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:user:remove')
            ->setDescription('Remove user from database')
            ->addArgument('username', InputArgument::REQUIRED, 'The user username')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');

        $user = $this->userManager->findUserByUsername($username);
        if (null === $user) {
            throw new \Exception(sprintf('User with username "%s" does not exist', $username));
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(sprintf(
            '<question>Are you sure you want to delete user %s from database? [yN]</question>',
            $user->getUsername()
        ), false);

        if (!$helper->ask($input, $output, $question)) {
            return 1;
        }

        $this->userManager->removeUser($user);

        $output->writeln('User removed!');

        return 0;
    }
}
