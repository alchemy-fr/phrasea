<?php

declare(strict_types=1);

namespace App\Asset\Attribute;

use App\Elasticsearch\Mapping\IndexMappingUpdater;
use App\Entity\Core\Asset;
use App\Entity\Core\Attribute;
use App\Entity\Core\AttributeDefinition;
use App\File\FileMetadataAccessorWrapper;
use App\Repository\Core\AttributeDefinitionRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class InitialResolver
{
    private Environment $twig;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $em, loggerInterface $logger)
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * @param Asset $asset
     * @return array<string, array<string, Attribute>>
     */
    public function resolveInitialAttributes(Asset $asset): array
    {
        $attributes = [];

        /** @var AttributeDefinitionRepositoryInterface $repo */
        $repo = $this->em->getRepository(AttributeDefinition::class);

        // only get attrDefs with initializers setting
        $definitions = $repo->getWorkspaceInitializeDefinitions($asset->getWorkspaceId());

        foreach ($definitions as $definition) {
            $initializers = $definition->getInitializers();

            // todo: remove
            $this->logger->debug(sprintf("INITIAL VALUE FOR %s: JS=%s", $definition->getName() , var_export($initializers, true)));

            if (null !== $initializers) {
                foreach ($initializers as $locale => $initializeFormula) {

                    $initialValue = $this->resolveInitial(
                        $initializeFormula,
                        [
                            'file' =>  new FileMetadataAccessorWrapper($asset->getSource(), $this->logger),
                            'asset' => $asset
                        ],
                        $definition
                    );

                    // todo: remove
                    $this->logger->debug(sprintf("  INITIAL VALUE FOR %s[%s]: %s", $definition->getName(), $locale, var_export($initialValue, true)));

                    $initialValues = [];
                    if ($definition->isMultiple()) {
                        // each line becomes a value
                        $initialValues = array_filter(
                            explode("\n", $initialValue),
                            function ($s) {
                                return '' != trim($s);
                            }
                        );

                        // todo: remove debug after testing
                        $this->logger->debug(sprintf("initialValues result for '%s' (multi): [%s]", $definition->getName(), join(', ', array_map(function ($v) {return var_export($v, true); }, $initialValues))));

                    } else if( '' != ($initialValue = trim($initialValue)) ) {
                        $initialValues = [$initialValue];

                        // todo: remove debug after testing
                        $this->logger->debug(sprintf("initialValues result for '%s' (mono): %s", $definition->getName(), var_export($initialValues, true)));
                    }

                    $position = 0;
                    foreach($initialValues as $initialValue) {
                        $attribute = new Attribute();
                        $attribute->setCreatedAt(new DateTimeImmutable());
                        $attribute->setUpdatedAt(new DateTimeImmutable());
                        $attribute->setDefinition($definition);
                        $attribute->setAsset($asset);
                        $attribute->setOrigin(Attribute::ORIGIN_INITIAL);
                        $attribute->setValue($initialValue);
                        $attribute->setPosition($position++);
                        if($locale !== IndexMappingUpdater::NO_LOCALE) {
                            $attribute->setLocale(IndexMappingUpdater::NO_LOCALE);
                        }

                        $attributes[] = $attribute;
                    }
                }
            }
        }

        return $attributes;
    }


    private function resolveInitial(string $initializeFormula, array $twigContext, AttributeDefinition $definition): string
    {
        $templateFormula = false;
        try {
            $initializeFormula = json_decode($initializeFormula, true, 512, JSON_THROW_ON_ERROR);
        }
        catch (\Exception $e) {
            // not json ? assume this is plain twig template
            $templateFormula = $initializeFormula;
        }

        if($templateFormula === false) {
            // assume this is json formula
            if($initializeFormula['type'] == "metadata") {
                // the "source" is a simple metadata tagname, convert it to twig
                $templateFormula = sprintf("{%% for v in file.metadata('%s').values %%}{{v}}\n{%% endfor %%}", $initializeFormula['value']);
            }
            else if ($initializeFormula['type'] == "template") {
                $templateFormula = $initializeFormula['template'];
            }
            else {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid template type for attribute "%s"', $initializeFormula['type'], $definition->getName()));
            }
        }

        $this->logger->debug(sprintf("FORMULA = \"%s\"", $templateFormula));
        $template = $this->twig->createTemplate($templateFormula);

        return $this->twig->render($template, $twigContext);
    }
}
