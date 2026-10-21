<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Migration_Add_arena_credit_rules extends CI_Migration
{
 public function up()
 {
  foreach(array(
   "CREATE TABLE IF NOT EXISTS st_arena_access_groups(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,game_type VARCHAR(32) NOT NULL,access_level INT UNSIGNED NOT NULL,price_credits INT UNSIGNED NOT NULL,revision INT UNSIGNED NOT NULL DEFAULT 1,UNIQUE KEY uq_arena_access_level(game_type,access_level)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
   "CREATE TABLE IF NOT EXISTS st_arena_access_packs(pack_id BIGINT UNSIGNED PRIMARY KEY,group_id BIGINT UNSIGNED NOT NULL,CONSTRAINT fk_access_pack FOREIGN KEY(pack_id) REFERENCES st_arena_packs(id),CONSTRAINT fk_access_group FOREIGN KEY(group_id) REFERENCES st_arena_access_groups(id)) ENGINE=InnoDB"
  ) as $sql){if(!$this->db->query($sql)){throw new RuntimeException('Arena credit schema unavailable.');}}
  $this->db->trans_begin();$ranks=array();
  foreach($this->db->order_by('game_type,id')->get('st_arena_packs')->result_array() as $pack){
   $type=$pack['game_type'];$ranks[$type]=($ranks[$type]??0)+1;if($this->db->where('pack_id',$pack['id'])->get('st_arena_access_packs')->num_rows()){continue;}$content=json_decode($pack['content_json'],TRUE);
   $level=in_array($type,array('math','expedition'),TRUE)?(int)$content['level']:$ranks[$type];
   if($type==='english'){$level=(int)$content['chapter'];if($level===0){$level=strpos($pack['slug'],'review-1')!==FALSE?6:12;}}
   $level=max(1,$level);$price=$level<=2?0:($level<=5?10:($level<=10?20:($level<=15?30:($level<=20?40:50))));
   $this->db->query('INSERT IGNORE INTO st_arena_access_groups(game_type,access_level,price_credits) VALUES(?,?,?)',array($type,$level,$price));$g=$this->db->where('game_type',$type)->where('access_level',$level)->get('st_arena_access_groups')->row_array();$this->db->insert('st_arena_access_packs',array('pack_id'=>$pack['id'],'group_id'=>$g['id']));
  }
  $this->load->library('site_settings');if(!$this->db->where('section','akses_arena')->get('st_site_settings')->num_rows()){$id=(int)$this->db->query('SELECT COALESCE(MAX(id),0)+1 id FROM st_site_settings')->row_array()['id'];$this->db->insert('st_site_settings',array('id'=>$id,'section'=>'akses_arena','values_json'=>json_encode($this->site_settings->defaults('akses_arena')),'revision'=>1,'updated_at'=>gmdate('Y-m-d H:i:s')));}
  if(!$this->db->trans_status()){$this->db->trans_rollback();throw new RuntimeException('Arena credit mapping unavailable.');}if(!$this->db->trans_commit()){throw new RuntimeException('Arena mapping commit unavailable.');}
 }
 public function down(){throw new RuntimeException('Preserve access and payment references.');}
}
