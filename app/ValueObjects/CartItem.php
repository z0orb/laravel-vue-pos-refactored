<?php

namespace App\ValueObjects;

/**
 * Satu baris belanja di keranjang kasir beserta aturan hitung PPN-nya.
 */
class CartItem
{
    private $productId;
    private $quantity;
    private $basePrice;
    private $ppnPercent;

    public function __construct($productId, $quantity, $basePrice, $ppnPercent)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
        $this->basePrice = $basePrice;
        $this->ppnPercent = $ppnPercent;
    }

    /**
     * Membuat CartItem dari payload cart yang dikirim halaman kasir.
     */
    public static function fromArray(array $data)
    {
        return new self($data['id'], $data['quantity'], $data['realPrice'], $data['ppn']);
    }

    public function productId()
    {
        return $this->productId;
    }

    public function quantity()
    {
        return $this->quantity;
    }

    /**
     * Nominal PPN untuk satu unit barang.
     */
    public function ppnAmount()
    {
        return $this->basePrice * $this->ppnPercent / 100;
    }

    /**
     * Harga satu unit setelah ditambah PPN.
     */
    public function unitPrice()
    {
        return $this->basePrice + $this->ppnAmount();
    }

    public function subtotal()
    {
        return $this->unitPrice() * $this->quantity;
    }
}
