<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Telegram_gateway
{
 public function valid_chat_id($chat)
 {
  // Private chats have positive IDs; groups and supergroups have negative IDs.
  return is_string($chat) && (bool)preg_match('/\A(?:[1-9][0-9]{0,18}|-[1-9][0-9]{4,18})\z/',$chat);
 }
 private function api($token,$method,$parameters=array())
 {
  if(!preg_match('/\A[0-9]{6,16}:[A-Za-z0-9_-]{25,80}\z/',$token) || !in_array($method,array('getMe','getUpdates','setMyCommands'),TRUE)){return array('ok'=>FALSE,'error'=>'invalid_configuration');}
  $ci =& get_instance();
  if(ENVIRONMENT!=='production' || preg_match('/\Astudy_qa_/',$ci->db->database)){
   $fixture=getenv('STUDY_TELEGRAM_PAIR_TEST_'.strtoupper($method));
   if($fixture===FALSE){return array('ok'=>FALSE,'error'=>'development_transport_disabled');}
   $data=json_decode($fixture,TRUE);
   return is_array($data)?$data:array('ok'=>FALSE,'error'=>'invalid_fixture');
  }
  $body='';$h=curl_init('https://api.telegram.org/bot'.$token.'/'.$method);
  curl_setopt_array($h,array(CURLOPT_POST=>TRUE,CURLOPT_POSTFIELDS=>json_encode((object)$parameters,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>array('Content-Type: application/json'),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>FALSE,CURLOPT_SSL_VERIFYPEER=>TRUE,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($h,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>524288){return 0;}$body.=$chunk;return strlen($chunk);}));
  $ok=curl_exec($h);$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
  if($ok===FALSE){return array('ok'=>FALSE,'error'=>'unavailable');}
  $data=json_decode($body,TRUE);
  if($http===200 && is_array($data) && ($data['ok']??FALSE)===TRUE){return $data;}
  return array('ok'=>FALSE,'error'=>$http===409?'webhook_active':(in_array($http,array(401,403,404),TRUE)?'invalid_token':'unavailable'));
 }
 public function bot_username($token)
 {
  $response=$this->api($token,'getMe');
  if(!$response['ok']){return $response;}
  $bot=$response['result']??NULL;$name=is_array($bot)?($bot['username']??NULL):NULL;
  if(($bot['is_bot']??FALSE)!==TRUE || !is_string($name) || !preg_match('/\A[A-Za-z0-9_]{5,32}\z/',$name)){return array('ok'=>FALSE,'error'=>'invalid_bot');}
  return array('ok'=>TRUE,'username'=>$name);
 }
 public function updates($token,$offset)
 {
  if(!is_int($offset) || $offset<0){return array('ok'=>FALSE,'error'=>'invalid_configuration');}
  return $this->api($token,'getUpdates',array('offset'=>$offset,'limit'=>100,'timeout'=>0,'allowed_updates'=>array('message')));
 }
 public function set_commands($token)
 {
  $ci =& get_instance();
  $ci->load->model('Telegram_bot_commands_model');
  return $this->api($token,'setMyCommands',array('commands'=>$ci->Telegram_bot_commands_model->telegram_commands()));
 }
 public function find_private_chat($token,$code,$issued_at)
 {
  if(!is_string($code) || !preg_match('/\A[a-f0-9]{32}\z/',$code) || !is_int($issued_at)){return array('ok'=>FALSE,'error'=>'invalid_configuration');}
  $ci =& get_instance();
  $stored=$ci->db->query('SELECT chat_id FROM st_telegram_pair_updates WHERE code_hash=? AND message_at>=? AND message_at<=? LIMIT 1',array(hash('sha256',$code),gmdate('Y-m-d H:i:s',$issued_at-5),gmdate('Y-m-d H:i:s',$issued_at+600)))->row_array();
  if($stored && $this->valid_chat_id($stored['chat_id'])){return array('ok'=>TRUE,'chat_id'=>$stored['chat_id']);}
  // No offset: this reads pending updates without confirming or discarding them.
  $response=$this->api($token,'getUpdates',array('limit'=>100,'timeout'=>0));
  if(!$response['ok']){return $response;}
  if(!is_array($response['result']??NULL)){return array('ok'=>FALSE,'error'=>'invalid_response');}
  foreach($response['result'] as $update){
   $message=$update['message']??NULL;if(!is_array($message)){continue;}
   $chat=$message['chat']??NULL;$from=$message['from']??NULL;
   if(!is_array($chat) || !is_array($from) || ($chat['type']??'')!=='private' || !is_int($chat['id']??NULL) || !is_int($from['id']??NULL) || $chat['id']!==$from['id'] || !empty($from['is_bot'])){continue;}
   $id=(string)$chat['id'];$date=$message['date']??NULL;
   if(!$this->valid_chat_id($id) || !is_int($date) || $date<$issued_at-5 || $date>$issued_at+600 || !is_string($message['text']??NULL) || !hash_equals('/start pp_'.$code,$message['text'])){continue;}
   return array('ok'=>TRUE,'chat_id'=>$id);
  }
  return array('ok'=>FALSE,'error'=>count($response['result'])>=100?'updates_full':'not_found');
 }
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
 public function send($token,$chat,$text,$reply_to=NULL)
 {
  // No configurable endpoint, HTML parse mode, remote media or redirects.
  if(!preg_match('/\A[0-9]{6,16}:[A-Za-z0-9_-]{25,80}\z/',$token) || !$this->valid_chat_id($chat) || mb_strlen($text)>1500 || ($reply_to!==NULL && (!is_int($reply_to) || $reply_to<1))){return array('ok'=>FALSE,'error'=>'invalid_configuration');}
  $ci =& get_instance();
  if(ENVIRONMENT!=='production' || preg_match('/\Astudy_qa_/',$ci->db->database)){
   $mode=getenv('STUDY_TELEGRAM_TEST');if($mode==='rate_limited'){return array('ok'=>FALSE,'error'=>'rate_limited','retry'=>60);}return $mode==='success'?array('ok'=>TRUE):array('ok'=>FALSE,'error'=>$mode==='uncertain'?'uncertain':'development_transport_disabled');
  }
  $payload=array('chat_id'=>$chat,'text'=>$text,'link_preview_options'=>array('is_disabled'=>TRUE),'protect_content'=>TRUE);
  if($reply_to!==NULL){$payload['reply_parameters']=array('message_id'=>$reply_to,'allow_sending_without_reply'=>TRUE);}
  $body='';$h=curl_init('https://api.telegram.org/bot'.$token.'/sendMessage');
  curl_setopt_array($h,array(CURLOPT_POST=>TRUE,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>array('Content-Type: application/json'),CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>FALSE,CURLOPT_SSL_VERIFYPEER=>TRUE,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($h,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>65536){return 0;}$body.=$chunk;return strlen($chunk);}));
  $ok=curl_exec($h);$http=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
  if($ok===FALSE){return array('ok'=>FALSE,'error'=>'uncertain');}
  $data=json_decode($body,TRUE);
  if($http===200 && ($data['ok']??FALSE)===TRUE){return array('ok'=>TRUE);}
  if($http===429){return array('ok'=>FALSE,'error'=>'rate_limited','retry'=>min(86400,max(60,(int)($data['parameters']['retry_after']??60))));}
  return array('ok'=>FALSE,'error'=>in_array($http,array(400,401,403,404),TRUE)?'bot_or_chat_denied':'uncertain');
 }
}
