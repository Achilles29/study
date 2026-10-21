<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Telegram_gateway
{
 private function key()
 {
  $ci =& get_instance();$key=$ci->config->item('encryption_key');
  if(!is_string($key) || strlen($key)<24){throw new RuntimeException('Notification encryption unavailable.');}
  return hash_hkdf('sha256',$key,32,'projectpintar/telegram/v1');
 }
 public function seal($token)
 {
  $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($token,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$nonce,$tag,'telegram-v1',16);
  if($cipher===FALSE){throw new RuntimeException('Notification encryption failed.');}return base64_encode($nonce.$tag.$cipher);
 }
 public function open($cipher)
 {
  $raw=base64_decode((string)$cipher,TRUE);if($raw===FALSE || strlen($raw)<29){throw new RuntimeException('Notification secret unavailable.');}
  $value=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'telegram-v1');
  if($value===FALSE){throw new RuntimeException('Notification secret unavailable.');}return $value;
 }
 public function send($token,$chat,$text)
 {
  // No configurable endpoint, HTML parse mode, remote media or redirects.
  if(!preg_match('/\A[0-9]{6,16}:[A-Za-z0-9_-]{25,80}\z/',$token) || !preg_match('/\A-[1-9][0-9]{4,18}\z/',$chat) || mb_strlen($text)>1500){return array('ok'=>FALSE,'error'=>'invalid_configuration');}
  $ci =& get_instance();
  if(ENVIRONMENT!=='production' || preg_match('/\Astudy_qa_/',$ci->db->database)){
   $mode=getenv('STUDY_TELEGRAM_TEST');if($mode==='rate_limited'){return array('ok'=>FALSE,'error'=>'rate_limited','retry'=>60);}return $mode==='success'?array('ok'=>TRUE):array('ok'=>FALSE,'error'=>$mode==='uncertain'?'uncertain':'development_transport_disabled');
  }
  $body='';$h=curl_init('https://api.telegram.org/bot'.$token.'/sendMessage');
  curl_setopt_array($h,array(CURLOPT_POST=>TRUE,CURLOPT_POSTFIELDS=>json_encode(array('chat_id'=>$chat,'text'=>$text,'link_preview_options'=>array('is_disabled'=>TRUE),'protect_content'=>TRUE)),CURLOPT_HTTPHEADER=>array('Content-Type: application/json'),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>FALSE,CURLOPT_SSL_VERIFYPEER=>TRUE,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($h,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>65536){return 0;}$body.=$chunk;return strlen($chunk);}));
  $ok=curl_exec($h);$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
  if($ok===FALSE){return array('ok'=>FALSE,'error'=>'uncertain');}
  $data=json_decode($body,TRUE);
  if($http===200 && ($data['ok']??FALSE)===TRUE){return array('ok'=>TRUE);}
  if($http===429){return array('ok'=>FALSE,'error'=>'rate_limited','retry'=>min(86400,max(60,(int)($data['parameters']['retry_after']??60))));}
  return array('ok'=>FALSE,'error'=>in_array($http,array(400,401,403,404),TRUE)?'bot_or_group_denied':'uncertain');
 }
}
