<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Group;
use App\Entity\User;
use App\User\GroupMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

class GroupMapperTest extends TestCase
{
    public function testGroupMapper(): void
    {
        $groupStore = [];
        foreach (['B', 'C'] as $g) {
            $groupStore[] = [['name' => $g], null, (new Group())->setName($g)];
        }

        /** @var EntityManagerInterface|MockBuilder $stubEm */
        $stubEm = $this->createMock(EntityManagerInterface::class);
        $stubEm->expects($this->once())
            ->method('persist');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->exactly(2))
            ->method('findOneBy')
            ->will($this->returnValueMap($groupStore));

        $stubEm->expects($this->exactly(2))
            ->method('getRepository')
            ->willReturn($repo);

        $groupMapper = new GroupMapper($stubEm, [
                'foo' => [
                '1A' => 'A',
                '1B' => 'B',
                '1C' => 'C',
                '1D' => 'D',
            ],
        ]);

        $user = new User();
        $groupA = new Group();
        $groupA->setName('A');
        $user->addGroup($groupA);

        $groupMapper->updateGroups('foo', $user, [
            '1B',
            '1C',
        ]);

        self::assertEquals([
            'B',
            'C',
        ], array_values($user->getIndexedGroups()));
    }
}
