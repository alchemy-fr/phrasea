<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock;
use Alchemy\TestBundle\Helper\TestServicesTrait;
use App\Entity\Core\Workspace;
use App\Entity\Integration\IntegrationToken;
use App\Entity\Integration\WorkspaceIntegration;
use App\Entity\Log\AuditLog;
use App\Integration\Phrasea\Uploader\UploaderIntegration;
use App\Model\AuditLogActionEnum;
use App\Service\Workspace\WorkspaceCreator;
use Doctrine\DBAL\Connection;

/**
 * The admins are granted every action by the AdminVoter: the CRUD controllers refuse the
 * write actions themselves.
 */
class ReadOnlyAdminTest extends AbstractDataboxAdminTestCase
{
    use TestServicesTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->getAuthAdminUser(), 'admin');
    }

    public function testAuditLogCannotBeWritten(): void
    {
        $log = new AuditLog();
        $log->setAction(AuditLogActionEnum::AssetMoved);
        $em = $this->getEntityManager();
        $em->persist($log);
        $em->flush();
        $id = $log->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/audit-log/%s', $id));
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('.action-edit, .action-delete'));
        $token = $crawler->filter('#action-confirmation-form input[name="token"]');

        $this->client->request('GET', sprintf('/admin/audit-log/%s/edit', $id));
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/audit-log/new');
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('POST', sprintf('/admin/audit-log/%s/delete', $id), [
            'token' => $token->count() > 0 ? $token->attr('value') : '',
        ]);
        $this->assertResponseStatusCodeSame(403);

        $this->assertSame(1, (int) self::getService(Connection::class)->fetchOne('SELECT COUNT(*) FROM audit_log'));
    }

    public function testIntegrationTokenCanBeDeletedButNotEdited(): void
    {
        $id = $this->createIntegrationToken();

        $crawler = $this->client->request('GET', sprintf('/admin/integration-token/%s', $id));
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('.action-edit'));
        $this->assertCount(1, $crawler->filter('.action-delete'));

        $this->client->request('GET', sprintf('/admin/integration-token/%s/edit', $id));
        $this->assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/integration-token/new');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', $crawler->filter('.action-delete')->attr('href'), [
            'token' => $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value'),
        ]);
        $this->assertResponseRedirects();
        $this->assertSame(0, (int) self::getService(Connection::class)->fetchOne('SELECT COUNT(*) FROM integration_token'));
    }

    private function createIntegrationToken(): string
    {
        $em = $this->getEntityManager();

        $workspace = new Workspace();
        $workspace->setName('Read-only admin');
        $workspace->setSlug('read-only-admin');
        $workspace->setOwnerId('custom_owner');
        $workspace->setEnabledLocales(['en']);
        self::getService(WorkspaceCreator::class)->createWorkspace($workspace);

        $integration = new WorkspaceIntegration();
        $integration->setWorkspace($workspace);
        $integration->setOwnerId(KeycloakClientTestMock::USER_UID);
        $integration->setPublic(false);
        $integration->setIntegration(UploaderIntegration::getName());
        $integration->setConfig([]);
        $em->persist($integration);

        $token = new IntegrationToken();
        $token->setIntegration($integration);
        $token->setUserId(KeycloakClientTestMock::USER_UID);
        $token->setToken(['access_token' => 'access']);
        $token->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $em->persist($token);
        $em->flush();

        return $token->getId();
    }
}
