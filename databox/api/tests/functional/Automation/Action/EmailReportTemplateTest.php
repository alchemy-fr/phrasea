<?php

declare(strict_types=1);

namespace App\Tests\Functional\Automation\Action;

use Alchemy\NotifierBundle\Channel\ChannelType;
use Alchemy\NotifierBundle\Notification\NotificationRenderer;
use App\Automation\Action\EmailReportAction;
use App\Automation\AutomationRunContext;
use App\Model\AutomationScopeEnum;
use App\Model\AutomationTriggerEnum;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EmailReportTemplateTest extends KernelTestCase
{
    private NotificationRenderer $renderer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->renderer = self::getContainer()->get(NotificationRenderer::class);
    }

    private static function createParams(int $total, array $assets, AutomationScopeEnum $scope = AutomationScopeEnum::New, ?string $subject = null): array
    {
        return EmailReportAction::buildParams($assets, $total, ['subject' => $subject], new AutomationRunContext(
            runId: 'run-id',
            automationId: 'automation-id',
            automationName: 'Daily <report>',
            savedSearchId: 'search-id',
            savedSearchName: 'Holidays',
            ownerId: 'owner-id',
            trigger: AutomationTriggerEnum::Schedule,
            dryRun: false,
            scope: $scope,
        ));
    }

    private static function createAssets(): array
    {
        return [
            ['id' => 'asset-1', 'title' => 'Beach & sun', 'workspace' => 'Photos', 'createdAt' => '2026-10-18T10:00:00+00:00'],
            ['id' => 'asset-2', 'title' => null, 'workspace' => 'Photos', 'createdAt' => null],
        ];
    }

    public function testRenderEmail(): void
    {
        $rendered = $this->renderer->render(EmailReportAction::TOPIC, ChannelType::Email, self::createParams(5, self::createAssets()) + [
            'recipient' => ['displayName' => 'John', 'email' => 'john@example.com'],
        ], 'en');

        $this->assertSame('5 new assets in Holidays (Daily <report>)', $rendered->subject);
        $body = $rendered->body;
        $this->assertStringContainsString('Hi John,', $body);
        $this->assertStringContainsString('<strong>Daily &lt;report&gt;</strong> found 5 new assets', $body);
        $this->assertStringContainsString('/notification-uri?uri=%2Fsaved-searches%2Fsearch-id">Holidays</a>', html_entity_decode($body));
        $this->assertStringContainsString('/notification-uri?uri=%2Fassets%2Fasset-1">Beach', html_entity_decode($body));
        $this->assertStringContainsString('Beach &amp; sun</a> — Photos — 2026-10-18', $body);
        $this->assertStringContainsString('Untitled</a> — Photos</li>', $body);
        $this->assertStringContainsString('…and 3 more.', $body);
    }

    public function testRenderEmailWithCustomSubjectToAnAddress(): void
    {
        $rendered = $this->renderer->render(EmailReportAction::TOPIC, ChannelType::Email, self::createParams(1, [self::createAssets()[0]], AutomationScopeEnum::All, 'Weekly & daily') + [
            'recipient' => ['email' => 'john@example.com', 'locale' => null],
        ], 'fr');

        $this->assertSame('Weekly & daily', $rendered->subject);
        $this->assertStringContainsString('Bonjour john@example.com,', $rendered->body);
        $this->assertStringContainsString('a traité 1 asset de la recherche enregistrée', $rendered->body);
        $this->assertStringNotContainsString('de plus', $rendered->body);
    }

    public function testRenderEmptyEmail(): void
    {
        $rendered = $this->renderer->render(EmailReportAction::TOPIC, ChannelType::Email, self::createParams(0, []) + [
            'recipient' => ['displayName' => 'John'],
        ], 'en');

        $this->assertSame('No new asset in Holidays (Daily <report>)', $rendered->subject);
        $this->assertStringContainsString('found no new asset', $rendered->body);
        $this->assertStringNotContainsString('<ul>', $rendered->body);
    }

    public function testRenderInApp(): void
    {
        $rendered = $this->renderer->render(EmailReportAction::TOPIC, ChannelType::InApp, self::createParams(5, self::createAssets()), 'en');

        $this->assertSame('5 new assets in Holidays', $rendered->subject);
        $this->assertSame('Daily &lt;report&gt;: 5 new assets in Holidays: Beach &amp; sun, Untitled …and 3 more.', $rendered->body);
        $this->assertSame('/saved-searches/search-id', $rendered->uri);
    }
}
