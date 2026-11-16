<?php

namespace Tests\Unit;

use App\ValueObjects\CartItem;
use PHPUnit\Framework\TestCase;

class CartItemTest extends TestCase
{
    public function test_menghitung_ppn_harga_satuan_dan_subtotal()
    {
        $item = new CartItem(2, 3, 500, 20);

        $this->assertEquals(100, $item->ppnAmount());
        $this->assertEquals(600, $item->unitPrice());
        $this->assertEquals(1800, $item->subtotal());
    }

    public function test_produk_tanpa_ppn()
    {
        $item = new CartItem(1, 2, 15000, 0);

        $this->assertEquals(0, $item->ppnAmount());
        $this->assertEquals(15000, $item->unitPrice());
        $this->assertEquals(30000, $item->subtotal());
    }

    public function test_dibuat_dari_payload_halaman_kasir()
    {
        $item = CartItem::fromArray([
            'id' => 7,
            'price' => 3000,      // total tampilan di frontend, tidak dipakai backend
            'realPrice' => 1000,
            'ppn' => 10,
            'quantity' => 3,
        ]);

        $this->assertEquals(7, $item->productId());
        $this->assertEquals(3, $item->quantity());
        $this->assertEquals(1100, $item->unitPrice());
        $this->assertEquals(3300, $item->subtotal());
    }
}
