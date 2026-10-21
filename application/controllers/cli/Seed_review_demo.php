<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Seed_review_demo extends CI_Controller
{
 public function index()
 {
  if(PHP_SAPI!=='cli' || ENVIRONMENT!=='development' || getenv('STUDY_REVIEW_DEMO')!=='066'){show_404();exit(1);}
  $this->load->database();$this->db->db_debug=FALSE;if($this->db->database!=='study_qa_monitor_sso039'){throw new RuntimeException('Demo seed only in isolated QA; never production.');}
  $first=array('Aditya','Dewi','Rina','Budi','Ayu','Fajar','Indah','Dimas','Nadia','Rizky','Putri','Agus','Wulan','Sari','Bayu','Intan','Arif','Rani','Hendra','Laras');$last=array('Pratama','Lestari','Saputra','Wibowo','Permata','Nugraha','Kusuma','Santoso','Utami','Wijaya','Pertiwi','Hidayat','Anggraini','Ramadhan','Maharani','Setiawan','Cahyani','Maulana','Puspita','Wulandari');
  $open=array('Belajarnya jadi lebih santai.','Lumayan membantu buat belajar di rumah.','Anakku suka kegiatan singkat begini.','Tampilannya enak dilihat.','Awalnya cuma iseng coba.','Suka, bisa belajar bareng sore-sore.','Materinya gampang diikuti.','Good job, semoga makin lengkap ya.','Pas buat mengulang pelajaran.','Baru coba beberapa kali, sejauh ini nyaman.');
  $middle=array('Kami biasanya pilih satu materi dulu, habis itu ngobrol tentang contohnya.','Yang penting buat saya anak nggak merasa dikejar-kejar.','Kadang perlu saya jelaskan ulang, tapi itu jadi waktu ngobrol juga.','Kalau belum paham ya dicoba lagi pelan-pelan.','Senang bisa memilih kelas lain waktu mau mengulang.','Masih ada bagian yang perlu ditambah, tapi idenya sudah bagus.','Dipakai sebentar setelah makan malam, nggak harus lama-lama.','Semoga penjelasan dan contoh soalnya terus ditambah.','Bukan pengganti guru, tapi cukup membantu menemani belajar.','Anak saya paling semangat kalau ada kegiatan bermainnya.');
  $end=array('Makasih ya!','Keep it up.','Semangat terus timnya.','Oke banget buat mulai.','Pelan-pelan yang penting rutin.','Bakal dicoba lagi besok.','Sukses selalu.','Worth trying.','Mantap, lanjutkan.','Semoga makin berkembang.');
  $this->load->library('study_transaction');$result=$this->study_transaction->run($this->db,function()use($first,$last,$open,$middle,$end){$n=0;
   for($i=0;$i<1000;$i++){$key='demo066-'.str_pad((string)$i,4,'0',STR_PAD_LEFT);if($this->db->where('demo_key',$key)->count_all_results('st_reviews')){continue;}$when=gmdate('Y-m-d H:i:s',strtotime('2025-01-01 UTC')+(($i*31337)%31536000));$body=$open[$i%10].($i%4===0?'':' '.$middle[intdiv($i,10)%10]).($i%3===0?'':' '.$end[intdiv($i,100)%10]);
    $this->db->insert('st_reviews',array('user_id'=>NULL,'demo_key'=>$key,'is_demo'=>1,'display_name'=>$first[$i%20].' '.$last[intdiv($i,20)%20],'rating'=>$i%10===0?4:5,'body'=>$body,'status'=>'published','created_at'=>$when,'updated_at'=>$when));$n++;}
   return array('ok'=>TRUE,'simulation_rows'=>$n,'public_reviews_added'=>0);});echo json_encode($result).PHP_EOL;if(!$result['ok']){exit(1);}
 }
}
