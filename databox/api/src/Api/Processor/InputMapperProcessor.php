<?php

declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Api\Mapper\Input\InputMapperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Persists the entity built from the input DTO of a write operation:
 * maps the (validated) DTO onto the read entity or a new one, checks the
 * entity security expressions, validates the entity and persists it.
 *
 * As the operation "securityPostDenormalize"/"securityPostValidation" would be
 * evaluated against the input DTO, the expressions checking the resulting entity
 * are given as extra properties:
 *
 *     extraProperties: [
 *         InputMapperProcessor::ENTITY_SECURITY => 'is_granted("CREATE", object)',
 *         InputMapperProcessor::ENTITY_SECURITY_POST_VALIDATION => '...',
 *     ]
 */
final readonly class InputMapperProcessor implements ProcessorInterface
{
    public const string ENTITY_SECURITY = 'entity_security';
    public const string ENTITY_SECURITY_POST_VALIDATION = 'entity_security_post_validation';

    public function __construct(
        #[AutowireLocator(InputMapperInterface::TAG)]
        private ContainerInterface $mappers,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private ValidatorInterface $validator,
        private ResourceAccessCheckerInterface $resourceAccessChecker,
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        /** @var InputMapperInterface $mapper */
        $mapper = $this->mappers->get($data::class);

        $target = $context['read_data'] ?? null;
        $target = \is_object($target) ? $target : null;
        $entity = $mapper->map($data, $target, $context + [
            'operation' => $operation,
            'resource_class' => $operation->getClass(),
        ]);

        if (null === $entity) {
            if (null === $target) {
                throw new BadRequestHttpException('Nothing to create from the given input');
            }

            // The input removed the entity (e.g. an attribute updated with an empty value)
            $this->em->flush();

            return null;
        }

        $this->checkSecurity(self::ENTITY_SECURITY, $entity, $operation, $context);
        if (false !== $operation->canValidate()) {
            $this->validator->validate($entity, $operation->getValidationContext() ?? []);
        }
        $this->checkSecurity(self::ENTITY_SECURITY_POST_VALIDATION, $entity, $operation, $context);

        return $this->persistProcessor->process($entity, $operation, $uriVariables, $context);
    }

    private function checkSecurity(string $key, object $entity, Operation $operation, array $context): void
    {
        $expression = $operation->getExtraProperties()[$key] ?? null;
        if (null === $expression) {
            return;
        }

        if (!$this->resourceAccessChecker->isGranted($operation->getClass(), $expression, [
            'object' => $entity,
            'previous_object' => $context['previous_data'] ?? null,
            'request' => $context['request'] ?? null,
        ])) {
            throw new AccessDeniedException($operation->getSecurityPostDenormalizeMessage() ?? 'Access Denied.');
        }
    }
}
