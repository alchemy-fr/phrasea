<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Api\Model\Output\ApiMetaWrapperOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The compiled ES query exposes what the user is not told otherwise: the
 * attribute filter rules targeting them with their literal values, every
 * allowed workspace, their groups and collection paths. It is only returned
 * in debug mode or to ROLE_TECH.
 */
final readonly class SearchDebugMeta
{
    public const string META_KEY = 'debug:es';

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->debug || $this->authorizationChecker->isGranted(JwtUser::ROLE_TECH);
    }

    public function apply(ApiMetaWrapperOutput $response, array $queryJson, float $searchTime): void
    {
        if (!$this->isAllowed()) {
            return;
        }

        $response->setMeta(self::META_KEY, [
            'query' => $queryJson,
            'time' => $searchTime,
        ]);
    }
}
