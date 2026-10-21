<?php
define('BASEPATH',__DIR__);
require dirname(__DIR__).'/application/libraries/Review_preview.php';
function check087($ok){if(!$ok){throw new RuntimeException('Preview assertion failed');}}
$m=new Review_preview();$all=array();
for($page=1;$page<=50;$page++){
 $r=$m->listing($page);check087($r['page']===$page && count($r['rows'])===20 && $r['total']===1000);
 $all=array_merge($all,$r['rows']);
}
check087(count(array_unique(array_column($all,'id')))===1000);
$counts=array_count_values(array_column($all,'rating'));check087($counts[5]===900 && $counts[4]===100);
check087(array_sum(array_column($all,'rating'))/1000===4.9);
foreach($all as $i=>$r){check087($r['is_demo']===1 && str_starts_with($r['created_at'],'2025-'));if($i){check087($all[$i-1]['created_at']>=$r['created_at']);}}
check087($m->listing(-1)['page']===1 && $m->listing(9999)['page']===50);
check087($m->listing(3)===$m->listing(3));
echo "PASS preview dataset: 1000 unique IDs, 900/100 stars, 4.9 mean, 2025 dates, stable ordering and 50 pages.\n";
