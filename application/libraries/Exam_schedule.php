<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Exam_schedule
{
 public static function from_form($value)
 {
  if($value==='')return NULL;
  if(!is_string($value)||!preg_match('/\A[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/',$value))throw new InvalidArgumentException('Tanggal harus berupa tanggal dan jam WIB yang valid.');
  $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone('Asia/Jakarta'));$error=DateTimeImmutable::getLastErrors();
  if(!$date||($error&&($error['warning_count']||$error['error_count']))||$date->format('Y-m-d\TH:i')!==$value)throw new InvalidArgumentException('Tanggal atau jam tidak valid.');
  return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
 }
 public static function to_form($value){return $value?(new DateTimeImmutable($value,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d\TH:i'):'';}
 public static function is_open($exam,$now=NULL){$now=$now??gmdate('Y-m-d H:i:s');return $exam&&$exam['status']==='published'&&(empty($exam['available_from'])||$exam['available_from']<=$now)&&(empty($exam['available_until'])||$exam['available_until']>$now);}
 public static function message($exam,$now=NULL){$now=$now??gmdate('Y-m-d H:i:s');if(!empty($exam['available_from'])&&$exam['available_from']>$now)return 'Simulasi belum dibuka. Tunggu tanggal mulai yang ditetapkan pengelola.';if(!empty($exam['available_until'])&&$exam['available_until']<=$now)return 'Jangka aktif simulasi telah berakhir. Sesi yang sudah dimulai tetap mengikuti batas waktu sesinya.';return '';}
}
