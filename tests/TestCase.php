<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Schema dan data awal hanya dimuat sekali per proses PHPUnit.
     */
    protected static $databaseSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::$databaseSeeded) {
            $this->loadDatabaseDump();
            static::$databaseSeeded = true;
        }
    }

    /**
     * Memuat ulang laravel_pos.sql ke database testing agar setiap run
     * berangkat dari data yang sama dengan data demo aplikasi.
     */
    protected function loadDatabaseDump()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (DB::select('SHOW TABLES') as $table) {
            $name = array_values((array) $table)[0];
            DB::statement("DROP TABLE IF EXISTS `$name`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        DB::unprepared(file_get_contents(base_path('laravel_pos.sql')));
    }
}
