<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Traffic_navigation
{
 // Only public content and neutral landing paths. Never account IDs, session IDs, tokens or queries.
 public function safe_path($value)
 {
  if(!is_string($value)||strlen($value)>4096){return NULL;}
  $path=explode('?',explode('#',$value,2)[0],2)[0];
  $path=preg_replace('#\A/index\.php/#','/',$path);
  if(strlen($path)>255||!preg_match('#\A/(?:[a-zA-Z0-9/_-]*)\z#',$path)||strpos($path,'//')!==FALSE){return NULL;}
  if(in_array($path,array('/','/kursus','/beranda','/privasi','/belajar/arena','/belajar/arena/riwayat','/belajar/battle','/belajar/battle/umum','/keluarga/anak','/latihan/mandiri','/latihan/ujian','/latihan/kursus'),TRUE)){return $path;}
  if(preg_match('#\A/(?:kursus/[a-z0-9][a-z0-9-]{0,179}|materi/[a-z0-9][a-z0-9-]{0,179}|belajar/arena/(?:game|pilih)/[a-z0-9][a-z0-9_-]{0,179})\z#',$path)){return $path;}
  if(preg_match('#\A/latihan-soal(?:/kelas-[1-6](?:/(?:matematika|bahasa-indonesia|bahasa-inggris|ipas|pendidikan-pancasila|pjok|seni-budaya))?)?\z#',$path)){return $path;}
  return NULL;
 }
 public function fallback($view)
 {
  if($view==='public_learning/index'){return '/latihan-soal';}
  $map=array('home/index'=>'/','catalog/index'=>'/kursus','catalog/course'=>'/kursus','catalog/lesson'=>'/kursus','dashboard/index'=>'/beranda','privacy/policy'=>'/privasi','family/children'=>'/keluarga/anak','battle/index'=>'/belajar/battle','battle/directory'=>'/belajar/battle/umum','battle/public_room'=>'/belajar/battle/umum');
  return $map[$view]??(strpos($view,'arena/')===0?'/belajar/arena':NULL);
 }
 public function referrer($value,$base)
 {
  $result=array('referrer_kind'=>'direct_unknown','referrer_host'=>NULL,'referrer_path'=>NULL);
  if($value===NULL||$value===''){return $result;}
  if(!is_string($value)||strlen($value)>4096||preg_match('/[\x00-\x20\x7f\\\\]/',$value)){return array_replace($result,array('referrer_kind'=>'unknown'));}
  $url=parse_url($value);$host=strtolower($url['host']??'');
  if(!$url||!in_array(strtolower($url['scheme']??''),array('http','https'),TRUE)||isset($url['user'])||isset($url['pass'])||strlen($host)>253||!preg_match('/\A[a-z0-9]+(?:[.-][a-z0-9]+)*\z/',$host)){return array_replace($result,array('referrer_kind'=>'unknown'));}
  $internal=in_array($host,array('projectpintar.com','www.projectpintar.com',strtolower(parse_url($base,PHP_URL_HOST)??'')),TRUE);
  if($internal){return array('referrer_kind'=>'internal','referrer_host'=>NULL,'referrer_path'=>$this->safe_path($url['path']??'/'));}
  // Host boundaries prevent google.com.evil.example from masquerading as a search engine.
  $search=preg_match('/(?:\A|\.)(?:google\.(?:com|co\.id|co\.uk|com\.au)|bing\.com|duckduckgo\.com|search\.yahoo\.com|search\.brave\.com|yandex\.(?:com|ru)|baidu\.com)\z/',$host);
  return array('referrer_kind'=>$search?'search':'external','referrer_host'=>$host,'referrer_path'=>NULL);
 }
 public function referrer_label($kind)
 {
  return array('internal'=>'Halaman internal','search'=>'Mesin pencari','external'=>'Situs lain','direct_unknown'=>'Langsung / tidak diketahui','unknown'=>'Perujuk tidak dapat dikenali')[$kind??'']??'Belum direkam (data lama)';
 }
}
