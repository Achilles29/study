<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class English_course_content
{
 public $data;
 public $units=array();
 public $levels=array();
 public function __construct()
 {
  $this->data=json_decode(file_get_contents(APPPATH.'content/english_hack/v1/curriculum.json'),TRUE,512,JSON_THROW_ON_ERROR);
  foreach($this->data['levels'] as $level){$this->levels[$level['id']]=$level;foreach($level['units'] as $u){$u['level_id']=$level['id'];$this->units[$u['id']]=$u;}}
 }
 public function version(){return $this->data['version'];}
 public function unit($id){return $this->units[$id]??NULL;}
 public function level($id){return $this->levels[$id]??NULL;}
 public function questions($unit_id,$kind)
 {
  if(in_array($kind,array('practice','review'),TRUE)) return $this->unit($unit_id)['questions']??array();
  $level=$this->level($unit_id);if(!$level)return array();
  $questions=$level['checkpoint'];
  foreach($level['units'] as $unit){$pool=$unit['questions'];shuffle($pool);$questions=array_merge($questions,array_slice($pool,0,2));}
  return $questions;
 }
 public function snapshot($unit_id,$kind,$pass_score)
 {
  $questions=$this->questions($unit_id,$kind);shuffle($questions);
  foreach($questions as &$q){if($q['type']==='choice'){$correct=$q['options'][$q['answer_index']];shuffle($q['options']);$q['answer_index']=array_search($correct,$q['options'],TRUE);}}unset($q);
  return array('questions'=>$questions,'pass_score'=>(int)$pass_score,'title'=>$kind==='checkpoint'?$this->level($unit_id)['cefr'].' · Checkpoint':$this->unit($unit_id)['title']);
 }
 public function task($unit_id,$kind)
 {
  if($kind==='capstone'){$l=$this->level($unit_id);return $l?array('prompt'=>$l['capstone']['brief'],'criteria'=>$l['capstone']['criteria'],'title'=>$l['capstone']['title']):NULL;}
  $u=$this->unit($unit_id);if(!$u)return NULL;
  foreach($u['tasks'] as $t)if($t['skill']===$kind)return $t+array('title'=>$u['title']);return NULL;
 }
 public function normalise($text){return preg_replace('/\s+/u',' ',preg_replace('/[.!?,;:]+$/u','',mb_strtolower(str_replace(array('’','‘'),"'",trim($text)),'UTF-8')));}
 public function grade($snapshot,$answers)
 {
  if(!is_array($answers)||count($answers)!==count($snapshot['questions']))return NULL;
  $result=array();$correct=0;
  foreach($snapshot['questions'] as $q){$a=$answers[$q['id']]??NULL;if(!is_string($a)||trim($a)===''||mb_strlen($a)>500||!mb_check_encoding($a,'UTF-8'))return NULL;
   if($q['type']==='choice'){if(!ctype_digit($a)||!isset($q['options'][(int)$a]))return NULL;$ok=(int)$a===$q['answer_index'];}
   else{$ok=in_array($this->normalise($a),array_map(array($this,'normalise'),$q['answers']),TRUE);}
   $result[$q['id']]=array('value'=>$a,'correct'=>$ok);$correct+=$ok?1:0;
  }
  return array('answers'=>$result,'score'=>round($correct*100/count($result),2),'correct'=>$correct);
 }
}
