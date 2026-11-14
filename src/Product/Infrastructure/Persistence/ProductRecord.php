<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

/**
 * The mapped persistence object of a product; ProductMapper converts it to the domain model.
 *
 * The price is stored on the product: one price in one currency. Prices in
 * further currencies would get a table of their own, with this one as the default.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product')]
final class ProductRecord
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    public string $id;

    #[ORM\Column(length: 255)]
    public string $name;

    /** Gross price in cents. */
    #[ORM\Column]
    public int $priceAmount;

    #[ORM\Column(length: 3)]
    public string $priceCurrency;

    /** VAT rate in basis points (1900 = 19 %). */
    #[ORM\Column]
    public int $taxRateBp;
}
