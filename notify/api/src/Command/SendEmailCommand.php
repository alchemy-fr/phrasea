<?php

declare(strict_types=1);

namespace App\Command;

use App\Consumer\Handler\SendEmail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SendEmailCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('app:send-email')
            ->setDescription('Send an email to a user')
            ->addArgument('template', InputArgument::REQUIRED, 'The mail template name')
            ->addArgument('email', InputArgument::REQUIRED, 'The recipient email')
            ->addArgument('locale', InputArgument::REQUIRED, 'The recipient locale')
            ->addArgument('parameters', InputArgument::OPTIONAL, 'JSON encoded template parameters')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $template = $input->getArgument('template');
        $email = $input->getArgument('email');
        $locale = $input->getArgument('locale');

        if ($input->getArgument('parameters')) {
            $parameters = json_decode((string) $input->getArgument('parameters'), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $parameters = [];
        }

        $this->bus->dispatch(new SendEmail($email, $template, $parameters, $locale));

        return 0;
    }
}
