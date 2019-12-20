<?php

declare(strict_types=1);

namespace App\Command;

use App\User\UserManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetUserRolesCommand extends Command
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
            ->setName('app:user:set-roles')
            ->setDescription('Update user roles')
            ->addArgument('username', InputArgument::REQUIRED, 'The username')
            ->addArgument(
                'roles',
                InputArgument::REQUIRED,
                'User roles (comma separated values)',
                null
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');

        $user = $this->userManager->findUserByUsername($username);
        if (null === $user) {
            throw new Exception(sprintf('User with username "%s" does not exist', $username));
        }

        $roles = explode(',', $input->getArgument('roles'));
        $user->setRoles($roles);

        $this->userManager->persistUser($user);

        return 0;
    }
}
