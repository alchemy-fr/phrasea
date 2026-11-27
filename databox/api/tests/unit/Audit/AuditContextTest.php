<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use Alchemy\AuthBundle\Security\JwtUser;
use App\Audit\AuditContext;
use App\Model\AuditActorTypeEnum;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditContextTest extends TestCase
{
    private function resolveActorType(?object $user, ?Request $request): int
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return new AuditContext($security, $requestStack)->resolve()['actorType'];
    }

    public function testAnHttpRequestWithoutUserIsAnonymous(): void
    {
        $this->assertSame(AuditActorTypeEnum::Anonymous->value, $this->resolveActorType(null, Request::create('/s/1/unlock', 'POST')));
    }

    public function testOutsideOfARequestTheSystemActs(): void
    {
        $this->assertSame(AuditActorTypeEnum::System->value, $this->resolveActorType(null, null));
    }

    public function testTheEndpointsOfExternalServicesActAsTheSystem(): void
    {
        $request = Request::create('/integrations/uploader/1/incoming-commit', 'POST');
        $request->attributes->set(AuditContext::SYSTEM_ROUTE_ATTRIBUTE, true);

        $this->assertSame(AuditActorTypeEnum::System->value, $this->resolveActorType(null, $request));
    }

    public function testASignedInUserActs(): void
    {
        $this->assertSame(AuditActorTypeEnum::User->value, $this->resolveActorType(new JwtUser('jwt', 'u1', 'alice'), Request::create('/assets')));
    }
}
