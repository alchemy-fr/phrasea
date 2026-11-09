<?php

declare(strict_types=1);

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Databox\DataboxCatalog;
use App\Entity\Target;
use App\Form\Builder\FieldType;
use App\Form\Builder\FormDefinition;
use App\Form\FormSchemaManager;
use App\Security\Voter\TargetVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The values a user may pick for an entity or tag field of the target's form.
 * Only the lists the form offers can be read through it.
 */
final readonly class FormFieldOptionsProvider implements ProviderInterface
{
    use DataboxResponseTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private FormSchemaManager $schemaManager,
        private DataboxCatalog $catalog,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        /** @var Request $request */
        $request = $context['request'];

        $id = (string) $uriVariables['id'];
        $target = $this->em->find(Target::class, $id);
        if (!$target instanceof Target) {
            throw new NotFoundHttpException(sprintf('Target "%s" not found', $id));
        }
        if (!$this->security->isGranted(TargetVoter::READ, $target)) {
            throw new AccessDeniedHttpException('Cannot read this target');
        }

        $formSchema = $this->schemaManager->loadSchema($target->getId(), $request->getLocale());
        if (!$formSchema?->isBuilder()) {
            throw new NotFoundHttpException('The target form has no such field');
        }
        $definition = FormDefinition::fromArray($formSchema->getData());
        $field = $definition->getField((string) $uriVariables['key']);
        if (null === $field || $field->hidden) {
            throw new NotFoundHttpException('The target form has no such field');
        }

        $query = self::optionsQuery($request);

        return match ($field->type) {
            FieldType::Entity => self::fromDatabox(fn (): array => $this->catalog->getEntityOptions(
                (string) $field->entityListId,
                $query['query'],
                $query['ids'],
                $query['page'],
                $request->getLocale(),
            )),
            FieldType::Tag => self::fromDatabox(fn (): array => $this->catalog->getTagOptions(
                (string) $definition->workspaceId,
                $query['query'],
                $query['ids'],
                $query['page'],
            )),
            default => throw new NotFoundHttpException('The field has no Databox values'),
        };
    }
}
