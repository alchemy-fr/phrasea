<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Form;

use Alchemy\WebhookBundle\Form\DataTransformer\EventsDataTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class EventsChoiceType extends ChoiceType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->addModelTransformer(new EventsDataTransformer());
    }
}
