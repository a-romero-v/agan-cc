<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\DataFixtures;

use App\Product\Infrastructure\Persistence\ProductRecord;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The demo catalogue of the dev database.
 *
 * The IDs are fixed, so the examples in the README keep working after every reset.
 * All prices are gross, in EUR, with 19 % VAT (SPEC, D1).
 */
final class ProductFixtures extends Fixture
{
    private const string CURRENCY = 'EUR';
    private const int TAX_RATE_BP = 1900;

    /** @var array<string, array{name: string, priceAmount: int}> */
    private const array PRODUCTS = [
        '019a7f3e-4a0b-7b29-8c1d-000000000001' => ['name' => 'Espresso Cup', 'priceAmount' => 999],
        '019a7f3e-4a0b-7b29-8c1d-000000000002' => ['name' => 'Coffee Filter Papers', 'priceAmount' => 100],
        '019a7f3e-4a0b-7b29-8c1d-000000000003' => ['name' => 'Hand Coffee Grinder', 'priceAmount' => 4990],
        '019a7f3e-4a0b-7b29-8c1d-000000000004' => ['name' => 'Milk Frother', 'priceAmount' => 1995],
        '019a7f3e-4a0b-7b29-8c1d-000000000005' => ['name' => 'Espresso Machine', 'priceAmount' => 39900],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PRODUCTS as $id => $data) {
            $product = new ProductRecord();
            $product->id = $id;
            $product->name = $data['name'];
            $product->priceAmount = $data['priceAmount'];
            $product->priceCurrency = self::CURRENCY;
            $product->taxRateBp = self::TAX_RATE_BP;

            $manager->persist($product);
        }

        $manager->flush();
    }
}
