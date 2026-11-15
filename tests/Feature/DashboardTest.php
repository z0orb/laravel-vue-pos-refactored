<?php

namespace Tests\Feature;

use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Characterization test untuk GET /api/v1/admin/home (statistik dashboard admin).
 * Tanggal "hari ini" dikunci agar hasil perhitungan bulanan bisa dibandingkan.
 */
class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function getDashboard($today)
    {
        Carbon::setTestNow(Carbon::parse($today));
        $admin = User::where('username', 'admin')->first();

        return $this->actingAs($admin, 'api')->getJson('/api/v1/admin/home');
    }

    public function test_statistik_bulan_dengan_transaksi_naik()
    {
        $this->getDashboard('2019-11-30 12:00:00')
            ->assertStatus(200)
            ->assertExactJson([
                'transaksi_bulan_ini' => 13,
                'transaksi_bulan_lalu' => 1,
                'peningkatan_transaksi' => '+1200%',
                'average_price' => 52800,
                'product_sold' => '82',
                'product_sold_lastmo' => '2',
                'pendapatan_bulan_ini' => 21300,
                'pendapatan_bulan_lalu' => 5000,
                'peningkatan_pendapatan' => '+1630000%',
            ]);
    }

    public function test_statistik_bulan_dengan_transaksi_turun()
    {
        $this->getDashboard('2019-12-15 12:00:00')
            ->assertStatus(200)
            ->assertJson([
                'transaksi_bulan_ini' => 0,
                'transaksi_bulan_lalu' => 13,
                'peningkatan_transaksi' => '-1300%',
                'product_sold_lastmo' => '68',
                'pendapatan_bulan_ini' => null,
                'pendapatan_bulan_lalu' => 21300,
                'peningkatan_pendapatan' => '-2130000%',
            ]);
    }

    public function test_statistik_bulan_tanpa_transaksi_sama_sekali()
    {
        // Kasus batas: selisih nol. Tanda untuk transaksi "-" sedangkan
        // untuk pendapatan "+", sesuai perilaku sistem sebelum refactoring.
        $this->getDashboard('2021-06-10 12:00:00')
            ->assertStatus(200)
            ->assertJson([
                'transaksi_bulan_ini' => 0,
                'transaksi_bulan_lalu' => 0,
                'peningkatan_transaksi' => '-0%',
                'pendapatan_bulan_ini' => null,
                'pendapatan_bulan_lalu' => null,
                'peningkatan_pendapatan' => '+0%',
            ]);
    }
}
