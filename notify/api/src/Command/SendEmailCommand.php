<?php

declare(strict_types=1);

namespace App\Command;

use App\Consumer\Handler\SendEmailHandler;
use Arthem\Bundle\RabbitBundle\Consumer\Event\EventMessage;
use Arthem\Bundle\RabbitBundle\Producer\EventProducer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendEmailCommand extends Command
{
    /**
     * @var EventProducer
     */
    private $eventProducer;

    public function __construct(EventProducer $eventProducer)
    {
        parent::__construct();

        $this->eventProducer = $eventProducer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:send-email')
            ->setDescription('Send an email to a user')
            ->addArgument('template', InputArgument::REQUIRED, 'The mail template name')
            ->addArgument('email', InputArgument::REQUIRED, 'The recipient email')
            ->addArgument('locale', InputArgument::REQUIRED, 'The recipient locale')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $template  = $input->getArgument('template');
        $email  = $input->getArgument('email');
        $locale  = $input->getArgument('locale');
        $parameters = [];

        $this->eventProducer->publish(new EventMessage(SendEmailHandler::EVENT, [
            'email' => $email,
            'template' => $template,
            'parameters' => $parameters,
            'locale' => $locale,
        ]));

        return 0;
    }
}
