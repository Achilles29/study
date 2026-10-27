<?php
defined('BASEPATH') OR exit('No direct script access allowed');
function english_status($status){return array('pending'=>'Menunggu penilaian','accepted'=>'Memenuhi rubrik','revise'=>'Perlu revisi','running'=>'Belum selesai','completed'=>'Selesai','draft'=>'Draf','published'=>'Tayang','archived'=>'Diarsipkan')[$status]??$status;}
function english_kind($kind){return array('practice'=>'Latihan unit','review'=>'Review berkala','checkpoint'=>'Checkpoint level','speaking'=>'Speaking','writing'=>'Writing','capstone'=>'Proyek akhir')[$kind]??$kind;}
function english_time($utc){return $utc?(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y H:i').' WIB':'—';}
function english_rubric(){return array('Pemenuhan tugas','Akurasi dan rentang bahasa','Organisasi dan keterpautan','Interaksi / revisi','Kejelasan dan integritas makna');}
function english_pager($base,$page,$total,$query=array()){
 $max=max(1,(int)ceil($total/20));$out='<nav class="eh-actions" aria-label="Halaman data"><span>Halaman '.$page.' dari '.$max.' · '.$total.' data</span>';
 foreach(array($page-1=>'← Sebelumnya',$page+1=>'Berikutnya →') as $p=>$label)if($p>=1&&$p<=$max)$out.='<a class="eh-btn" href="'.html_escape(site_url($base).'?'.http_build_query($query+array('halaman'=>$p))).'">'.$label.'</a>';return $out.'</nav>';
}

/** Inspect container/codec signatures; never trust the uploaded name or browser MIME. */
function english_audio_type($path)
{
 $bytes=file_get_contents($path);if(!is_string($bytes)||strlen($bytes)<100)return NULL;
 if(substr($bytes,0,4)==='RIFF'&&substr($bytes,8,4)==='WAVE'&&strpos($bytes,'fmt ')!==FALSE&&strpos($bytes,'data')!==FALSE)return array('mime'=>'audio/wav','extension'=>'wav');
 if(substr($bytes,0,4)==="\x1a\x45\xdf\xa3"&&strpos(substr($bytes,0,4096),'webm')!==FALSE&&(strpos($bytes,'A_OPUS')!==FALSE||strpos($bytes,'A_VORBIS')!==FALSE)&&strpos($bytes,'V_VP')===FALSE&&strpos($bytes,'V_AV')===FALSE)return array('mime'=>'audio/webm','extension'=>'webm');
 if(substr($bytes,0,4)==='OggS'&&(strpos($bytes,'OpusHead')!==FALSE||strpos($bytes,"\x01vorbis")!==FALSE)&&strpos($bytes,'theora')===FALSE)return array('mime'=>'audio/ogg','extension'=>'ogg');
 if(substr($bytes,4,4)==='ftyp'&&(strpos($bytes,'mp4a')!==FALSE||strpos($bytes,'alac')!==FALSE)&&strpos($bytes,'avc1')===FALSE&&strpos($bytes,'hvc1')===FALSE&&strpos($bytes,'vp09')===FALSE)return array('mime'=>'audio/mp4','extension'=>'m4a');
 $offset=0;
 if(substr($bytes,0,3)==='ID3'){$size=0;for($i=6;$i<=9;$i++){$v=ord($bytes[$i]);if($v>127)return NULL;$size=($size<<7)|$v;}$offset=10+$size;if(ord($bytes[5])&0x10)$offset+=10;}
 if(strlen($bytes)>$offset+4){$a=ord($bytes[$offset]);$b=ord($bytes[$offset+1]);$c=ord($bytes[$offset+2]);if($a===255&&($b&224)===224&&($b&24)!==8&&($b&6)!==0&&($c&240)!==0&&($c&240)!==240&&($c&12)!==12)return array('mime'=>'audio/mpeg','extension'=>'mp3');}
 return NULL;
}

/** Only editorial segments are marked up; every text fragment is escaped. */
function english_lesson_blocks($unit)
{
 $out='';$blocks=$unit['lesson_blocks']??array();
 if(!$blocks){foreach($unit['lesson'] as $p)$out.='<p>'.html_escape($p).'</p>';return $out;}
 foreach($blocks as $i=>$block){$out.='<section class="eh-reading-block"><span class="eh-reading-number">'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT).'</span><div><h3>'.html_escape($block['title']).'</h3><p>';
  foreach($block['segments'] as $segment){$text=html_escape($segment['text']);$out.=($segment['kind']??'')==='key'?'<strong class="eh-key">'.$text.'</strong>':(($segment['kind']??'')==='example'?'<span class="eh-inline-example" lang="en">'.$text.'</span>':$text);}
  $out.='</p></div></section>';
 }return $out;
}
function english_status_badge($status){$class=in_array($status,array('accepted','completed','published'),TRUE)?'good':(in_array($status,array('revise','draft','archived'),TRUE)?'attention':'waiting');return '<span class="ec-badge ec-badge-'.$class.'">'.html_escape(english_status($status)).'</span>';}
