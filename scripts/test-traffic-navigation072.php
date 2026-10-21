<?php
define('BASEPATH',__DIR__);require dirname(__DIR__).'/application/libraries/Traffic_navigation.php';$m=new Traffic_navigation();$n=0;
function t072($a,$b){global $n;if($a!==$b){throw new RuntimeException('Navigation test failed #'.($n+1));}$n++;}
foreach(array('/','/kursus','/kursus/matematika-kelas-1','/materi/123','/belajar/arena/pilih/math-3','/belajar/battle/umum') as $path){t072($m->safe_path($path.'?token=SECRET#hidden'),$path);}
foreach(array('//evil.test','https://evil.test/','/akun/pesanan/1','/auth/google/callback','/keluarga/anak/42','/belajar/arena/main/13','/%2e%2e/secret','/kursus/<script>','/bantuan/secret',array('x')) as $bad){t072($m->safe_path($bad),NULL);}
t072($m->safe_path('/index.php/kursus'),'/kursus');
$base='https://www.projectpintar.com/';
t072($m->referrer(NULL,$base)['referrer_kind'],'direct_unknown');
t072($m->referrer('https://www.projectpintar.com/kursus?token=SECRET',$base),array('referrer_kind'=>'internal','referrer_host'=>NULL,'referrer_path'=>'/kursus'));
t072($m->referrer('https://www.projectpintar.com/akun/pesanan/123?secret=x',$base)['referrer_path'],NULL);
t072($m->referrer('https://www.google.com/search?q=PRIVATE',$base),array('referrer_kind'=>'search','referrer_host'=>'www.google.com','referrer_path'=>NULL));
t072($m->referrer('https://www.google.com.evil.example/search?q=PRIVATE',$base)['referrer_kind'],'external');
foreach(array('javascript:alert(1)','https://user:pass@evil.example/path',"https://evil.test/\r\nInjected: yes",array('x'),'https://bad\\host/x') as $bad){t072($m->referrer($bad,$base)['referrer_kind'],'unknown');}
t072($m->referrer('https://outside.example/private-child/42?key=SECRET',$base),array('referrer_kind'=>'external','referrer_host'=>'outside.example','referrer_path'=>NULL));
t072($m->referrer_label(NULL),'Belum direkam (data lama)');
echo "PASS $n navigation checks: allowlisted paths, legacy routes, no tokens/private IDs/external paths, safe referring host/categories, search spoof boundaries, unknown historical data.\n";
