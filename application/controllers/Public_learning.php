<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Public_learning extends MY_Controller
{
	public function index($grade = NULL, $subject = NULL)
	{
		$this->require_http_methods(array('GET','HEAD'));
		$this->load->model('Public_learning_model');
		if($grade!==NULL && !preg_match('/\A[1-6]\z/',(string)$grade)) { show_404(); return; }
		$grade=$grade===NULL?NULL:(int)$grade;
		$subjects=$this->Public_learning_model->subjects();
		if($subject!==NULL && (!isset($subjects[$subject]) || $grade===NULL)) { show_404(); return; }
		try {
			$courses=$this->Public_learning_model->courses($grade,$subject);
			if(!$courses) { show_404(); return; }
			$path='latihan-soal'.($grade!==NULL?'/kelas-'.$grade:'').($subject!==NULL?'/'.$subject:'');
			if(trim($this->uri->uri_string(),'/')!==$path) { redirect($path,'location',301); return; }
			$title=$subject!==NULL?'Latihan Soal '.$subjects[$subject]['label'].' Kelas '.$grade.' SD dan Pembahasan':($grade!==NULL?'Latihan Soal SD Kelas '.$grade.' dan Pembahasan':'Latihan Soal Online SD Kelas 1–6');
			$intro=$subject!==NULL?$subjects[$subject]['intro']:$this->Public_learning_model->grade_intro($grade);
			if($grade===NULL) { $intro='Pilih kelas dan mata pelajaran, baca contoh soal beserta pembahasan, lalu lanjutkan latihan kursus. Materi berasal dari kursus gratis yang sudah diterbitkan; hasil latihan dapat disimpan setelah masuk akun.'; }
			$breadcrumbs=array(array('label'=>'Beranda','path'=>''),array('label'=>'Latihan soal','path'=>'latihan-soal'));
			if($grade!==NULL) { $breadcrumbs[]=array('label'=>'Kelas '.$grade.' SD','path'=>'latihan-soal/kelas-'.$grade); }
			if($subject!==NULL) { $breadcrumbs[]=array('label'=>$subjects[$subject]['label'],'path'=>$path); }
			$lessons=$subject!==NULL?$this->Public_learning_model->lessons(array_column($courses,'id')):array();
			$samples=array();
			if($subject!==NULL) { foreach($courses as $course) { $samples[$course['id']]=$this->Public_learning_model->samples($course['id']); } }
			$this->render_page('public_learning/index',compact('grade','subject','subjects','courses','intro','path','breadcrumbs','lessons','samples')+array('page_title'=>$title));
		} catch(RuntimeException $error) { show_error('Halaman belajar belum dapat dimuat. Coba kembali.',503); }
	}
}
