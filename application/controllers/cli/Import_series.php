<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Add-only import for independently authored secondary-school course manuscripts. */
class Import_series extends CI_Controller
{
	private function audit($id, $details)
	{
		$this->db->insert('st_audit_events', array('actor_user_id'=>1,'event_type'=>'content.series_imported','entity_type'=>'course','entity_id'=>$id,'details_json'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>gmdate('Y-m-d H:i:s')));
	}

	public function index($grade = '', $mode = 'check')
	{
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || ! in_array($mode,array('check','apply'),TRUE)) { show_404(); exit(1); }
		if ($mode === 'apply' && getenv('STUDY_IMPORT_SERIES') !== $grade) { throw new RuntimeException('Import gate disabled.'); }
		$this->load->library('Curriculum_series');
		$courses = $this->curriculum_series->load($grade);
		$this->load->database(); $this->db->db_debug = FALSE;
		if ( ! in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)) { throw new RuntimeException('Database denied.'); }
		if ( ! $this->db->query("SELECT u.id FROM st_users u JOIN st_user_roles ur ON ur.user_id=u.id JOIN st_roles r ON r.id=ur.role_id WHERE u.id=1 AND u.status='active' AND r.role_code='system_admin'")->num_rows()) { throw new RuntimeException('Active administrator required.'); }
		$lock='study_series_'.$grade;
		if ((int)$this->db->query('SELECT GET_LOCK(?,5) locked',array($lock))->row_array()['locked']!==1) { throw new RuntimeException('Import busy.'); }
		$started=FALSE; $report=array();
		try
		{
			if ( ! $this->db->trans_begin()) { throw new RuntimeException('Transaction unavailable.'); } $started=TRUE;
			foreach ($courses as $course)
			{
				$existing=$this->db->where('slug',$course['slug'])->get('st_courses')->row_array();
				if ($existing)
				{
					$audit=$this->db->where('event_type','content.series_imported')->where('entity_type','course')->where('entity_id',$existing['id'])->get('st_audit_events')->row_array();
					$meta=$audit?json_decode($audit['details_json'],TRUE):NULL;
					if ( ! $meta || ($meta['grade']??'')!==$grade || ($meta['source_sha256']??'')!==$course['source_sha256']) { throw new RuntimeException('Existing course/source conflict: '.$course['slug']); }
					$report[]=array('slug'=>$course['slug'],'action'=>'preserved','status'=>$existing['status']); continue;
				}
				$category=$this->db->where('slug',$course['category'])->where('status','active')->get('st_categories')->row_array();
				if ( ! $category && in_array($course['category'],array('sosiologi','matematika-lanjut','koding-ka'),TRUE) && $mode==='apply')
				{
					$name=array('sosiologi'=>'Sosiologi','matematika-lanjut'=>'Matematika Tingkat Lanjut','koding-ka'=>'Koding dan Kecerdasan Artifisial')[$course['category']]; $now=gmdate('Y-m-d H:i:s');
					if ( ! $this->db->insert('st_categories',array('slug'=>$course['category'],'name'=>$name,'description'=>'Mata pelajaran pilihan','sort_order'=>50,'status'=>'active','created_at'=>$now,'updated_at'=>$now))) { throw new RuntimeException('Category insert failed.'); }
					$category=$this->db->where('slug',$course['category'])->get('st_categories')->row_array();
				}
				if ( ! $category && in_array($course['category'],array('sosiologi','matematika-lanjut','koding-ka'),TRUE) && $mode==='check') { $category=array('id'=>0); }
				$grade_row=$this->db->where('code',$grade)->get('st_learning_grades')->row_array();
				if ( ! $category || ! $grade_row) { throw new RuntimeException('Required category/grade missing: '.$course['category']); }
				$lesson_count=array_sum(array_map(static function($c){return count($c['lessons']);},$course['chapters']));
				if ($mode==='check') { $report[]=array('slug'=>$course['slug'],'action'=>'would_create','chapters'=>count($course['chapters']),'lessons'=>$lesson_count); continue; }
				$now=gmdate('Y-m-d H:i:s'); $dates=array('created_at'=>$now,'updated_at'=>$now);
				$course_data=array('category_id'=>$category['id'],'grade_id'=>$grade_row['id'],'author_user_id'=>1,'updated_by_user_id'=>1,'title'=>$course['title'],'slug'=>$course['slug'],'summary'=>$course['summary'],'learning_objectives'=>$course['objectives'],'prerequisites'=>$course['prerequisites'],'target_group'=>str_starts_with($grade,'sd_')?'primary':'secondary','estimated_minutes'=>$lesson_count*20,'status'=>'draft','content_version'=>1,'rights_status'=>'owned','source_note'=>$course['source'],'is_free'=>1)+$dates;
				if ( ! $this->db->insert('st_courses',$course_data)) { throw new RuntimeException('Course insert failed.'); }
				$id=(int)$this->db->insert_id(); $ordinal=0;
				foreach ($course['chapters'] as $si=>$chapter)
				{
					if ( ! $this->db->insert('st_course_sections',array('course_id'=>$id,'title'=>$chapter['title'],'sort_order'=>$si+1)+$dates)) { throw new RuntimeException('Chapter insert failed.'); }
					$section_id=(int)$this->db->insert_id();
					foreach ($chapter['lessons'] as $li=>$lesson)
					{
						$ordinal++;
						if ( ! $this->db->insert('st_lessons',array('section_id'=>$section_id,'title'=>$lesson['title'],'slug'=>$course['slug'].'-sesi-'.$ordinal,'body_text'=>$lesson['body'],'estimated_minutes'=>$lesson['minutes'],'sort_order'=>$li+1,'status'=>'draft','content_version'=>1,'is_preview'=>0,'rights_status'=>'owned','source_note'=>$course['source'])+$dates)) { throw new RuntimeException('Lesson insert failed.'); }
				}
				}
				$this->audit($id,array('grade'=>$grade,'source_sha256'=>$course['source_sha256'],'chapters'=>count($course['chapters']),'lessons'=>$lesson_count));
				$report[]=array('id'=>$id,'slug'=>$course['slug'],'action'=>'created_draft','chapters'=>count($course['chapters']),'lessons'=>$lesson_count);
			}
			if ( ! $this->db->trans_status()) { throw new RuntimeException('Import transaction failed.'); }
			if ($mode==='check') { $this->db->trans_rollback(); }
			elseif ( ! $this->db->trans_commit()) { throw new RuntimeException('Commit uncertain.'); }
			$started=FALSE;
			echo json_encode(array('grade'=>$grade,'mode'=>$mode,'courses'=>$report),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
		}
		catch(Throwable $error) { if ($started) { $this->db->trans_rollback(); } throw $error; }
		finally { $this->db->query('SELECT RELEASE_LOCK(?)',array($lock)); }
	}
}
