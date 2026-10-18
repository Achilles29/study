<?php
defined('BASEPATH') OR exit('No direct script access allowed');
function study_grades()
{
 static $grades;
 if ($grades === NULL) { $ci =& get_instance(); $grades=array_column($ci->db->order_by('sort_order,id')->get('st_learning_grades')->result_array(),NULL,'code'); }
 return $grades;
}
function study_grade_filter()
{
 $ci =& get_instance(); $value=$ci->input->get('kelas',FALSE) ?? '';
 if (!is_string($value) || ($value!=='' && $value!=='lintas-kelas' && !isset(study_grades()[$value]))) { show_error('Pilih kelas yang tersedia.',400,'Filter kelas tidak valid'); exit; }
 return $value;
}
function study_grade_select($value, $name='kelas', $form=FALSE)
{
 $out='<label class="form-label" for="'.html_escape($name).'">Kelas / kelompok</label><select class="form-select" id="'.html_escape($name).'" name="'.html_escape($name).'">';
 $options=array(''=>$form?'Lintas kelas / belum ditentukan':'Semua kelas');
 if (!$form) { $options['lintas-kelas']='Lintas kelas / belum ditentukan'; }
 foreach(study_grades() as $code=>$grade){$options[$form?(string)$grade['id']:$code]=$grade['name'];}
 foreach($options as $key=>$label){$out.='<option value="'.html_escape((string)$key).'"'.((string)$key===(string)$value?' selected':'').'>'.html_escape($label).'</option>';}
 return $out.'</select>';
}
function study_grade_label($id)
{
 foreach(study_grades() as $g){if((int)$g['id']===(int)$id){return $g['name'];}}
 return 'Lintas kelas / belum ditentukan';
}
function study_grade_code($id)
{
 foreach(study_grades() as $code=>$grade){if((int)$grade['id']===(int)$id){return $code;}}
 return 'lintas-kelas';
}
function study_grade_match($id,$code)
{
 return $code==='' || ($code==='lintas-kelas' ? $id===NULL : (int)$id===(int)study_grades()[$code]['id']);
}
function study_grade_where($code,$column='c.grade_id')
{
 if ($code==='') { return; }
 if (!in_array($column,array('c.grade_id','grade_id'),TRUE)) { throw new RuntimeException('Invalid grade column.'); }
 $ci =& get_instance(); $ci->db->where($column,$code==='lintas-kelas'?NULL:(int)study_grades()[$code]['id']);
}
function study_grade_arena($packs,$code)
{
 static $map;
 if($map===NULL){$ci =& get_instance();$map=array_column($ci->db->query('SELECT m.pack_id,c.grade_id FROM st_arena_pack_categories m JOIN st_arena_categories c ON c.id=m.arena_category_id')->result_array(),'grade_id','pack_id');}
 return array_values(array_filter($packs,static function($p)use($map,$code){return study_grade_match($map[$p['id']]??NULL,$code);}));
}
