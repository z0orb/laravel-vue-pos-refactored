<?php

namespace Tests\Feature;

use App\Model\Product;
use App\Model\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Characterization test untuk endpoint POST /api/v1/transaction/new.
 * Nilai yang diharapkan diambil dari perilaku sistem sebelum refactoring.
 */
class TransactionTest extends TestCase
{
    use DatabaseTransactions;

    private function postCart(array $cart, $bayar = 120000, $kembalian = 13200)
    {
        $kasir = User::where('username', 'kasir')->first();

        return $this->actingAs($kasir, 'api')->postJson('/api/v1/transaction/new', [
            'cart' => $cart,
            'bayar' => $bayar,
            'kembalian' => $kembalian,
            'customer' => 2,
            'payment_method' => 1,
        ]);
    }

    private function cartItem($id, $price, $ppn, $quantity)
    {
        return [
            'id' => $id,
            'price' => $price * $quantity,
            'realPrice' => $price,
            'ppn' => $ppn,
            'quantity' => $quantity,
        ];
    }

    public function test_new_transaction_menyimpan_order_dan_detail_dengan_ppn()
    {
        $response = $this->postCart([
            $this->cartItem(2, 500, 20, 3),     // Chocolatos
            $this->cartItem(1, 100000, 5, 1),   // TestPPN
        ]);

        $response->assertStatus(200)->assertJson(['status' => true]);

        $order = Transaction::with('details')->orderBy('id', 'desc')->first();
        $this->assertEquals(2, $order->user_id);
        $this->assertEquals(106800, $order->total);
        $this->assertEquals(5100, $order->total_ppn);
        $this->assertEquals(120000, $order->bayar);
        $this->assertEquals(13200, $order->kembalian);
        $this->assertEquals(2, $order->customer_id);
        $this->assertEquals(1, $order->payment_method_id);
        $this->assertCount(2, $order->details);

        $first = $order->details->firstWhere('product_id', 2);
        $this->assertEquals(3, $first->quantity);
        $this->assertEquals(600, $first->price);
        $this->assertEquals(1800, $first->subtotal);
        $this->assertEquals(100, $first->ppn);

        $second = $order->details->firstWhere('product_id', 1);
        $this->assertEquals(1, $second->quantity);
        $this->assertEquals(105000, $second->price);
        $this->assertEquals(105000, $second->subtotal);
        $this->assertEquals(5000, $second->ppn);
    }

    public function test_new_transaction_hanya_mengurangi_stok_item_terakhir_di_cart()
    {
        // Perilaku lama (dipertahankan saat refactoring): pengurangan stok
        // berada di luar loop sehingga hanya item terakhir yang terpotong.
        $this->postCart([
            $this->cartItem(2, 500, 20, 3),
            $this->cartItem(1, 100000, 5, 1),
        ])->assertStatus(200);

        $this->assertEquals(155, Product::find(2)->stock);
        $this->assertEquals(1, Product::find(1)->stock);
    }

    public function test_new_transaction_mengembalikan_pesan_ringkasan_html()
    {
        $response = $this->postCart([$this->cartItem(2, 500, 20, 2)], 2000, 800);

        $order = Transaction::orderBy('id', 'desc')->first();
        $expected = '<b>Transaksi Sukses!</b> <br>Total Harga: <b>Rp1,200</b><br>'
            . 'Jumlah Bayar: <b>Rp 2,000</b><br>Kembalian: <b>Rp 800</b><br>'
            . "Invoice: <a href='" . url('transaksi/invoice/' . $order->invoice) . "'>Klik Disini</a>";

        $response->assertExactJson(['status' => true, 'message' => $expected]);
    }
}
