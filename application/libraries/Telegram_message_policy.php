<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Plain-text templates and WIB scheduling. No template code is evaluated. */
class Telegram_message_policy
{
 public function categories(){return array('members'=>'Anggota baru','children'=>'Akun anak','payments'=>'Pesanan & kredit','donations'=>'Donasi','help'=>'Bantuan','visitors'=>'Laporan trafik');}
 public function default_template($category)
 {
  return $category==='visitors'?"📊 Laporan trafik Project Pintar\n{periode}\n\nAkses situs: {kunjungan}\nAkses nonbot: {manusia}\nBot / alat: {bot}\n\nJumlah akses, bukan orang unik.\n{tautan}":"Project Pintar — {judul}\n{isi}\n{tautan}";
 }
 public function placeholders($category)
 {
  $all=array('judul'=>'Judul pesan / ringkasan','isi'=>'Rincian kejadian','jenis'=>'Nama jenis pesan','jumlah'=>'Jumlah kejadian','periode'=>'Rentang laporan dalam WIB','waktu'=>'Waktu penyusunan dalam WIB','tautan'=>'Tautan halaman terkait');
  if($category==='visitors')$all+=array('kunjungan'=>'Jumlah akses situs','manusia'=>'Akses yang tidak ditandai bot','bot'=>'Akses yang ditandai bot / alat');
  return $all;
 }
 public function validate($v)
 {
  $category=$v['category']??NULL;
  if(!is_string($category)||!isset($this->categories()[$category])||!is_string($v['revision']??NULL)||!ctype_digit($v['revision']))return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_policy');
  $mode=$v['mode']??NULL;$minutes=$v['interval_minutes']??'';$daily=$v['daily_time']??'';$template=$v['template']??NULL;
  if(!in_array($mode,array('realtime','interval','daily'),TRUE)||($category==='visitors'&&$mode==='realtime'))return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_mode');
  if(!is_string($minutes)||!ctype_digit($minutes)||(int)$minutes<5||(int)$minutes>10080||!is_string($daily)||!preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/',$daily))return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_schedule');
  if(!is_string($template)||!mb_check_encoding($template,'UTF-8')||mb_strlen($template)>600||trim($template)===''||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$template))return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_template');
  preg_match_all('/\{([^{}]*)\}/u',$template,$matches);
  foreach($matches[1] as $key)if(!isset($this->placeholders($category)[$key]))return array('ok'=>FALSE,'code'=>400,'reason'=>'unknown_placeholder');
  if(strpbrk(preg_replace('/\{[^{}]*\}/u','',$template),'{}')!==FALSE)return array('ok'=>FALSE,'code'=>400,'reason'=>'unknown_placeholder');
  if(strpos($template,'{isi}')===FALSE&&($category!=='visitors'||strpos($template,'{kunjungan}')===FALSE))return array('ok'=>FALSE,'code'=>400,'reason'=>'missing_details');
  if(!in_array($v['send_empty']??'0',array('0','1'),TRUE))return array('ok'=>FALSE,'code'=>400,'reason'=>'invalid_policy');
  return array('ok'=>TRUE,'values'=>array('mode'=>$mode,'interval_minutes'=>(int)$minutes,'daily_time'=>$daily,'template'=>trim(str_replace("\r\n","\n",$template)),'send_empty'=>$category==='visitors'?(int)($v['send_empty']??0):0));
 }
 public function next_due($policy,$now)
 {
  if($policy['mode']==='realtime')return NULL;
  if($policy['mode']==='interval')return gmdate('Y-m-d H:i:s',$now+(int)$policy['interval_minutes']*60);
  $local=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Asia/Jakarta'));
  list($hour,$minute)=array_map('intval',explode(':',$policy['daily_time']));$next=$local->setTime($hour,$minute,0);
  if($next->getTimestamp()<=$now)$next=$next->modify('+1 day');
  return $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
 }
 public function local_time($utc){return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d/m/Y H:i').' WIB';}
 public function render($template,$values)
 {
  $replacements=array();foreach($values as $key=>$value)$replacements['{'.$key.'}']=(string)$value;
  return trim(strtr($template,$replacements));
 }
 public function chunks($text)
 {
  // Keep the existing gateway's 1,500-character limit, including part labels.
  if(mb_strlen($text)<=1500)return array($text);
  $chunks=array();while($text!==''){$part=mb_substr($text,0,1400);$cut=mb_strrpos($part,"\n");if(mb_strlen($text)>1400&&$cut!==FALSE&&$cut>700)$part=mb_substr($part,0,$cut+1);$chunks[]=$part;$text=mb_substr($text,mb_strlen($part));}
  $total=count($chunks);foreach($chunks as $i=>&$chunk)$chunk='Bagian '.($i+1).'/'.$total."\n".$chunk;unset($chunk);return $chunks;
 }
 public function sample($category)
 {
  $values=array('judul'=>'Ringkasan '.$this->categories()[$category],'isi'=>"1. Orang tua Sari menambahkan Bima sebagai akun anak.\n2. Orang tua Dimas menambahkan Nara sebagai akun anak.",'jenis'=>$this->categories()[$category],'jumlah'=>'2','periode'=>'27/10/2026 06:00–12:00 WIB','waktu'=>'27/10/2026 12:00 WIB','tautan'=>'https://www.projectpintar.com/kelola/pengguna');
  $examples=array('members'=>'Rani baru mendaftar sebagai orang tua lewat Google.','payments'=>'Rani membuat pesanan #125 senilai Rp50.000. Status: menunggu pembayaran.','donations'=>'Dimas membuat donasi #126 senilai Rp25.000.','help'=>'Sari mengirim permintaan bantuan #32. Topik: Jadwal kursus.');
  if(isset($examples[$category])){$values['isi']=$examples[$category];$values['jumlah']='1';}
  if($category==='visitors'){$values['judul']='Laporan trafik';$values['isi']='Situs diakses 240 kali: 210 akses nonbot dan 30 akses bot / alat. Ini jumlah akses, bukan orang unik.';$values['jumlah']='240';$values['tautan']='https://www.projectpintar.com/kelola/trafik';$values+=array('kunjungan'=>'240','manusia'=>'210','bot'=>'30');}
  return $values;
 }
}
