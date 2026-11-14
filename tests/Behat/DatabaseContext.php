<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Product\Infrastructure\Persistence\ProductRecord;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Database reset and data setup steps.
 *
 * The purger deletes the rows of all mapped tables in foreign-key order. With
 * UUID keys there are no sequences to reset.
 */
final class DatabaseContext implements Context
{
    private const string CURRENCY = 'EUR';
    private const int TAX_RATE_BP = 1900;

    private readonly EntityManagerInterface $em;

    public function __construct()
    {
        $em = KernelBoot::container()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;
    }

    #[BeforeScenario]
    public function resetDatabase(): void
    {
        new ORMPurger($this->em)->purge();
        $this->em->clear();
    }

    /**
     * Columns: id, name, price (gross, in cents). All products are in EUR with 19 % VAT.
     */
    #[Given('the following products exist:')]
    public function theFollowingProductsExist(TableNode $table): void
    {
        foreach ($table->getColumnsHash() as $row) {
            $product = new ProductRecord();
            $product->id = $row['id'];
            $product->name = $row['name'];
            $product->priceAmount = (int) $row['price'];
            $product->priceCurrency = self::CURRENCY;
            $product->taxRateBp = self::TAX_RATE_BP;
            $this->em->persist($product);
        }

        $this->em->flush();
        $this->em->clear();
    }
}
