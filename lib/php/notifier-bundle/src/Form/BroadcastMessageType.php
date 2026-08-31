<?php

declare(strict_types=1);

namespace Alchemy\NotifierBundle\Form;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Model\BroadcastMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BroadcastMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Subject',
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 8],
                'help' => 'HTML is allowed (emails and in-app notifications render it as-is).',
            ])
            ->add('channels', EnumType::class, [
                'class' => ChannelType::class,
                'choice_label' => static fn (ChannelType $channel): string => $channel->label(),
                'multiple' => true,
                'expanded' => true,
                'label' => 'Channels',
            ])
            ->add('directory', ChoiceType::class, [
                'choices' => array_flip($options['directories']),
                'label' => 'Audience',
                'help' => 'Who receives this notification.',
            ])
            ->add('url', TextType::class, [
                'required' => false,
                'label' => 'Link (optional)',
                'help' => 'Client URI the notification points at, e.g. /assets/42.',
            ])
            ->add('excludeMe', CheckboxType::class, [
                'required' => false,
                'label' => 'Do not notify me',
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $message = $event->getData();
            if (!$message instanceof BroadcastMessage) {
                return;
            }

            // An empty message would silently notify everybody with a blank body
            if (null === $message->subject || '' === trim($message->subject)
                || null === $message->body || '' === trim($message->body)) {
                $event->getForm()->addError(new FormError('Fill in both the subject and the message.'));
            }

            if ([] === $message->channels) {
                $event->getForm()->addError(new FormError('Pick at least one channel.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => BroadcastMessage::class,
                'directories' => [],
            ])
            ->setAllowedTypes('directories', 'array')
        ;
    }
}
