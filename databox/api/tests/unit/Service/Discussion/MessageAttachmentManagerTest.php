<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Discussion;

use App\Service\Discussion\MessageAttachmentManager;
use PHPUnit\Framework\TestCase;

class MessageAttachmentManagerTest extends TestCase
{
    public function testPartitionAttachmentsByContentId(): void
    {
        $file = ['type' => 'file', 'content' => '{"id":"f1","name":"a.pdf"}'];
        $otherFile = ['type' => 'file', 'content' => '{"id":"f2","name":"b.pdf"}'];
        $annotation = ['type' => 'annotation', 'content' => '{"id":"an1","type":"point"}'];
        $withoutId = ['type' => 'custom', 'content' => '{"foo":"bar"}'];
        $invalid = ['type' => 'custom', 'content' => 'not json'];

        [$kept, $removed] = MessageAttachmentManager::partitionAttachments(
            [$file, $otherFile, $annotation, $withoutId, $invalid],
            ['f1', 'an1', 'unknown'],
        );

        $this->assertSame([$otherFile, $withoutId, $invalid], $kept);
        $this->assertSame([$file, $annotation], $removed);
    }

    public function testPartitionAttachmentsWithoutIds(): void
    {
        $file = ['type' => 'file', 'content' => '{"id":"f1"}'];

        $this->assertSame([[$file], []], MessageAttachmentManager::partitionAttachments([$file], []));
        $this->assertSame([[], []], MessageAttachmentManager::partitionAttachments([], ['f1']));
    }
}
