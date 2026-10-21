<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Bounded, sequential image uploads; only newly created files may be removed. */
class Question_image_upload
{
 const MAX_FILES = 500;
 const MAX_TOTAL_BYTES = 33554432;
 const MAX_TOTAL_PIXELS = 64000000;
 private $store;

 public function __construct()
 {
  $ci =& get_instance();
  $ci->load->library('lesson_image_store');
  $this->store = $ci->lesson_image_store;
 }

 public function read($files)
 {
  if ($files === NULL) { return array(); }
  if (!is_array($files) || !is_array($files['name'] ?? NULL) || count($files['name']) > self::MAX_FILES) {
   throw new RuntimeException('Pilih hingga 500 gambar per impor, bukan 10. Jika lebih, bagi menjadi beberapa berkas soal.');
  }
  $keys = array_keys($files['name']);
  foreach (array('type','tmp_name','error','size') as $field) {
   if (!is_array($files[$field] ?? NULL) || array_keys($files[$field]) !== $keys) {
    throw new RuntimeException('Data lampiran tidak lengkap. Pilih ulang seluruh gambar.');
   }
  }
  $selected = array();
  $total = 0;
  $pixels = 0;
  foreach ($files['name'] as $i=>$name) {
   if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) { continue; }
   if (!is_string($name) || isset($selected[$name])) { throw new RuntimeException('Setiap lampiran harus mempunyai nama berkas unik.'); }
   $file = array();
   foreach (array('name','type','tmp_name','error','size') as $field) { $file[$field] = $files[$field][$i]; }
   if (!is_string($file['tmp_name']) || !is_string($file['type']) || $file['error'] !== UPLOAD_ERR_OK || !is_int($file['size']) || $file['size'] < 1 || $file['size'] > Lesson_image_store::MAX_UPLOAD_BYTES || !is_uploaded_file($file['tmp_name'])) {
    throw new RuntimeException('Unggahan gambar tidak lengkap atau melebihi 3 MiB per gambar. Pilih ulang berkas PNG/JPEG.');
   }
   $total += $file['size'];
   if ($total > self::MAX_TOTAL_BYTES) { throw new RuntimeException('Total gambar maksimal 32 MiB per impor. Perkecil gambar atau bagi impor menjadi beberapa berkas soal.'); }
   $dimensions = @getimagesize($file['tmp_name']);
   if (!$dimensions || !in_array($dimensions['mime'] ?? '', array('image/png','image/jpeg'), TRUE) || $dimensions[0] > Lesson_image_store::MAX_DIMENSION || $dimensions[1] > Lesson_image_store::MAX_DIMENSION || $dimensions[0] * $dimensions[1] > Lesson_image_store::MAX_PIXELS) {
    throw new RuntimeException('Gambar harus PNG/JPEG, maksimal 4096 piksel per sisi dan 4 juta piksel per gambar.');
   }
   $pixels += $dimensions[0] * $dimensions[1];
   if ($pixels > self::MAX_TOTAL_PIXELS) { throw new RuntimeException('Total resolusi gambar melebihi 64 juta piksel. Perkecil gambar atau bagi impor agar pemrosesan tetap aman.'); }
   $selected[$name] = $file;
  }
  $images = array();
  try {
   foreach ($selected as $name=>$file) {
    $result = $this->store->from_upload($file);
    if ($result['status'] !== 'ok') { throw new RuntimeException($result['message']); }
    $images[$name] = $result['image'] + array('original_name'=>$name);
   }
   return $images;
  } catch (Throwable $error) {
   $this->discard($images);
   throw $error;
  }
 }

 public function discard($images)
 {
  foreach ($images as $image) { $this->store->remove($image['storage_key']); }
 }
}
