<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211124140146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER INDEX idx_44fbbc15908e2ffe RENAME TO IDX_7A972A83908E2FFE');
        $this->addSql('ALTER INDEX idx_44fbbc155da1941 RENAME TO IDX_7A972A835DA1941');
        $this->addSql('ALTER INDEX idx_44fbbc1593cb796c RENAME TO IDX_7A972A8393CB796C');
        $this->addSql('ALTER INDEX uniq_sub_def RENAME TO uniq_representation');
        $this->addSql('ALTER INDEX idx_c7ab378482d40a1f RENAME TO IDX_8E3E63A882D40A1F');
        $this->addSql('ALTER INDEX sdc_uniq RENAME TO rend_class_uniq');
        $this->addSql('ALTER INDEX idx_4f0b98c5ea000b10 RENAME TO IDX_63599969EA000B10');
        $this->addSql('ALTER INDEX idx_4f0b98c582d40a1f RENAME TO IDX_6359996982D40A1F');
        $this->addSql('ALTER INDEX sds_ws_name RENAME TO rend_def_ws_name');
        $this->addSql('ALTER INDEX sdr_user_idx RENAME TO rr_user_idx');
        $this->addSql('ALTER INDEX sdr_object_idx RENAME TO rr_object_idx');
        $this->addSql('ALTER INDEX sdr_user_type_idx RENAME TO rr_user_type_idx');
        $this->addSql('ALTER INDEX sdr_uniq_rule RENAME TO rend_uniq_rule');
        $this->addSql('DROP INDEX idx_e8839a321aeefe4');
        $this->addSql('DROP INDEX idx_e8839a32517eacff');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER INDEX rend_class_uniq RENAME TO sdc_uniq');
        $this->addSql('ALTER INDEX idx_8e3e63a882d40a1f RENAME TO idx_c7ab378482d40a1f');
        $this->addSql('CREATE INDEX idx_e8839a321aeefe4 ON sdr_allowed (rendition_rule_id)');
        $this->addSql('CREATE INDEX idx_e8839a32517eacff ON sdr_allowed (rendition_class_id)');
        $this->addSql('ALTER INDEX idx_7a972a835da1941 RENAME TO idx_44fbbc155da1941');
        $this->addSql('ALTER INDEX idx_7a972a8393cb796c RENAME TO idx_44fbbc1593cb796c');
        $this->addSql('ALTER INDEX idx_7a972a83908e2ffe RENAME TO idx_44fbbc15908e2ffe');
        $this->addSql('ALTER INDEX uniq_representation RENAME TO uniq_sub_def');
        $this->addSql('ALTER INDEX rr_user_idx RENAME TO sdr_user_idx');
        $this->addSql('ALTER INDEX rr_object_idx RENAME TO sdr_object_idx');
        $this->addSql('ALTER INDEX rr_user_type_idx RENAME TO sdr_user_type_idx');
        $this->addSql('ALTER INDEX rend_uniq_rule RENAME TO sdr_uniq_rule');
        $this->addSql('ALTER INDEX rend_def_ws_name RENAME TO sds_ws_name');
        $this->addSql('ALTER INDEX idx_6359996982d40a1f RENAME TO idx_4f0b98c582d40a1f');
        $this->addSql('ALTER INDEX idx_63599969ea000b10 RENAME TO idx_4f0b98c5ea000b10');
    }
}
