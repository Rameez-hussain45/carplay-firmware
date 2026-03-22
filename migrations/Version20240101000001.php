<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create software_version table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE software_version (
            id             INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name           VARCHAR(120) NOT NULL,
            system_version VARCHAR(120) NOT NULL,
            st_link        VARCHAR(500) DEFAULT NULL,
            gd_link        VARCHAR(500) DEFAULT NULL,
            link           VARCHAR(500) DEFAULT NULL,
            is_latest      BOOLEAN NOT NULL DEFAULT 0,
            sort_order     INTEGER DEFAULT NULL
        )');

        $this->addSql('CREATE INDEX idx_sv_system_version ON software_version (system_version)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE software_version');
    }
}
