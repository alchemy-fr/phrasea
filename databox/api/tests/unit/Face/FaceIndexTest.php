<?php

declare(strict_types=1);

namespace App\Tests\Unit\Face;

use Alchemy\ESBundle\Indexer\IndexPersister;
use App\Entity\Core\Attribute;
use App\Integration\Core\FaceRecognition\FaceIndex;
use App\Integration\Core\FaceRecognition\FaceIndexPersister;
use Elastica\Index;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use PHPUnit\Framework\TestCase;

class FaceIndexTest extends TestCase
{
    public function testNothingIsWrittenNorRefreshedBeforeTheIndexExists(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('exists')->willReturn(false);
        $index->expects($this->never())->method('refresh');
        $inner = $this->createMock(ObjectPersisterInterface::class);
        $inner->expects($this->never())->method($this->anything());

        $this->createFaceIndex($index, $inner)->update([self::face()], ['deleted-face']);
    }

    public function testTheWrittenFacesAreSearchableOnceTheIndexExists(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('exists')->willReturn(true);
        $index->expects($this->once())->method('refresh');
        $inner = $this->createMock(ObjectPersisterInterface::class);
        $inner->expects($this->once())->method('replaceMany');
        $inner->expects($this->once())->method('deleteManyByIdentifiers')->with(['deleted-face']);

        $this->createFaceIndex($index, $inner)->update([self::face()], ['deleted-face']);
    }

    private function createFaceIndex(Index $index, ObjectPersisterInterface $inner): FaceIndex
    {
        $persister = new FaceIndexPersister($inner, $index);

        return new FaceIndex($index, new IndexPersister([Attribute::class => ['face' => $persister]]), $persister);
    }

    private static function face(): Attribute
    {
        $face = new Attribute();
        $face->setEmbedding(['m' => 'mock', 'v' => [1.0, 0.0]]);

        return $face;
    }
}
