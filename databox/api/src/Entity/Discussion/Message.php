<?php

declare(strict_types=1);

namespace App\Entity\Discussion;

use Alchemy\CoreBundle\Entity\AbstractUuidEntity;
use Alchemy\CoreBundle\Entity\Traits\CreatedAtTrait;
use Alchemy\CoreBundle\Entity\Traits\UpdatedAtTrait;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Api\Filter\ExactSearchFilter;
use App\Api\Model\Input\EditThreadMessageInput;
use App\Api\Model\Input\ThreadMessageInput;
use App\Api\Model\Output\ThreadMessageOutput;
use App\Api\Processor\PostMessageProcessor;
use App\Api\Processor\PutMessageProcessor;
use App\Api\Provider\ThreadMessagesProvider;
use App\Entity\Core\File;
use App\Repository\Discussion\MessageRepository;
use App\Security\Voter\AbstractVoter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ApiResource(
    shortName: 'message',
    operations: [
        new Get(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::READ.'", object)',
        ),
        new Post(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            input: ThreadMessageInput::class,
            processor: PostMessageProcessor::class,
        ),
        new Patch(
            normalizationContext: [
                'groups' => [self::GROUP_READ],
            ],
            security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
            input: EditThreadMessageInput::class,
            processor: PutMessageProcessor::class,
        ),
        new Delete(
            security: 'is_granted("'.AbstractVoter::DELETE.'", object)',
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    output: ThreadMessageOutput::class,
)]
#[ApiResource(
    uriTemplate: '/threads/{threadId}/messages',
    shortName: 'message',
    operations: [
        new GetCollection(
            provider: ThreadMessagesProvider::class,
            parameters: [
                'thread' => new QueryParameter(
                    filter: ExactSearchFilter::class,
                    property: 'thread',
                ),
                'thread[]' => new QueryParameter(property: 'thread', openApi: false),
            ],
        ),
    ],
    uriVariables: [
        'threadId' => new Link(
            toProperty: 'thread',
            fromClass: Thread::class
        ),
    ],
    normalizationContext: [
        'groups' => [self::GROUP_LIST],
    ],
    order: ['createdAt' => 'ASC'],
)]
#[ORM\Table]
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message extends AbstractUuidEntity
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    final public const string GROUP_READ = 'message:r';
    final public const string GROUP_LIST = 'message:i';
    final public const string GROUP_WRITE = 'message:w';

    #[ORM\ManyToOne(targetEntity: Thread::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Thread $thread = null;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: false)]
    private ?string $authorId = null;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private ?string $content = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attachments = null;

    /**
     * The files of the file $attachments, which only hold their ID in their JSON
     * content: this relation is what keeps them from being removed as orphans.
     *
     * @var DoctrineCollection<int, File>
     */
    #[ORM\ManyToMany(targetEntity: File::class)]
    #[ORM\JoinTable(name: 'message_attachment_file')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private DoctrineCollection $files;

    public function __construct(UuidInterface|string|null $id = null)
    {
        parent::__construct($id);
        $this->files = new ArrayCollection();
    }

    public function getThread(): ?Thread
    {
        return $this->thread;
    }

    public function setThread(?Thread $thread): void
    {
        $this->thread = $thread;
    }

    public function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    public function setAuthorId(?string $authorId): void
    {
        $this->authorId = $authorId;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): void
    {
        $this->attachments = $attachments;
    }

    /**
     * @return DoctrineCollection<int, File>
     */
    public function getFiles(): DoctrineCollection
    {
        return $this->files;
    }

    public function addFile(File $file): void
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
        }
    }

    public function removeFile(File $file): void
    {
        $this->files->removeElement($file);
    }
}
