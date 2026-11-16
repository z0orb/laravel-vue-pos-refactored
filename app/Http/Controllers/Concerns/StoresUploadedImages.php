<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;

trait StoresUploadedImages
{
    /**
     * Pindahkan file upload ke public/images/{folder} dengan nama aslinya,
     * lalu kembalikan nama file untuk disimpan di kolom image_name.
     */
    protected function storeImage(UploadedFile $file, $folder)
    {
        $imageName = $file->getClientOriginalName();
        $file->move(public_path('images/' . $folder), $imageName);

        return $imageName;
    }
}
