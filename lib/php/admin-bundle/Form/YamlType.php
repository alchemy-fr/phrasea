<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Form;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class YamlType extends AbstractType 
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr'=> [
                'rows' => 10,
                'style' => 'font-family: "Courier New"',
            ],
            'constraints' => [
                new Assert\Callback(
                    function (mixed $data, ExecutionContextInterface $context) {
                        try {
                            Yaml::parse($data);
                        } catch (ParseException $e) {
                            $context
                                ->buildViolation(sprintf('YAML error: %s', $e->getMessage()))
                                ->addViolation();
                        }
                    }       
                )
            ]
        ]);
    }

    public function getParent(): ?string
    {
        return TextareaType::class;
    }
}
