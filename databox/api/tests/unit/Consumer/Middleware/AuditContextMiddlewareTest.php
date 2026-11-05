<?php

declare(strict_types=1);

namespace App\Tests\Unit\Consumer\Middleware;

use Alchemy\AuthBundle\Security\JwtOauthClient;
use Alchemy\AuthBundle\Security\JwtUser;
use App\Audit\AuditContext;
use App\Consumer\Middleware\AuditContextMiddleware;
use App\Consumer\Stamp\AuditContextStamp;
use App\Model\AuditActorTypeEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

class AuditContextMiddlewareTest extends TestCase
{
    private const string USER_ID = '12345678-fc1a-492b-9e76-c2e8e6979786';
    private const string ADMIN_ID = '42424242-fc1a-492b-9e76-c2e8e6979786';

    public function testTheDispatchingContextIsCapturedAtSending(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://api.test/assets/move'));
        $context = $this->createContext(new JwtUser('jwt', self::USER_ID, 'alice', impersonatorId: self::ADMIN_ID), $requestStack);
        $middleware = new AuditContextMiddleware($context);

        $envelope = $middleware->handle(new Envelope(new \stdClass()), $this->createStack());

        $stamp = $envelope->last(AuditContextStamp::class);
        $this->assertInstanceOf(AuditContextStamp::class, $stamp);
        $this->assertSame(AuditActorTypeEnum::User->value, $stamp->context['actorType']);
        $this->assertSame(self::USER_ID, $stamp->context['userId']);
        $this->assertSame('alice', $stamp->context['userName']);
        $this->assertSame(self::ADMIN_ID, $stamp->context['impersonatorId']);
        $this->assertSame('/assets/move', $stamp->context['uri']);
        $this->assertSame(AuditContext::SOURCE_API, $stamp->context['source']);
        $this->assertSame($context->getCorrelationId(), $stamp->context['correlationId']);
    }

    public function testAnOAuthClientIsCapturedAsSuch(): void
    {
        $context = $this->createContext(new JwtOauthClient('jwt', 'machine_client'));
        $envelope = new AuditContextMiddleware($context)->handle(new Envelope(new \stdClass()), $this->createStack());

        $stamp = $envelope->last(AuditContextStamp::class);
        $this->assertSame(AuditActorTypeEnum::OAuthClient->value, $stamp->context['actorType']);
        $this->assertNull($stamp->context['userId']);
        $this->assertSame('machine_client', $stamp->context['clientId']);
    }

    public function testAnExistingStampIsKeptAtSending(): void
    {
        $context = $this->createContext(new JwtUser('jwt', self::ADMIN_ID, 'admin'));
        $stamp = new AuditContextStamp(['userId' => self::USER_ID]);

        $envelope = new AuditContextMiddleware($context)->handle(new Envelope(new \stdClass(), [$stamp]), $this->createStack());

        $this->assertSame([$stamp], $envelope->all(AuditContextStamp::class));
    }

    public function testTheContextIsRestoredDuringTheHandlerThenCleaned(): void
    {
        $context = $this->createContext(null);
        $previousCorrelationId = $context->getCorrelationId();

        $seen = null;
        $stamp = new AuditContextStamp([
            'actorType' => AuditActorTypeEnum::User->value,
            'userId' => self::USER_ID,
            'userName' => 'alice',
            'impersonatorId' => null,
            'clientId' => null,
            'ip' => '10.0.0.1',
            'uri' => '/assets/move',
            'route' => '_api_/assets/move_post',
            'source' => AuditContext::SOURCE_API,
            'command' => null,
            'correlationId' => 'c0ffee00-0000-4000-8000-000000000000',
        ]);
        $envelope = new Envelope(new \stdClass(), [$stamp, new ConsumedByWorkerStamp()]);

        new AuditContextMiddleware($context)->handle($envelope, $this->createStack(static function () use ($context, &$seen): void {
            $seen = $context->resolve();
        }));

        $this->assertSame(AuditActorTypeEnum::User->value, $seen['actorType']);
        $this->assertSame(self::USER_ID, $seen['userId']);
        $this->assertSame('alice', $seen['userName']);
        $this->assertSame('10.0.0.1', $seen['ip']);
        $this->assertSame('/assets/move', $seen['uri']);
        $this->assertSame(AuditContext::SOURCE_WORKER, $seen['source']);
        $this->assertSame('c0ffee00-0000-4000-8000-000000000000', $seen['correlationId']);

        $after = $context->resolve();
        $this->assertSame(AuditActorTypeEnum::System->value, $after['actorType']);
        $this->assertNull($after['userId']);
        $this->assertSame($previousCorrelationId, $after['correlationId']);
    }

    public function testTheContextIsCleanedWhenTheHandlerFails(): void
    {
        $context = $this->createContext(null);
        $envelope = new Envelope(new \stdClass(), [
            new AuditContextStamp(['actorType' => AuditActorTypeEnum::User->value, 'userId' => self::USER_ID]),
            new ConsumedByWorkerStamp(),
        ]);

        try {
            new AuditContextMiddleware($context)->handle($envelope, $this->createStack(static function (): void {
                throw new \RuntimeException('Handler failure');
            }));
            $this->fail('The handler exception must be thrown');
        } catch (\RuntimeException) {
        }

        $this->assertNull($context->resolve()['userId']);
    }

    public function testAMessageConsumedWithoutStampIsHandledByTheSystem(): void
    {
        // A token left by a previous message must not be the actor
        $context = $this->createContext(new JwtUser('jwt', self::USER_ID, 'alice'));
        $context->restore(['actorType' => AuditActorTypeEnum::User->value, 'userId' => self::USER_ID]);

        $seen = null;
        new AuditContextMiddleware($context)->handle(new Envelope(new \stdClass(), [new ConsumedByWorkerStamp()]), $this->createStack(static function () use ($context, &$seen): void {
            $seen = $context->resolve();
        }));

        $this->assertSame(AuditActorTypeEnum::System->value, $seen['actorType']);
        $this->assertNull($seen['userId']);
        $this->assertSame(AuditContext::SOURCE_WORKER, $seen['source']);
        $this->assertNotEmpty($seen['correlationId']);
    }

    private function createContext(?object $user, ?RequestStack $requestStack = null): AuditContext
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new AuditContext($security, $requestStack ?? new RequestStack());
    }

    private function createStack(?\Closure $handler = null): StackInterface
    {
        return new StackMiddleware(new class($handler) implements MiddlewareInterface {
            public function __construct(private readonly ?\Closure $handler)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                if (null !== $this->handler) {
                    ($this->handler)();
                }

                return $envelope;
            }
        });
    }
}
