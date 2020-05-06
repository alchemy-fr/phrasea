<?php

declare(strict_types=1);

namespace Alchemy\OAuthServerBundle\Command;

use Alchemy\OAuthServerBundle\Entity\OAuthClient;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateClientCommand extends Command
{
    private ClientManagerInterface $clientManager;

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
            ->setName('alchemy:oauth:create-client')
            ->setDescription('Creates a new OAuth client')
            ->addArgument('client-id', InputArgument::REQUIRED, 'The client ID')
            ->addOption(
                'random-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Force the random suffix of public ID.',
                null
            )
            ->addOption(
                'secret',
                null,
                InputOption::VALUE_REQUIRED,
                'Force the client secret.',
                null
            )
            ->addOption(
                'redirect-uri',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Sets redirect uri for client. Use this option multiple times to set multiple redirect URIs.',
                null
            )
            ->addOption(
                'append-redirect-uri',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Appends redirect uri for client. Use this option multiple times to set multiple redirect URIs.',
                null
            )
            ->addOption(
                'grant-type',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Sets allowed grant type for client. Use this option multiple times to set multiple grant types..',
                null
            )
            ->addOption(
                'append-grant-type',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Appends allowed grant type for client. Use this option multiple times to set multiple grant types..',
                null
            )
            ->addOption(
                'scope',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Sets allowed scope type for client. Use this option multiple times to set multiple scopes..',
                null
            )
            ->addOption(
                'append-scope',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Appends allowed scope for client. Use this option multiple times to set multiple scopes..',
                null
            )
            ->setHelp(<<<EOT
The <info>%command.name%</info> command creates a new client.

<info>php %command.full_name% [--redirect-uri=...] [--grant-type=...]</info>

EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Client Credentials');
        $clientId = $input->getArgument('client-id');

        /** @var OAuthClient $client */
        $client = $this->clientManager->findClientBy([
            'id' => $clientId,
        ]);

        if (null === $client) {
            $client = $this->clientManager->createClient();
            $client->setId($clientId);
        }

        if ($randomId = $input->getOption('random-id')) {
            $client->setRandomId($randomId);
        }
        if ($secret = $input->getOption('secret')) {
            $client->setSecret($secret);
        }

        if ($append = $input->getOption('append-redirect-uri')) {
            if ($input->getOption('redirect-uri')) {
                throw new InvalidArgumentException('Options append-redirect-uri and redirect-uri cannot be used together');
            }
            $client->setRedirectUris(array_unique(array_merge(
                $client->getRedirectUris(),
                $input->getOption('append-redirect-uri')
            )));
        } else {
            $client->setRedirectUris($input->getOption('redirect-uri'));
        }

        if ($append = $input->getOption('append-grant-type')) {
            if ($input->getOption('grant-type')) {
                throw new InvalidArgumentException('Options append-grant-type and grant-type cannot be used together');
            }
            $client->setAllowedGrantTypes(array_unique(array_merge(
                $client->getAllowedGrantTypes(),
                $input->getOption('append-grant-type')
            )));
        } else {
            $client->setAllowedGrantTypes($input->getOption('grant-type'));
        }

        if ($append = $input->getOption('append-scope')) {
            if ($input->getOption('scope')) {
                throw new InvalidArgumentException('Options append-scope and scope cannot be used together');
            }
            $client->setAllowedScopes(array_unique(array_merge(
                $client->getAllowedScopes(),
                $input->getOption('append-scope')
            )));
        } else {
            $client->setAllowedScopes($input->getOption('scope'));
        }

        $this->clientManager->updateClient($client);

        $headers = ['Client ID', 'Client Secret'];
        $rows = [
            [$client->getPublicId(), $client->getSecret()],
        ];

        $io->table($headers, $rows);

        return 0;
    }
}
