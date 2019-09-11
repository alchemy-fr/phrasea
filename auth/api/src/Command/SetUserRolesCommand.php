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
            ->addArgument('email', InputArgument::REQUIRED, 'The user email (used a login)')
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
        $email = $input->getArgument('email');

        $user = $this->userManager->findUserByEmail($email);
        if (null === $user) {
            throw new Exception(sprintf('User with email "%s" does not exist', $email));
        }

        $roles = explode(',', $input->getArgument('roles'));
        $user->setRoles($roles);

        $this->userManager->persistUser($user);

        return 0;
    }
}
