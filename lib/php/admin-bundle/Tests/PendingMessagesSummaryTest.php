<?php

declare(strict_types=1);

namespace Alchemy\AdminBundle\Tests;

use Alchemy\AdminBundle\Utils\PendingMessagesSummary;
use PHPUnit\Framework\TestCase;

class PendingMessagesSummaryTest extends TestCase
{
    private const string JOB_CONSUMER = PendingMessagesSummary::WORKFLOW_JOB_CONSUMER_TYPE;

    public function testEmpty(): void
    {
        $summary = new PendingMessagesSummary();

        $this->assertSame(0, $summary->getCount());
        $this->assertSame([], $summary->getGroups());
    }

    public function testGroupsByTypeSortedByCountThenType(): void
    {
        $summary = new PendingMessagesSummary();
        $summary->add('App\Consumer\Handler\Search\ESIndex', '{}');
        $summary->add('App\Consumer\Handler\File\ReadMetadata', '{}');
        $summary->add('App\Consumer\Handler\Search\ESIndex', '{}');
        $summary->add('App\Consumer\Handler\Asset\AssetDelete', '{}');
        $summary->add('App\Consumer\Handler\Search\ESIndex', '{}');
        $summary->add('App\Consumer\Handler\File\ReadMetadata', '{}');
        $summary->add('App\Consumer\Handler\Asset\AttributeChanged', '{}');

        $this->assertSame(7, $summary->getCount());
        $this->assertSame([
            ['type' => 'App\Consumer\Handler\Search\ESIndex', 'shortType' => 'ESIndex', 'detail' => null, 'count' => 3],
            ['type' => 'App\Consumer\Handler\File\ReadMetadata', 'shortType' => 'ReadMetadata', 'detail' => null, 'count' => 2],
            ['type' => 'App\Consumer\Handler\Asset\AssetDelete', 'shortType' => 'AssetDelete', 'detail' => null, 'count' => 1],
            ['type' => 'App\Consumer\Handler\Asset\AttributeChanged', 'shortType' => 'AttributeChanged', 'detail' => null, 'count' => 1],
        ], $summary->getGroups());
    }

    public function testMissingTypeIsGroupedAsUnknown(): void
    {
        $summary = new PendingMessagesSummary();
        $summary->add(null, '{}');
        $summary->add(null, 'not json');
        $summary->add('NoNamespaceMessage', '{}');

        $this->assertSame(3, $summary->getCount());
        $this->assertSame([
            ['type' => null, 'shortType' => '(unknown)', 'detail' => null, 'count' => 2],
            ['type' => 'NoNamespaceMessage', 'shortType' => 'NoNamespaceMessage', 'detail' => null, 'count' => 1],
        ], $summary->getGroups());
    }

    public function testJobConsumerIsDetailedByJobId(): void
    {
        $summary = new PendingMessagesSummary();
        $summary->add(self::JOB_CONSUMER, '{"workflowId":"w1","jobId":"thumbnail","jobStateId":"s1"}');
        $summary->add(self::JOB_CONSUMER, '{"workflowId":"w2","jobId":"thumbnail","jobStateId":"s2"}');
        $summary->add(self::JOB_CONSUMER, '{"workflowId":"w1","jobId":"read_metadata","jobStateId":"s3"}');
        $summary->add('App\Consumer\Handler\Search\ESIndex', '{"jobId":"ignored"}');

        $this->assertSame(4, $summary->getCount());
        $this->assertSame([
            ['type' => self::JOB_CONSUMER, 'shortType' => 'JobConsumer', 'detail' => 'thumbnail', 'count' => 2],
            ['type' => self::JOB_CONSUMER, 'shortType' => 'JobConsumer', 'detail' => 'read_metadata', 'count' => 1],
            ['type' => 'App\Consumer\Handler\Search\ESIndex', 'shortType' => 'ESIndex', 'detail' => null, 'count' => 1],
        ], $summary->getGroups());
    }

    public function testJobConsumerWithUnreadableBodyHasNoDetail(): void
    {
        $summary = new PendingMessagesSummary();
        $summary->add(self::JOB_CONSUMER, 'not json');
        $summary->add(self::JOB_CONSUMER, '{"workflowId":"w1"}');
        $summary->add(self::JOB_CONSUMER, '"a string"');
        $summary->add(self::JOB_CONSUMER, '{"jobId":"thumbnail"}');

        $this->assertSame(4, $summary->getCount());
        $this->assertSame([
            ['type' => self::JOB_CONSUMER, 'shortType' => 'JobConsumer', 'detail' => null, 'count' => 3],
            ['type' => self::JOB_CONSUMER, 'shortType' => 'JobConsumer', 'detail' => 'thumbnail', 'count' => 1],
        ], $summary->getGroups());
    }
}
