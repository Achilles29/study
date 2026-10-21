<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Visual fixtures only: no database connection, account creation or persistence. */
class Review_preview
{
 public function listing($page)
 {
  $first=array('Aditya','Dewi','Rina','Budi','Ayu','Fajar','Indah','Dimas','Nadia','Rizky','Putri','Agus','Wulan','Sari','Bayu','Intan','Arif','Rani','Hendra','Laras');
  $last=array('Pratama','Lestari','Saputra','Wibowo','Permata','Nugraha','Kusuma','Santoso','Utami','Wijaya','Pertiwi','Hidayat','Anggraini','Ramadhan','Maharani','Setiawan','Cahyani','Maulana','Puspita','Wulandari');
  $open=array('Belajarnya jadi lebih santai.','Lumayan membantu buat belajar di rumah.','Anakku suka kegiatan singkat begini.','Tampilannya enak dilihat.','Awalnya cuma iseng coba.','Suka, bisa belajar bareng sore-sore.','Materinya gampang diikuti.','Good job, semoga makin lengkap ya.','Pas buat mengulang pelajaran.','Baru coba beberapa kali, sejauh ini nyaman.');
  $middle=array('Kami biasanya pilih satu materi dulu, habis itu ngobrol tentang contohnya.','Yang penting buat saya anak nggak merasa dikejar-kejar.','Kadang perlu saya jelaskan ulang, tapi itu jadi waktu ngobrol juga.','Kalau belum paham ya dicoba lagi pelan-pelan.','Senang bisa memilih kelas lain waktu mau mengulang.','Masih ada bagian yang perlu ditambah, tapi idenya sudah bagus.','Dipakai sebentar setelah makan malam, nggak harus lama-lama.','Semoga penjelasan dan contoh soalnya terus ditambah.','Bukan pengganti guru, tapi cukup membantu menemani belajar.','Anak saya paling semangat kalau ada kegiatan bermainnya.');
  $end=array('Makasih ya!','Keep it up.','Semangat terus timnya.','Oke banget buat mulai.','Pelan-pelan yang penting rutin.','Bakal dicoba lagi besok.','Sukses selalu.','Worth trying.','Mantap, lanjutkan.','Semoga makin berkembang.');
  $rows=array();
  for($i=0;$i<1000;$i++){
   $rows[]=array('id'=>$i+1,'is_demo'=>1,'display_name'=>$first[$i%20].' '.$last[intdiv($i,20)%20],
    'rating'=>$i%10===0?4:5,'body'=>$open[$i%10].($i%4===0?'':' '.$middle[intdiv($i,10)%10]).($i%3===0?'':' '.$end[intdiv($i,100)%10]),
    'created_at'=>gmdate('Y-m-d H:i:s',1735689600+(($i*31337)%31536000)));
  }
  usort($rows,static function($a,$b){return strcmp($b['created_at'],$a['created_at']);});
  $page=min(50,max(1,(int)$page));
  return array('rows'=>array_slice($rows,($page-1)*20,20),'page'=>$page,'total'=>1000,'summary'=>array('total'=>1000,'average'=>4.9));
 }
}
