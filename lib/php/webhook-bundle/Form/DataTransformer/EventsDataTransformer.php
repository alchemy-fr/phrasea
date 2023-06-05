<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Form\DataTransformer;

use Alchemy\WebhookBundle\Entity\Webhook;
use Symfony\Component\Form\DataTransformerInterface;

class EventsDataTransformer implements DataTransformerInterface
{
    public function transform($value)
    {
        if (empty($value)) {
            return [Webhook::ALL_EVENTS];
        }

        return $value;
    }

    public function reverseTransform($value)
    {
        if (in_array(Webhook::ALL_EVENTS, $value)) {
            return [];
        }

        return array_filter($value);
    }
}
