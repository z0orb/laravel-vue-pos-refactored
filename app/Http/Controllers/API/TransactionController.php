<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\Transaction;
use App\Model\TransactionDetail;
use App\Model\Product;
use App\Http\Resources\TransactionCollection;
use App\ValueObjects\CartItem;
use Ramsey\Uuid\Uuid;
use Auth;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $r)
    {
        // $search = $r->get('search');
        // return new TransactionCollection(Transaction::whereHas('details')->with(['details' => function($query) use ($id){
        //     $query->with('product' => function($query) use ($id){
        //             $query->where('product.id',$id);
        //     });
        // }])->where('id','LIKE',"%$search%")->orderBy('id','desc')->paginate(10));
    }

    
    public function newTransaction(Request $r) {
        $order = $this->createOrder($r);
        $items = array_map([CartItem::class, 'fromArray'], $r->cart);

        $order->update($this->saveOrderDetails($order, $items));

        // Perilaku lama dipertahankan: hanya stok item terakhir yang dikurangi.
        $lastItem = end($items);
        Product::find($lastItem->productId())->reduceStock($lastItem->quantity());

        return response()->json([
            'status' => true,
            'message' => $this->successMessage($order, $r->bayar, $r->kembalian),
        ]);
    }

    private function createOrder(Request $r) {
        return Transaction::create([
            'invoice' => Uuid::uuid4(),
            'user_id' => Auth::user()->id,
            'total' => 0,
            'total_ppn' => 0,
            'bayar' => $r->bayar,
            'kembalian' => $r->kembalian,
            'payment_method_id' => $r->payment_method,
            'customer_id' => $r->customer,
        ]);
    }

    /**
     * Simpan setiap item sebagai detail order, lalu kembalikan total order.
     */
    private function saveOrderDetails(Transaction $order, array $items) {
        $totals = ['total' => 0, 'total_ppn' => 0];

        foreach ($items as $item) {
            TransactionDetail::create([
                'order_id' => $order->id,
                'product_id' => $item->productId(),
                'quantity' => $item->quantity(),
                'price' => $item->unitPrice(),
                'subtotal' => $item->subtotal(),
                'ppn' => $item->ppnAmount()
            ]);

            $totals['total'] += $item->subtotal();
            $totals['total_ppn'] += $item->ppnAmount();
        }

        return $totals;
    }

    private function successMessage(Transaction $order, $bayar, $kembalian) {
        return '<b>Transaksi Sukses!</b> <br>Total Harga: <b>Rp'.number_format($order->total)
            ."</b><br>Jumlah Bayar: <b>Rp ".number_format($bayar)
            ."</b><br>Kembalian: <b>Rp ".number_format($kembalian)
            ."</b><br>Invoice: <a href='".url("transaksi/invoice/".$order->invoice)."'>Klik Disini</a>";
    }

    public function history(Request $request) {
        $search = $request->get('search');
        return new TransactionCollection(Transaction::with(['details.product','customer'])->where('user_id',Auth::user()->id)->where('id','LIKE',"%$search%")->orderBy('id','desc')->paginate(10));
    }

    public function allHistory(Request $request) {
        $search = $request->get('search');
        return new TransactionCollection(Transaction::with(['details.product','cashier','customer'])->where('id','LIKE',"%$search%")->orderBy('id','desc')->paginate(10));
    }

    public function invoice(Request $r, $invoice_id) {
        return Transaction::with(['details.product','customer','payment_method'])->where('invoice',$invoice_id)->orderBy('id','desc')->first();
    }

    public function chart() {
        $data = [];
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
