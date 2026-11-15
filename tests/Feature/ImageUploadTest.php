<?php

namespace Tests\Feature;

use App\Model\Category;
use App\Model\Product;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Characterization test untuk semua endpoint yang menerima upload foto.
 * Yang diperiksa: file tersimpan di folder public yang benar dan nama file
 * tercatat di database.
 */
class ImageUploadTest extends TestCase
{
    use DatabaseTransactions;

    private $createdFiles = [];
    private $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();

        // `php artisan serve` menjalankan aplikasi dengan working directory
        // di folder public, jadi test meniru kondisi yang sama.
        $this->originalCwd = getcwd();
        chdir(public_path());

        $this->actingAs(User::where('username', 'admin')->first(), 'api');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        chdir($this->originalCwd);
        parent::tearDown();
    }

    private function fakePhoto($folder)
    {
        $name = 'test-' . uniqid() . '.png';
        $this->createdFiles[] = public_path("images/$folder/$name");

        return UploadedFile::fake()->image($name, 10, 10);
    }

    private function assertStored($folder, UploadedFile $photo)
    {
        $this->assertFileExists(public_path("images/$folder/" . $photo->getClientOriginalName()));
    }

    public function test_tambah_kategori_menyimpan_foto()
    {
        $photo = $this->fakePhoto('categories');

        $this->post('/api/v1/category', [
            'name' => 'Minuman Dingin',
            'description' => 'Aneka minuman',
            'photo' => $photo,
        ])->assertStatus(201)->assertJson(['name' => 'Minuman Dingin', 'slug' => 'minuman-dingin']);

        $this->assertStored('categories', $photo);
        $this->assertDatabaseHas('categories', ['name' => 'Minuman Dingin', 'image_name' => $photo->getClientOriginalName()]);
    }

    public function test_ubah_kategori_dengan_dan_tanpa_foto()
    {
        $photo = $this->fakePhoto('categories');

        $this->post('/api/v1/category/1', [
            'name' => 'Snack Ringan',
            'description' => 'Baru',
            'photo' => $photo,
        ])->assertStatus(200)->assertExactJson(['success' => true]);
        $this->assertStored('categories', $photo);
        $this->assertEquals($photo->getClientOriginalName(), Category::find(1)->image_name);

        $this->post('/api/v1/category/1', ['name' => 'Snack', 'description' => 'Tanpa foto'])
            ->assertStatus(200);
        $category = Category::find(1);
        $this->assertEquals('snack', $category->slug);
        $this->assertEquals($photo->getClientOriginalName(), $category->image_name);
    }

    public function test_tambah_produk_menyimpan_foto()
    {
        $photo = $this->fakePhoto('products');

        $this->post('/api/v1/product', [
            'name' => 'Teh Botol',
            'code' => 'TEH01',
            'stock' => 10,
            'category' => 1,
            'description' => 'Teh manis',
            'price' => 4000,
            'ppn' => 10,
            'photo' => $photo,
        ])->assertStatus(200)->assertExactJson(['status' => true]);

        $this->assertStored('products', $photo);
        $this->assertDatabaseHas('products', ['code' => 'TEH01', 'slug' => 'teh-botol', 'image_name' => $photo->getClientOriginalName()]);
    }

    public function test_ubah_produk_dengan_foto()
    {
        $photo = $this->fakePhoto('products');

        $this->post('/api/v1/product/2', [
            'name' => 'Chocolatos Besar',
            'description' => 'Ukuran besar',
            'code' => 'CHO1',
            'category' => 1,
            'price' => 1000,
            'stock' => 50,
            'photo' => $photo,
        ])->assertStatus(200)->assertExactJson(['status' => true]);

        $this->assertStored('products', $photo);
        $product = Product::find(2);
        $this->assertEquals($photo->getClientOriginalName(), $product->image_name);
        $this->assertEquals(50, $product->stock);
    }

    public function test_tambah_customer_menyimpan_foto()
    {
        $photo = $this->fakePhoto('customers');

        $this->post('/api/v1/customer', [
            'name' => 'Budi',
            'alamat' => 'Jl. Mawar 1',
            'role' => 'Retail',
            'phone' => '0811111',
            'provinsi' => 'Jawa Timur',
            'kota' => 'Surabaya',
            'email' => 'budi@example.com',
            'photo' => $photo,
        ])->assertStatus(200)->assertExactJson(['status' => true]);

        $this->assertStored('customers', $photo);
        $this->assertDatabaseHas('customers', ['email' => 'budi@example.com', 'image_name' => $photo->getClientOriginalName()]);
    }

    public function test_tambah_dan_ubah_user_menyimpan_foto()
    {
        $photo = $this->fakePhoto('users');

        $this->post('/api/v1/user', [
            'name' => 'Kasir Dua',
            'email' => 'kasir2@example.com',
            'password' => 'rahasia123',
            'photo' => $photo,
            'username' => 'kasirdua',
            'level' => 'Kasir',
            'address' => 'Surabaya',
            'phone' => '08123',
        ])->assertStatus(201)->assertJson(['username' => 'kasirdua']);
        $this->assertStored('users', $photo);

        $id = DB::table('users')->where('username', 'kasirdua')->value('id');
        $newPhoto = $this->fakePhoto('users');

        $this->post("/api/v1/user/$id", [
            'name' => 'Kasir Dua Baru',
            'email' => 'kasir2@example.com',
            'password' => 'rahasia123',
            'photo' => $newPhoto,
            'username' => 'kasirdua',
            'level' => 'Kasir',
            'address' => 'Malang',
            'phone' => '08123',
        ])->assertStatus(200)->assertExactJson(['status' => 'success']);

        $this->assertStored('users', $newPhoto);
        $this->assertDatabaseHas('users', ['id' => $id, 'address' => 'Malang', 'image_name' => $newPhoto->getClientOriginalName()]);
    }

    public function test_validasi_produk_menolak_kode_duplikat()
    {
        $this->postJson('/api/v1/product', [
            'name' => 'Duplikat',
            'code' => 'CHO1',
            'stock' => 1,
            'category' => 1,
            'description' => 'x',
            'price' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }
}
