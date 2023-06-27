<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Form;

use Alchemy\WebhookBundle\Entity\Webhook;
use Alchemy\WebhookBundle\Form\DataTransformer\EventsDataTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

class EventsChoiceType extends AbstractType
{
    public function __construct(private readonly array $events)
    {
    }

    public function getBlockPrefix()
    {
        return 'alchemy_webhook_events';
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new EventsDataTransformer());

        $builder->add(Webhook::ALL_EVENTS, CheckboxType::class, [
            'label' => 'All events',
        ]);
        foreach ($this->events as $name => $event) {
            $builder->add($name, CheckboxType::class, [
                'label' => $name,
                'help' => $event['description'] ?? null,
            ]);
        }
    }
}
