<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Restricted image markers, never HTML or remote URLs. Stored references are 1-based. */
class Question_media
{
 const MARKER = '/\[GAMBAR:\s*([^\]\r\n]+?)\s*\]/iu';
 const CANONICAL = '/\[GAMBAR:([1-9][0-9]{0,2})\]/';

 public static function references($text)
 {
  preg_match_all(self::MARKER, $text, $matches);
  $rest = preg_replace(self::MARKER, '', $text);
  if (preg_match('/\[\s*GAMBAR\b/iu', $rest)) {
   throw new RuntimeException('Penanda gambar belum lengkap. Gunakan [GAMBAR: nama.png] dan tutup dengan ].');
  }
  return array_map('trim', $matches[1]);
 }

 public static function compile($text, $names)
 {
  self::references($text);
  return preg_replace_callback(self::MARKER, static function($m) use ($names) {
   $ref = trim($m[1]);
   if (preg_match('/\A[1-9][0-9]{0,2}\z/', $ref)) {
    $index = (int)$ref - 1;
    if (!isset($names[$index])) { throw new RuntimeException('Nomor gambar '.$ref.' tidak tersedia pada lampiran soal ini.'); }
   } else {
    $index = array_search($ref, $names, TRUE);
    if ($index === FALSE) { throw new RuntimeException('Lampirkan gambar dengan nama persis: '.$ref); }
   }
   return '[GAMBAR:'.($index+1).']';
  }, $text);
 }

 public static function indexes($text, $enabled)
 {
  if (!$enabled) { return array(); }
  preg_match_all(self::CANONICAL, $text, $m);
  return array_values(array_unique(array_map(static function($n){return (int)$n-1;}, $m[1])));
 }

 /** Only non-sensitive rendering metadata may leave the snapshot model. */
 public static function layout($image, $prompt, $options)
 {
  $count = $image ? 1 + count($image['additional_images'] ?? array()) : 0;
  $enabled = ($image['text_markers_version'] ?? NULL) === 1;
  $used = self::indexes(implode("\n", array_merge(array($prompt), $options)), $enabled);
  return array('enabled'=>$enabled, 'count'=>$count,
   'gallery'=>$count ? array_values(array_diff(range(0,$count-1), $used)) : array());
 }

 public static function render($text, $layout, $image_url, $context='soal')
 {
  $escape = static function($s){return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');};
  if (empty($layout['enabled'])) { return nl2br($escape($text)); }
  $parts = preg_split('/(\[GAMBAR:[1-9][0-9]{0,2}\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
  $html = '';
  foreach ($parts as $part) {
   if (preg_match('/\A\[GAMBAR:([1-9][0-9]{0,2})\]\z/', $part, $m)) {
    $index = (int)$m[1]-1;
    if ($index >= $layout['count']) {
     $html .= '<span class="text-danger">[Gambar tidak tersedia]</span>';
    } else {
     $html .= '<img class="question-inline-image" src="'.$escape($image_url($index)).
      '" alt="'.$escape('Gambar '.$m[1].' pada '.$context).'" loading="lazy">';
    }
   } else { $html .= nl2br($escape($part)); }
  }
  return $html;
 }
}
