<?php

namespace App\Http\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait ImageUploadTrait
{
    /**
     * @param UploadedFile $image
     * @return string|null
     */
    public function imageUpload(UploadedFile $image): ?string {
        if (!is_uploaded_file($image)){
            return null;
        }

        $ext = $image->getClientOriginalExtension();
        $img_name = Str::random(15).'.'.$ext;

//        $image->storeAs('public', $img_name);

        if ($ext == 'jpeg') {
            $image_filename = str_replace('.jpeg', '.webp', $img_name);
            $webpimage = imagecreatefromjpeg($image);
        } elseif ($ext == 'jpg') {
            $image_filename = str_replace('.jpg', '.webp', $img_name);
            $webpimage = imagecreatefromjpeg($image);
        } elseif ($ext == 'png') {
            $image_filename = str_replace('.png', '.webp', $img_name);
            $webpimage = imagecreatefrompng($image);
        }

        if (!empty($webpimage) && !empty($image_filename)) {
            imagewebp($webpimage, 'storage/'.$image_filename, 60);
            imagedestroy($webpimage);
        }

        return $image_filename ?? '';
    }
}
