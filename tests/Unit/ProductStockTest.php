<?php

namespace Tests\Unit;

use App\Model\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductStockTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reduce_stock_mengurangi_dan_menyimpan_stok()
    {
        $product = Product::find(2);
        $this->assertEquals(155, $product->stock);

        $product->reduceStock(5);

        $this->assertEquals(150, $product->stock);
        $this->assertEquals(150, Product::find(2)->stock);
    }
}
