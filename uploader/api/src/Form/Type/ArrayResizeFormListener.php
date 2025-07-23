<?php

namespace App\Form\Type;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

#[When('')]
class ArrayResizeFormListener implements EventSubscriberInterface
{
    public function __construct(
        protected string $type,
        protected array $options = [],
        protected ?bool $deleteEmpty = false,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_SUBMIT => 'preSubmit',
            FormEvents::SUBMIT => ['onSubmit', 50],
        ];
    }

    /**
     * @return void
     */
    public function preSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData() ?? [];

        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // First remove all rows
        foreach ($form as $name => $child) {
            $form->remove($name);
        }

        // Then add all rows again in the correct order
        foreach ($data as $name => $value) {
            $form->add($name, $this->type, array_replace([
                'property_path' => '['.$name.']',
            ], $this->options));
        }
    }

    /**
     * @return void
     */
    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (!\is_array($data)) {
            $data = [];
        }

        foreach ($form as $name => $child) {
            if (!isset($data[$name])) {
                $form->remove($name);
            }
        }

        foreach ($data as $name => $value) {
            if (!$form->has($name)) {
                $form->add($name, $this->type, array_replace([
                    'property_path' => '['.$name.']',
                ], []));
            }
        }
    }

    /**
     * @return void
     */
    public function onSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData() ?? [];

        // At this point, $data is an array or an array-like object that already contains the
        // new entries, which were added by the data mapper. The data mapper ignores existing
        // entries, so we need to manually unset removed entries in the collection.

        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if ($this->deleteEmpty) {
            /** @var FormInterface $child */
            foreach ($form as $name => $child) {
                if (!$child->isValid() || !$child->isSynchronized()) {
                    continue;
                }

                $isEmpty = \is_callable($this->deleteEmpty) ? ($this->deleteEmpty)($child->getData()) : $child->isEmpty();

                if ($isEmpty) {
                    unset($data[$name]);
                    $form->remove($name);
                }
            }
        }

        $toDelete = [];

        foreach ($data as $name => $child) {
            if (!$form->has($name)) {
                $toDelete[] = $name;
            }
        }

        foreach ($toDelete as $name) {
            unset($data[$name]);
        }

        $event->setData($data);
    }
}
