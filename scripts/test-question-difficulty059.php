<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') exit(2);
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean();
$ci =& get_instance();
$ci->load->library('question_import');
$ci->load->model(array('Bank_model','Exam_model','Standalone_model','Account_model','Practice_model'));
$bank=$ci->Bank_model; $exam=$ci->Exam_model; $drill=$ci->Standalone_model;
$admin=$ci->Account_model->find_active_user(1);
function diff_check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$salt=bin2hex(random_bytes(5));
$base=array('mapel'=>'matematika','kelas'=>'sd_1','tingkat'=>'basic','soal'=>'','a'=>'1','b'=>'2','c'=>'3','d'=>'4','kunci'=>'A','bahasan'=>'Jawaban satu.','gambar'=>'','kesulitan'=>'');
$ids=array();
foreach (array('mudah'=>'easy','sedang'=>'medium','susah'=>'hard',''=>'') as $label=>$tag) {
 $row=$base; $row['soal']='QA kesulitan '.$salt.' '.($label?:'tanpa tag').' pilih angka satu.'; $row['kesulitan']=$label;
 $q=$bank->normalize_rows(array($row),'owned','QA difficulty fixture')[0];
 diff_check($q['question_difficulty']===($tag?:NULL),'Tag normalization');
 $r=$bank->save($admin,0,'0',$q,'published'); diff_check($r['ok'],'Bank save '.$label);
 $ids[$tag?:'untagged']=$r['id'];
 diff_check($bank->get($r['id'])['question_difficulty']===($tag?:NULL),'Tag persisted');
}
$invalid=$base;$invalid['soal']='QA invalid '.$salt;$invalid['kesulitan']='ekstrem';
try{$bank->normalize_rows(array($invalid),'owned','QA invalid difficulty');throw new RuntimeException('Invalid tag accepted');}
catch(RuntimeException $error){diff_check(strpos($error->getMessage(),'Kesulitan soal')!==FALSE,'Invalid tag rejected');}
foreach(array('easy','medium','hard','untagged') as $tag){$list=$bank->listing('sd_1',1,'published',1,$tag);diff_check(in_array((int)$ids[$tag],array_map('intval',array_column($list['rows'],'id')),TRUE),'Bank filter '.$tag);}
$legacy=array_slice(Question_import::HEADERS,0,-1);
$tmp=tempnam(sys_get_temp_dir(),'difficulty-');
try{$h=fopen($tmp,'w');fputcsv($h,$legacy);fputcsv($h,array_slice($ci->question_import->sample(),0,-1));fclose($h);$read=$ci->question_import->read($tmp,'csv');diff_check($read[0]['kesulitan']==='','Legacy CSV optional tag');}
finally{unlink($tmp);}
$settings=array('title'=>'QA kesulitan '.$salt,'grade_id'=>'3','question_difficulty'=>'easy','duration_minutes'=>'10','max_daily'=>'3','credit_price'=>'0','revision'=>'0','status'=>'draft','question_shuffle'=>'1','option_shuffle'=>'1','parent_live_enabled'=>'0','available_from'=>'','available_until'=>'')+Practice_sheet::defaults();
$created=$exam->save($admin,0,$settings);diff_check($created['ok'],'Exam create');$exam_id=$created['id'];
$mismatch=$exam->select($admin,$exam_id,'1',array((string)$ids['medium']));diff_check(($mismatch['code']??0)===409,'Exam rejects mismatched tag: '.json_encode($mismatch));
$untagged=$exam->select($admin,$exam_id,'1',array((string)$ids['untagged']));diff_check(($untagged['code']??0)===409,'Exam rejects untagged question: '.json_encode($untagged));
diff_check($exam->select($admin,$exam_id,'1',array((string)$ids['easy']))['ok'],'Exam accepts tagged question');
$edit=$bank->normalize_rows(array(array_replace($base,array('soal'=>'QA kesulitan '.$salt.' mudah pilih angka satu.','kesulitan'=>'sedang'))),'owned','QA difficulty fixture')[0];
diff_check($bank->save($admin,$ids['easy'],(string)$bank->get($ids['easy'])['content_version'],$edit,'published')['code']===409,'Linked tag cannot change');
$settings['revision']=(string)$exam->get($exam_id)['revision'];$settings['question_difficulty']='hard';
diff_check($exam->save($admin,$exam_id,$settings)['code']===409,'Exam filter cannot conflict with selected questions');
$settings['question_difficulty']='easy';$settings['status']='published';
diff_check($exam->save($admin,$exam_id,$settings)['ok'],'Publish tagged exam');
$form=array('grade_id'=>'3','subject_id'=>'1','question_difficulty'=>'easy','count'=>'1','minutes'=>'0','option_shuffle'=>'0')+Practice_sheet::defaults();
$started=$drill->start($admin,$form,bin2hex(random_bytes(32)));diff_check($started['ok'],'Tagged self-practice start');
$attempt=$ci->Practice_model->find_attempt($started['id'],$admin['id']);
$selected=$ci->Practice_model->display_questions($attempt);diff_check(count($selected)===1&&$bank->get($selected[0]['id'])['question_difficulty']==='easy','Tagged self-practice selection');
diff_check($ci->Practice_model->save_answers($started['id'],$admin['id'],0,array(),TRUE)['ok'],'Finish tagged session');
$form['question_difficulty']='hard';$form['count']='50';$before=$drill->quota($admin['id']);
$short=$drill->start($admin,$form,bin2hex(random_bytes(32)));diff_check($short['code']===409&&$drill->quota($admin['id'])===$before,'Insufficient filtered pool does not consume quota');
echo "PASS 059 difficulty: optional imports, bank tags and filters, exam enforcement, self-practice selection, quota on shortage.\n";
