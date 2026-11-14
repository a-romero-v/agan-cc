<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114161926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Product catalogue: table product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product (id VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, price_amount INTEGER NOT NULL, price_currency VARCHAR(3) NOT NULL, tax_rate_bp INTEGER NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product');
    }
}
