<?php
// Exact-count educational diagrams, drawn from coordinates rather than generative imagery.
function study_math047_diagram($lesson, $path)
{
 $im = imagecreatetruecolor(1200, 720);
 $white = imagecolorallocate($im, 255,255,255); $ink = imagecolorallocate($im,32,40,68); $line = imagecolorallocate($im,157,172,194); $soft = imagecolorallocate($im,243,246,252); $teal = imagecolorallocate($im,11,99,92); $gold = imagecolorallocate($im,255,203,75);
 imagefill($im,0,0,$white); imagefilledrectangle($im,0,0,1199,115,$teal);
 $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'; $bold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
 if ( ! is_file($font) || ! is_file($bold)) { throw new RuntimeException('Diagram font unavailable.'); }
 imagettftext($im,26,0,45,69,$white,$bold,$lesson['title']);
 $d = $lesson['diagram'];
 if (isset($d['sequence']))
 {
  foreach ($d['sequence'] as $i=>$n) { $x=105+($i%5)*210; $y=155+intdiv($i,5)*115; imagefilledrectangle($im,$x,$y,$x+150,$y+85,$soft); imagerectangle($im,$x,$y,$x+150,$y+85,$line); imagettftext($im,35,0,$x+($n<10?59:45),$y+59,$ink,$bold,(string)$n); }
  imagettftext($im,23,0,110,675,$ink,$font,'Baca dari kiri ke kanan, lalu lanjut ke baris berikutnya.');
 }
 else
 {
  $rows=max(2,(int)ceil(max($d['groups'])/5));
  foreach ($d['groups'] as $g=>$n)
  {
   $left=70+$g*590; imagettftext($im,28,0,$left,180,$ink,$bold,$d['labels'][$g]);
   for($i=0;$i<$rows*5;$i++){ $x=$left+($i%5)*86; $y=225+intdiv($i,5)*90; imagefilledrectangle($im,$x,$y,$x+80,$y+80,$soft); imagerectangle($im,$x,$y,$x+80,$y+80,$line); if($i<$n){imagefilledellipse($im,$x+40,$y+40,54,54,$g===0?$teal:$gold);imageellipse($im,$x+40,$y+40,54,54,$ink);} }
   imagettftext($im,25,0,$left,555,$ink,$font,'Jumlah: '.$n);
  }
  imagettftext($im,25,0,70,635,$ink,$bold,$d['footer']??'Satu lingkaran berisi = satu benda.');
  imagettftext($im,19,0,70,685,$ink,$font,'Kotak kosong hanya tempat; tidak dihitung sebagai benda.');
 }
 if ( ! imagepng($im,$path,6)) { throw new RuntimeException('Diagram write failed.'); } imagedestroy($im);
}
