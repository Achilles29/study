<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Arena_credit_model extends CI_Model
{
 public function __construct(){parent::__construct();$this->load->model(array('Bank_model','Settings_model'));}
 public function settings(){return $this->Settings_model->get_section('akses_arena');}
 public function group($id){$g=$this->Bank_model->rows('SELECT * FROM st_arena_access_groups WHERE id=?',array($id))[0]??NULL;if($g){$g['packs']=$this->Bank_model->rows('SELECT p.id,p.slug,p.title,p.enabled FROM st_arena_access_packs a JOIN st_arena_packs p ON p.id=a.pack_id WHERE a.group_id=? ORDER BY p.id',array($id));}return $g;}
 public function access($user,$pack)
 {
  $g=$this->Bank_model->rows('SELECT g.* FROM st_arena_access_groups g JOIN st_arena_access_packs p ON p.group_id=g.id WHERE p.pack_id=?',array($pack))[0]??NULL;$enabled=$this->settings()['values']['enabled']==='1';
  $this->load->model('Feature_credit_model');$owned=$g&&$this->Feature_credit_model->owns($user,'arena',$g['id']);
  return array('allowed'=>!$enabled||($g&&((int)$g['price_credits']===0||$owned)),'enabled'=>$enabled,'owned'=>$owned,'group'=>$g);
 }
 public function groups($type)
 {
  $groups=$this->Bank_model->rows('SELECT * FROM st_arena_access_groups WHERE game_type=? ORDER BY access_level,id',array($type));foreach($groups as &$g){$g['packs']=$this->group($g['id'])['packs'];}unset($g);return $groups;
 }
 public function save($user,$id,$price,$revision)
 {
  if(!is_string($price)||!in_array($price,array('0','10','20','30','40','50'),TRUE)||!is_string($revision)||!ctype_digit($revision)){return array('ok'=>FALSE,'code'=>400);}
  return $this->study_transaction->run($this->db,function()use($user,$id,$price,$revision){
   if(!$this->Bank_model->administrator($user)){return array('ok'=>FALSE,'code'=>403);}$g=$this->Bank_model->rows('SELECT * FROM st_arena_access_groups WHERE id=? FOR UPDATE',array($id))[0]??NULL;if(!$g||(int)$g['revision']!==(int)$revision){return array('ok'=>FALSE,'code'=>409);}if(((int)$g['access_level']<=2&&$price!=='0')||((int)$g['access_level']>2&&$price==='0')){return array('ok'=>FALSE,'code'=>400);}
   $this->db->where('id',$id)->update('st_arena_access_groups',array('price_credits'=>(int)$price,'revision'=>(int)$g['revision']+1));$this->db->insert('st_audit_events',array('actor_user_id'=>$user['id'],'event_type'=>'arena.credit_price_changed','entity_type'=>'arena_access_group','entity_id'=>$id,'details_json'=>json_encode(array('before'=>(int)$g['price_credits'],'after'=>(int)$price)),'created_at'=>gmdate('Y-m-d H:i:s')));return array('ok'=>TRUE);
  });
 }
}
