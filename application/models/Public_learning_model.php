<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only public discovery. Never reads learner attempts, families or bank questions. */
class Public_learning_model extends CI_Model
{
	private $catalog = NULL;
	public function subjects()
	{
		return array(
			'matematika' => array('label'=>'Matematika', 'category'=>'matematika', 'intro'=>'Belajar memahami bilangan, pola, bentuk, pengukuran, dan data melalui contoh serta langkah penyelesaian.'),
			'bahasa-indonesia' => array('label'=>'Bahasa Indonesia', 'category'=>'bahasa-indonesia', 'intro'=>'Latih pemahaman bacaan, kosakata, dan cara menyampaikan gagasan. Baca teks dengan teliti sebelum memilih jawaban.'),
			'bahasa-inggris' => array('label'=>'Bahasa Inggris', 'category'=>'bahasa-inggris', 'intro'=>'Kenali kosakata dan ungkapan sehari-hari melalui teks pendek. Kelas 1–2 merupakan pengayaan pengenalan bahasa.'),
			'ipas' => array('label'=>'IPAS', 'category'=>'ipas', 'intro'=>'Pelajari alam dan kehidupan masyarakat dengan pengamatan, hubungan sebab-akibat, dan contoh sekitar. IPAS tersedia mulai kelas 3.'),
			'pendidikan-pancasila' => array('label'=>'Pendidikan Pancasila', 'category'=>'ppkn', 'intro'=>'Pahami nilai Pancasila, aturan, tanggung jawab, dan kebersamaan melalui situasi sehari-hari.'),
			'pjok' => array('label'=>'PJOK', 'category'=>'penjaskes', 'intro'=>'Kenali gerak, kebiasaan sehat, dan keselamatan. Kuis menguji pengetahuan; praktik gerak tetap membutuhkan tempat aman dan pendamping.'),
			'seni-budaya' => array('label'=>'Seni Budaya', 'category'=>'seni-budaya', 'intro'=>'Jelajahi pengayaan rupa, musik, tari, dan teater. Jawaban kuis tidak menggantikan pengalaman berkarya dan praktik.')
		);
	}
	public function grade_intro($grade)
	{
		$intro = array(
			1=>'Mulai dari mengenal bilangan, membaca kata sederhana, dan kebiasaan sehari-hari. Dampingi anak membaca petunjuk; utamakan memahami alasan, bukan cepat selesai.',
			2=>'Lanjutkan dasar berhitung dan membaca melalui cerita pendek, benda di sekitar, serta kegiatan sehari-hari. Ulangi materi yang masih terasa sulit.',
			3=>'Hubungkan keterampilan membaca dan berhitung dengan pengamatan alam serta masyarakat. IPAS mulai tersedia pada kelas ini.',
			4=>'Perdalam penalaran melalui soal cerita, informasi dalam bacaan, dan hubungan antarkonsep. Coba jelaskan jawaban dengan kata-katamu sendiri.',
			5=>'Gunakan materi untuk membandingkan informasi, menyusun alasan, dan menyelesaikan masalah bertahap. Baca pembahasan untuk memeriksa langkah berpikirmu.',
			6=>'Tinjau kembali konsep dan hubungkan beberapa langkah penyelesaian. Rangkaian ini membantu belajar, bukan bocoran atau prediksi soal ujian sekolah.'
		);
		return $intro[$grade] ?? '';
	}
	public function courses($grade = NULL, $subject = NULL)
	{
		if ($this->catalog === NULL)
		{
			$q = $this->db->query("SELECT c.id,c.title,c.slug,c.summary,c.source_note,c.rights_status,c.updated_at,c.published_at,g.code,cat.slug category_slug
				FROM st_courses c JOIN st_learning_grades g ON g.id=c.grade_id JOIN st_categories cat ON cat.id=c.category_id
				WHERE c.status='published' AND c.is_free=1 AND c.rights_status IN ('owned','licensed','public_domain')
				AND g.code IN ('sd_1','sd_2','sd_3','sd_4','sd_5','sd_6') AND cat.status='active'
				AND EXISTS(SELECT 1 FROM st_course_sections s JOIN st_lessons l ON l.section_id=s.id WHERE s.course_id=c.id AND l.status='published' AND l.rights_status IN ('owned','licensed','public_domain') AND CHAR_LENGTH(TRIM(l.body_text))>=300)
				ORDER BY g.sort_order,cat.sort_order,c.id LIMIT 2000");
			if ($q === FALSE) { throw new RuntimeException('Public catalog unavailable.'); }
			$this->catalog = array();
			$subjects=$this->subjects();
			foreach ($q->result_array() as $row)
			{
				foreach ($subjects as $key=>$definition)
				{
					if ($definition['category'] !== $row['category_slug']) { continue; }
					$row['grade']=(int)substr($row['code'],3); $row['subject']=$key; $row['subject_label']=$definition['label'];
					$row['public_path']='latihan-soal/kelas-'.$row['grade'].'/'.$key;
					$this->catalog[]=$row; break;
				}
			}
		}
		return array_values(array_filter($this->catalog,static function($c) use($grade,$subject) { return ($grade===NULL || $c['grade']===$grade) && ($subject===NULL || $c['subject']===$subject); }));
	}
	public function context($course_id)
	{
		foreach ($this->courses() as $course) { if ((int)$course['id']===(int)$course_id) { return $course; } }
		return NULL;
	}
	public function lessons($course_ids)
	{
		if (!$course_ids) { return array(); }
		$allowed=array_map('intval',array_column($this->courses(),'id'));
		$ids=array_values(array_intersect($allowed,array_map('intval',$course_ids)));
		if (!$ids) { return array(); }
		$q=$this->db->select('l.id,l.title,l.slug,l.updated_at,l.source_note,l.rights_status,s.course_id,s.id AS section_id,s.title AS section_title')
			->from('st_lessons l')->join('st_course_sections s','s.id=l.section_id')
			->where_in('s.course_id',$ids)->where('l.status','published')
			->where_in('l.rights_status',array('owned','licensed','public_domain'))
			->where('CHAR_LENGTH(TRIM(l.body_text))>=',300,FALSE)
			->order_by('s.course_id,s.sort_order,s.id,l.sort_order,l.id')->get();
		if ($q===FALSE) { throw new RuntimeException('Public lessons unavailable.'); }
		return $q->result_array();
	}
	public function samples($course_id)
	{
		if (!$this->context($course_id)) { return array(); }
		$q=$this->db->query("SELECT id,prompt,explanation FROM st_questions WHERE course_id=? AND status='published' AND visibility='shared' AND target_child_user_id IS NULL AND reviewed_by_user_id IS NOT NULL AND question_type='single_choice' AND rights_status IN ('owned','licensed','public_domain') AND CHAR_LENGTH(TRIM(explanation))>0 ORDER BY id LIMIT 12",array((int)$course_id));
		if ($q===FALSE) { throw new RuntimeException('Public examples unavailable.'); }
		$rows=$q->result_array(); if (!$rows) { return array(); }
		$choices=$this->db->where_in('question_id',array_column($rows,'id'))->order_by('sort_order,id')->get('st_question_options');
		if ($choices===FALSE) { throw new RuntimeException('Public choices unavailable.'); }
		$options=array(); foreach($choices->result_array() as $choice) { $options[$choice['question_id']][]=$choice; }
		$result=array();
		foreach($rows as $row)
		{
			$items=$options[$row['id']]??array();
			if(count($items)<2 || count($items)>6 || array_sum(array_column($items,'is_correct'))!==1) { continue; }
			$row['options']=$items; $result[]=$row; if(count($result)===3) { break; }
		}
		return $result;
	}
	public function breadcrumbs($context = NULL, $lesson = NULL)
	{
		$items=array(array('label'=>'Beranda','path'=>''),array('label'=>'Latihan soal','path'=>'latihan-soal'));
		if($context) {
			$items[]=array('label'=>'Kelas '.$context['grade'].' SD','path'=>'latihan-soal/kelas-'.$context['grade']);
			$items[]=array('label'=>$context['subject_label'],'path'=>$context['public_path']);
			$items[]=array('label'=>$context['title'],'path'=>'kursus/'.$context['slug']);
		}
		if($lesson) { $items[]=array('label'=>$lesson['title'],'path'=>'materi/'.$lesson['slug']); }
		return $items;
	}
}
