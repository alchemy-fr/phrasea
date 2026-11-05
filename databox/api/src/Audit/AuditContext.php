<?php

declare(strict_types=1);

namespace App\Audit;

use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Model\AuditActorTypeEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Who acts, from where, and the correlation ID grouping an action with its asynchronous effects.
 *
 * In an HTTP request, the actor is the authenticated token. Elsewhere (Messenger handlers,
 * automations, console commands) the context is set explicitly: restored from the
 * AuditContextStamp of the consumed message, or set around a block of code.
 * An explicit actor prevails over the token.
 */
final class AuditContext implements ResetInterface
{
    final public const string SOURCE_API = 'api';
    final public const string SOURCE_ADMIN = 'admin';
    final public const string SOURCE_CONSOLE = 'console';
    final public const string SOURCE_WORKER = 'worker';
    final public const string SOURCE_AUTOMATION = 'automation';
    final public const string SOURCE_INTEGRATION = 'integration';

    /**
     * @var array{actorType: int, userId: ?string, userName: ?string, impersonatorId: ?string, clientId: ?string}|null
     */
    private ?array $actor = null;

    /**
     * @var array{ip: ?string, uri: ?string, route: ?string}|null
     */
    private ?array $origin = null;

    private ?string $source = null;
    private ?string $command = null;
    private ?string $correlationId = null;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * The resolved context of the current action.
     *
     * @return array{actorType: int, userId: ?string, userName: ?string, impersonatorId: ?string, clientId: ?string, ip: ?string, uri: ?string, route: ?string, source: string, command: ?string, correlationId: string}
     */
    public function resolve(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        return [
            ...($this->actor ?? $this->resolveTokenActor()),
            ...(null !== $request ? self::resolveRequestOrigin($request) : ($this->origin ?? ['ip' => null, 'uri' => null, 'route' => null])),
            'source' => $this->source ?? (null !== $request ? self::resolveRequestSource($request) : self::SOURCE_CONSOLE),
            'command' => $this->command,
            'correlationId' => $this->getCorrelationId(),
        ];
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId ??= Uuid::uuid4()->toString();
    }

    /**
     * Applies a context resolved elsewhere (see resolve()), e.g. carried by a message.
     */
    public function restore(array $context): void
    {
        $this->actor = [
            'actorType' => (int) ($context['actorType'] ?? AuditActorTypeEnum::System->value),
            'userId' => $context['userId'] ?? null,
            'userName' => $context['userName'] ?? null,
            'impersonatorId' => $context['impersonatorId'] ?? null,
            'clientId' => $context['clientId'] ?? null,
        ];
        $this->origin = [
            'ip' => $context['ip'] ?? null,
            'uri' => $context['uri'] ?? null,
            'route' => $context['route'] ?? null,
        ];
        $this->source = $context['source'] ?? null;
        $this->command = $context['command'] ?? null;
        $this->correlationId = $context['correlationId'] ?? null;
    }

    /**
     * Runs code as the system (no actor), keeping the correlation ID.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function runAsSystem(string $source, callable $fn): mixed
    {
        return $this->runWith([
            'actorType' => AuditActorTypeEnum::System->value,
            'userId' => null,
            'userName' => null,
            'impersonatorId' => null,
            'clientId' => null,
        ], $source, $fn);
    }

    /**
     * Runs code on behalf of a user, keeping the correlation ID.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function runAsUser(string $userId, ?string $userName, string $source, callable $fn): mixed
    {
        return $this->runWith([
            'actorType' => AuditActorTypeEnum::User->value,
            'userId' => $userId,
            'userName' => $userName,
            'impersonatorId' => null,
            'clientId' => null,
        ], $source, $fn);
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function runWith(array $actor, string $source, callable $fn): mixed
    {
        $correlationId = $this->getCorrelationId();
        $previous = $this->save();
        $this->actor = $actor;
        $this->source = $source;

        try {
            return $fn();
        } finally {
            $this->load($previous);
            $this->correlationId ??= $correlationId;
        }
    }

    /**
     * Starts a new console command: the system acts, under a new correlation ID.
     */
    public function startCommand(string $command): void
    {
        $this->reset();
        $this->source = self::SOURCE_CONSOLE;
        $this->command = $command;
    }

    /**
     * @return array<string, mixed> the raw state, to put back with load()
     */
    public function save(): array
    {
        return [
            'actor' => $this->actor,
            'origin' => $this->origin,
            'source' => $this->source,
            'command' => $this->command,
            'correlationId' => $this->correlationId,
        ];
    }

    public function load(array $state): void
    {
        $this->actor = $state['actor'] ?? null;
        $this->origin = $state['origin'] ?? null;
        $this->source = $state['source'] ?? null;
        $this->command = $state['command'] ?? null;
        $this->correlationId = $state['correlationId'] ?? null;
    }

    public function reset(): void
    {
        $this->load([]);
    }

    /**
     * @return array{actorType: int, userId: ?string, userName: ?string, impersonatorId: ?string, clientId: ?string}
     */
    private function resolveTokenActor(): array
    {
        $user = $this->security->getUser();
        if ($user instanceof JwtUser) {
            return [
                'actorType' => AuditActorTypeEnum::User->value,
                'userId' => $user->getId(),
                'userName' => $user->getUsername(),
                'impersonatorId' => $user->getImpersonatorId(),
                'clientId' => null,
            ];
        }
        if ($user instanceof JwtOauthClient) {
            return [
                'actorType' => AuditActorTypeEnum::OAuthClient->value,
                'userId' => null,
                'userName' => null,
                'impersonatorId' => null,
                'clientId' => $user->getId(),
            ];
        }
        if (null !== $user) {
            return [
                'actorType' => AuditActorTypeEnum::User->value,
                'userId' => $user->getUserIdentifier(),
                'userName' => $user->getUserIdentifier(),
                'impersonatorId' => null,
                'clientId' => null,
            ];
        }

        return [
            'actorType' => AuditActorTypeEnum::System->value,
            'userId' => null,
            'userName' => null,
            'impersonatorId' => null,
            'clientId' => null,
        ];
    }

    /**
     * @return array{ip: ?string, uri: ?string, route: ?string}
     */
    private static function resolveRequestOrigin(Request $request): array
    {
        return [
            'ip' => $request->getClientIp(),
            'uri' => substr($request->getUri(), strlen($request->getSchemeAndHttpHost().$request->getBaseUrl())),
            'route' => $request->attributes->get('_route'),
        ];
    }

    private static function resolveRequestSource(Request $request): string
    {
        return str_starts_with($request->getPathInfo(), '/admin') ? self::SOURCE_ADMIN : self::SOURCE_API;
    }
}
