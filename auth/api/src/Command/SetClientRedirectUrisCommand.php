<?php

declare(strict_types=1);

namespace App\Command;

use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SetClientRedirectUrisCommand extends Command
{
    /**
     * @var ClientManagerInterface
     */
    private $clientManager;

    public function __construct(ClientManagerInterface $clientManager)
    {
        parent::__construct();
        $this->clientManager = $clientManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:oauth:client:set-redirect-uris')
            ->setDescription('Update OAuth client redirect URIs')
            ->addArgument('client_id', InputArgument::REQUIRED, 'The client public ID')
            ->addOption('redirect_uris', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The redirect URIs')
            ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $clientId = $input->getArgument('client_id');
        $redirectUris = $input->getOption('redirect_uris');

        $client = $this->clientManager->findClientByPublicId($clientId);
        if (null === $client) {
            throw new NotFoundHttpException('Client not found');
        }
        $client->setRedirectUris($redirectUris);
        $this->clientManager->updateClient($client);

        return 0;
    }
}
