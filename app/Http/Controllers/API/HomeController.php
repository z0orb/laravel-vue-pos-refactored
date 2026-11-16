<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\Transaction;
use App\Model\Customer;
use App\Model\TransactionDetail;
use Carbon\Carbon;
use DB;
use PDF;
use App\Model\Category;
use App\Model\Product;
use App\User;

class HomeController extends Controller
{
    public function index() {
        $data = [];
        $thisMonth = Carbon::now()->format('Y-m');
        $lastMonth = Carbon::now()->subMonth(1)->format('Y-m');

        $data['transaksi_bulan_ini'] = $this->countTransactionsInMonth($thisMonth);
        $data['transaksi_bulan_lalu'] = $this->countTransactionsInMonth($lastMonth);
        $data['peningkatan_transaksi'] = $this->growthPercentage($data['transaksi_bulan_ini'], $data['transaksi_bulan_lalu'], false);
        $data['average_price'] = Product::selectRaw("AVG(price+ (price * ppn/100) ) as average")->first()->average;
        $data['product_sold'] = TransactionDetail::selectRaw('SUM(quantity) as quantity')->first()->quantity;
        $data['product_sold_lastmo'] = TransactionDetail::selectRaw('SUM(quantity) as quantity')->where('created_at','LIKE',"%$lastMonth%")->first()->quantity;
        $data['pendapatan_bulan_ini'] = $this->sumPpnInMonth($thisMonth);
        $data['pendapatan_bulan_lalu'] = $this->sumPpnInMonth($lastMonth);
        $data['peningkatan_pendapatan'] = $this->growthPercentage($data['pendapatan_bulan_ini'], $data['pendapatan_bulan_lalu'], true);

        return response()->json($data);
    }

    private function countTransactionsInMonth($month) {
        return Transaction::where('created_at','LIKE', '%'.$month.'%')->count();
    }

    private function sumPpnInMonth($month) {
        return DB::table('order_details')
            ->select(DB::raw("SUM(ppn) as fee"))
            ->where('order_details.created_at','LIKE', '%'.$month.'%')
            ->first()->fee;
    }

    /**
     * Format perubahan bulan ini terhadap bulan lalu, contoh "+25%".
     * $zeroIsIncrease menjaga perbedaan lama: selisih nol pada transaksi
     * ditampilkan "-0%", sedangkan pada pendapatan ditampilkan "+0%".
     */
    private function growthPercentage($current, $previous, $zeroIsIncrease) {
        $diff = $current - $previous;
        $isIncrease = $zeroIsIncrease ? $diff >= 0 : $diff > 0;
        $sign = $isIncrease ? "+" : "-";

        return $sign.((abs($diff)/($previous!==0?:1))*100)."%";
    }
    public function report() {
        $data = [];
        $data['total']['transaction'] = Transaction::count();
        $data['total']['transaction_thismo'] = Transaction::whereMonth('created_at', Carbon::now()->format('m'))->count();
        $data['total']['category'] = Category::count();
        $data['total']['product'] = Product::count();
        $data['total']['product_sold'] = TransactionDetail::count();
        $data['total']['product_sold_thismo'] = TransactionDetail::whereMonth('created_at', Carbon::now()->format('m'))->count();
        $data['total']['product'] = Product::count();
        $data['total']['customer'] = Customer::count();

        $data['transaction']['chart'] = DB::table('orders')->select(DB::raw("COUNT(*) as a, DATE(created_at) as y"))->where('created_at', '>=', Carbon::now()->subDays(7)->format('Y-m-d'))->groupBy(DB::raw('DATE(created_at)'))->get();
        
        return response()->json($data);
    }

    public function pdf_transaction(Request $r) {
        $transactions = Transaction::all();
        $pdf = PDF::loadview('pdf.transaction', ['transactions' => $transactions])->save('pdf/transaction.pdf');
        return response()->json(['status' => true, 'filename' => 'transaction.pdf']);
    }

    public function pdf_users(Request $r) {
        $users = User::all();
        $pdf = PDF::loadview('pdf.users', ['users' => $users])->save('pdf/users.pdf');
        return response()->json(['status' => true, 'filename' => 'users.pdf']);
    }

    public function pdf_products(Request $r) {
        $products = Product::all();
        $pdf = PDF::loadview('pdf.products', ['products' => $products])->setPaper('a4', 'landscape')->save('pdf/products.pdf');
        return response()->json(['status' => true, 'filename' => 'products.pdf']);
    }
}
