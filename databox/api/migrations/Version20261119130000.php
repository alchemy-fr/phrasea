<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link the messages to their attached files (message_attachment_file), which only held their ID in JSON';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_attachment_file (message_id UUID NOT NULL, file_id UUID NOT NULL, PRIMARY KEY (message_id, file_id))');
        $this->addSql('CREATE INDEX IDX_CB57CD9537A1329 ON message_attachment_file (message_id)');
        $this->addSql('CREATE INDEX IDX_CB57CD993CB796C ON message_attachment_file (file_id)');
        $this->addSql('ALTER TABLE message_attachment_file ADD CONSTRAINT FK_CB57CD9537A1329 FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message_attachment_file ADD CONSTRAINT FK_CB57CD993CB796C FOREIGN KEY (file_id) REFERENCES file (id) ON DELETE CASCADE NOT DEFERRABLE');

        // A file attachment is {"type": "file", "content": "{\"id\": \"<uuid>\", ...}"}: its content is a JSON string
        $this->addSql(<<<'SQL'
            INSERT INTO message_attachment_file (message_id, file_id)
            SELECT DISTINCT m.id, f.id
            FROM message m
            CROSS JOIN LATERAL json_array_elements(m.attachments) a
            INNER JOIN file f ON f.id::text = substring(a->>'content' from '"id":\s*"([0-9a-f-]{36})"')
            WHERE m.attachments IS NOT NULL
              AND json_typeof(m.attachments) = 'array'
              AND a->>'type' = 'file'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE message_attachment_file');
    }
}
