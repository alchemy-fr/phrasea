<?php

declare(strict_types=1);

namespace App\Filter;

use Alchemy\AclBundle\Entity\AccessControlEntryRepository;
use Alchemy\AclBundle\Security\PermissionInterface;
use Alchemy\AuthBundle\Security\JwtUser;
use Alchemy\AuthBundle\Security\Traits\SecurityAwareTrait;
use Alchemy\AuthBundle\Security\Voter\ScopeVoter;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Parameter;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use ApiPlatform\State\ParameterNotFound;
use App\Security\ScopeInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Applies the publication query parameter it is declared on (see the keys of PARAMETERS).
 *
 * The "flatten" parameter must default to false: it lists the root publications
 * when neither "parentId" nor "flatten" is requested.
 */
final class PublicationFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use SecurityAwareTrait;

    private const array PARAMETERS = [
        'flatten' => ['boolean', 'Get all the publications, regardless the hierarchy'],
        'parentId' => ['string', 'Get children of this publication'],
        'profileId' => ['string', 'Filter by profile'],
        'mine' => ['boolean', 'Get publications which the current authenticated user owns'],
        'editable' => ['boolean', 'Get publications the current authenticated user can edit'],
        'expired' => ['boolean', 'Get publications which expiration date has passed'],
        'empty' => ['boolean', 'Get publications with no asset'],
        'disabled' => ['boolean', 'Get publications which are not enabled'],
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $parameter = $context['parameter'];
        $key = $parameter->getKey();
        $value = $parameter->getValue();

        switch ($key) {
            case 'parentId':
                if (!empty($value)) {
                    $queryBuilder
                        ->andWhere('o.parent = :parentId')
                        ->setParameter('parentId', $value);
                }
                break;
            case 'flatten':
                $parentId = $operation?->getParameters()?->get('parentId')?->getValue();
                if (($parentId instanceof ParameterNotFound || empty($parentId))
                    && true !== $this->normalizeBoolValue($value, $key)) {
                    $queryBuilder->andWhere('o.parent IS NULL');
                }
                break;
            case 'profileId':
                if (!empty($value)) {
                    $queryBuilder
                        ->andWhere('o.profile = :profileId')
                        ->setParameter('profileId', $value);
                }
                break;
            case 'expired':
                if (true === $this->normalizeBoolValue($value, $key)) {
                    if (!in_array('p', $queryBuilder->getAllAliases())) {
                        $queryBuilder->leftJoin('o.profile', 'p');
                    }
                    $queryBuilder
                        ->andWhere('(o.config.expiresAt < :expiresNow OR (o.config.expiresAt IS NULL AND p.config.expiresAt < :expiresNow))')
                        ->setParameter('expiresNow', date('Y-m-d H:i:s'));
                }
                break;
            case 'empty':
                if (true === $this->normalizeBoolValue($value, $key)) {
                    $queryBuilder
                        ->leftJoin('o.assets', 'pa')
                        ->andWhere('pa.id IS NULL');
                }
                break;
            case 'disabled':
                if (true === $this->normalizeBoolValue($value, $key)) {
                    if (!in_array('p', $queryBuilder->getAllAliases())) {
                        $queryBuilder->leftJoin('o.profile', 'p');
                    }

                    $queryBuilder
                        ->andWhere('(o.config.enabled = false OR (p.id IS NOT NULL AND p.config.enabled = false))');
                }
                break;
            case 'mine':
                if (true === $this->normalizeBoolValue($value, $key)) {
                    $user = $this->security->getUser();
                    if (!$user instanceof JwtUser) {
                        throw new AuthenticationException('User must be authenticated');
                    }
                    $queryBuilder
                        ->andWhere('o.ownerId = :me')
                        ->setParameter('me', $user->getId());
                }
                break;
            case 'editable':
                if (true === $this->normalizeBoolValue($value, $key)) {
                    $this->applyEditable($queryBuilder);
                }
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unsupported publication filter parameter "%s"', $key));
        }
    }

    private function applyEditable(QueryBuilder $queryBuilder): void
    {
        if (
            $this->security->isGranted(JwtUser::ROLE_ADMIN)
            || $this->security->isGranted(ScopeVoter::PREFIX.ScopeInterface::SCOPE_PUBLISH)
        ) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof JwtUser) {
            throw new AuthenticationException('User must be authenticated');
        }
        if (!in_array('ace', $queryBuilder->getAllAliases(), true)) {
            AccessControlEntryRepository::joinAcl(
                $queryBuilder,
                $user->getId(),
                $user->getGroups(),
                'publication',
                'o',
                PermissionInterface::EDIT,
                false
            );
        }

        $aclConditions = [
            'o.ownerId = :uid',
            'ace.id IS NOT NULL',
        ];
        $queryBuilder->andWhere(implode(' OR ', $aclConditions));
    }

    public function getOpenApiParameters(Parameter $parameter): OpenApiParameter
    {
        [$type, $description] = self::PARAMETERS[$parameter->getKey()];

        return new OpenApiParameter(
            name: $parameter->getKey(),
            in: 'query',
            description: $description,
            schema: ['type' => $type],
            explode: false,
        );
    }

    private function normalizeBoolValue(mixed $value, string $property): ?bool
    {
        if (in_array($value, [true, 'true', '1'], true)) {
            return true;
        }

        if (in_array($value, [false, 'false', '0'], true)) {
            return false;
        }

        $this->logger->notice('Invalid filter ignored', [
            'exception' => new InvalidArgumentException(sprintf('Invalid boolean value for "%s" property, expected one of ( "%s" )', $property, implode('" | "', [
                'true',
                'false',
                '1',
                '0',
            ]))),
        ]);

        return null;
    }
}
