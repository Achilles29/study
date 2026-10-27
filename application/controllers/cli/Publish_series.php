<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Publishes only byte-for-byte checked manuscripts through the course review model. */
class Publish_series extends CI_Controller
{
	public function index($grade = '', $mode = 'check')
	{
		if (PHP_SAPI !== 'cli' || ENVIRONMENT !== 'development' || ! in_array($mode,array('check','apply'),TRUE)) { show_404(); exit(1); }
		if ($mode==='apply' && getenv('STUDY_PUBLISH_SERIES')!==$grade) { throw new RuntimeException('Publication gate disabled.'); }
		$this->load->library('Curriculum_series'); $courses=$this->curriculum_series->load($grade);
		$this->load->database(); $this->db->db_debug=FALSE;
		if ( ! in_array($this->db->database,array('db_study','study_qa_monitor_sso039'),TRUE)) { throw new RuntimeException('Database denied.'); }
		$this->load->model('Content_model');
		$lock='study_series_'.$grade;
		if ((int)$this->db->query('SELECT GET_LOCK(?,5) locked',array($lock))->row_array()['locked']!==1) { throw new RuntimeException('Publication busy.'); }
		try
		{
			$ready=array(); $report=array();
			foreach ($courses as $expected)
			{
				$course=$this->db->where('slug',$expected['slug'])->get('st_courses')->row_array();
				if ( ! $course) { throw new RuntimeException('Import first: '.$expected['slug']); }
				$audit=$this->db->where('event_type','content.series_imported')->where('entity_type','course')->where('entity_id',$course['id'])->get('st_audit_events')->row_array();
				$meta=$audit?json_decode($audit['details_json'],TRUE):NULL;
				if ( ! $meta || ($meta['grade']??'')!==$grade || ($meta['source_sha256']??'')!==$expected['source_sha256']) { throw new RuntimeException('Provenance mismatch.'); }
				foreach (array('title'=>'title','summary'=>'summary','learning_objectives'=>'objectives','prerequisites'=>'prerequisites','source_note'=>'source') as $field=>$key)
				{ if ($course[$field]!==$expected[$key]) { throw new RuntimeException('Course content changed.'); } }
				$sections=$this->db->where('course_id',$course['id'])->order_by('sort_order')->order_by('id')->get('st_course_sections')->result_array();
				if (count($sections)!==count($expected['chapters'])) { throw new RuntimeException('Chapter count changed.'); }
				foreach ($sections as $si=>$section)
				{
					$chapter=$expected['chapters'][$si];
					if ($section['title']!==$chapter['title']) { throw new RuntimeException('Chapter title changed.'); }
					$lessons=$this->db->where('section_id',$section['id'])->order_by('sort_order')->order_by('id')->get('st_lessons')->result_array();
					if (count($lessons)!==count($chapter['lessons'])) { throw new RuntimeException('Lesson count changed.'); }
					foreach ($lessons as $li=>$lesson)
					{ if ($lesson['title']!==$chapter['lessons'][$li]['title'] || $lesson['body_text']!==$chapter['lessons'][$li]['body']) { throw new RuntimeException('Lesson manuscript changed.'); } }
				}
				$report[]=array('id'=>(int)$course['id'],'slug'=>$course['slug'],'status'=>$course['status']);
				if ($course['status']!=='published') { $ready[]=$course; }
			}
			if ($mode==='apply')
			{
				foreach ($ready as $course)
				{
					if ($course['status']==='draft') { $result=$this->Content_model->submit_for_review($course['id'],1); if ( ! $result['ok']) { throw new RuntimeException($result['message']); } }
					elseif ($course['status']!=='review') { throw new RuntimeException('Unexpected course status.'); }
					$notes='Naskah orisinal seri 2026, grade '.$grade.'. Agen memeriksa kesesuaian naskah, asal rujukan, cakupan bab, contoh, dan keterbacaan sebelum terbit atas instruksi pemilik. Belum ditinjau pendidik independen.';
					$result=$this->Content_model->review_course($course['id'],1,'approved',$notes,TRUE,(int)$course['content_version']);
					if ( ! $result['ok']) { throw new RuntimeException($result['message']); }
				}
			}
			echo json_encode(array('grade'=>$grade,'mode'=>$mode,'courses'=>$report,'published_now'=>count($ready)*($mode==='apply'?1:0)),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
		}
		finally { $this->db->query('SELECT RELEASE_LOCK(?)',array($lock)); }
	}
}
