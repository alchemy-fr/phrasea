<?php

declare(strict_types=1);

namespace App\Tests\Functional\Notification;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Email\EmailLayoutRenderer;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;

final class ExceptionEmailTemplateTest extends KernelTestCase
{
    private const string TOPIC = 'user:exception';

    private NotificationRenderer $renderer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->renderer = self::getContainer()->get(NotificationRenderer::class);
    }

    private static function createParams(): array
    {
        return [
            'subject' => 'Integration "alchemy <rnd>" error',
            'message' => 'Invalid timeout (in workspace: alchemy)',
            'details' => [
                ['key' => 'instance', 'value' => 'https://databox.example.com'],
                ['key' => 'workspace', 'value' => 'alchemy'],
                ['key' => 'integration', 'value' => 'alchemy <rnd>'],
                ['key' => 'integration_type', 'value' => 'Remove BG'],
            ],
            'url' => '/workspaces/ws-1/manage/integrations/wi-1',
            'action' => 'edit_integration',
            'recipient' => ['displayName' => 'John', 'email' => 'john@example.com'],
        ];
    }

    public function testRenderEmail(): void
    {
        $rendered = $this->renderer->render(self::TOPIC, ChannelType::Email, self::createParams(), 'en');

        $body = $rendered->body;
        $this->assertStringContainsString('Hi John,', $body);
        $this->assertStringContainsString('<pre>Invalid timeout (in workspace: alchemy)</pre>', $body);
        foreach (['Instance' => 'https://databox.example.com', 'Workspace' => 'alchemy', 'Integration' => 'alchemy &lt;rnd&gt;', 'Integration type' => 'Remove BG'] as $label => $value) {
            $this->assertMatchesRegularExpression(sprintf('#>%s</td>\s*<td[^>]*>%s</td>#', preg_quote($label, '#'), preg_quote($value, '#')), $body);
        }
        $this->assertStringNotContainsString('alchemy <rnd>', $body);
        $this->assertSame('/workspaces/ws-1/manage/integrations/wi-1', $rendered->uri);
        $this->assertSame('Edit the integration', $rendered->action);

        $email = new Email();
        self::getContainer()->get(EmailLayoutRenderer::class)->apply($email, $rendered, 'en');
        $html = html_entity_decode($email->getHtmlBody());
        $this->assertStringContainsString('/notification-uri?uri=%2Fworkspaces%2Fws-1%2Fmanage%2Fintegrations%2Fwi-1', $html);
        $this->assertStringContainsString('Edit the integration</a>', $html);
    }

    public function testRenderEmailInFrench(): void
    {
        $rendered = $this->renderer->render(self::TOPIC, ChannelType::Email, self::createParams(), 'fr');

        $this->assertStringContainsString('>Espace de travail</td>', $rendered->body);
        $this->assertStringContainsString('>Type d\'intégration</td>', html_entity_decode($rendered->body, ENT_QUOTES));
        $this->assertSame('Modifier l\'intégration', html_entity_decode($rendered->action, ENT_QUOTES));
    }

    public function testRenderEmailWithoutLink(): void
    {
        $rendered = $this->renderer->render(self::TOPIC, ChannelType::Email, [
            'subject' => 'Automation error',
            'message' => 'Failure',
            'recipient' => ['displayName' => 'John', 'email' => 'john@example.com'],
        ], 'en');

        $this->assertStringNotContainsString('<table', $rendered->body);
        $this->assertNull($rendered->uri);
        $this->assertNull($rendered->action);

        $email = new Email();
        self::getContainer()->get(EmailLayoutRenderer::class)->apply($email, $rendered, 'en');
        $this->assertStringNotContainsString('notification-uri', $email->getHtmlBody());
    }

    public function testRenderInApp(): void
    {
        $rendered = $this->renderer->render(self::TOPIC, ChannelType::InApp, self::createParams(), 'en');

        $this->assertSame('/workspaces/ws-1/manage/integrations/wi-1', $rendered->uri);
    }
}
