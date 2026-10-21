<?php
// Content-only fixtures: no database connection or production writes.
require __DIR__.'/test-practice-unsaved-view.php';
$content=json_decode(file_get_contents(dirname(__DIR__).'/docs/data/pembahasan_kmsi_2025_d_084.json'),TRUE,512,JSON_THROW_ON_ERROR);
practice_unsaved_assert(count($content['questions'])===30,'Thirty source questions');
$ready=0;
foreach($content['questions'] as $i=>$item){
    practice_unsaved_assert($item['number']===$i+1&&$item['id']===$i+729,'Stable source numbering and bank IDs');
    practice_unsaved_assert(mb_strlen($item['explanation'])<=5000&&mb_check_encoding($item['explanation'],'UTF-8'),'Content fits bank validation');
    practice_unsaved_assert($item['ready']===($i!==7),'Only ambiguous question8 held');
    if(!$item['ready'])continue;
    $ready++;
    $fixture=$base;
    $fixture['questions'][0]['explanation']=$item['explanation'];
    $fixture['attempt']['status']='in_progress';
    practice_unsaved_assert(strpos(practice_unsaved_render($fixture),nl2br(html_escape($item['explanation'])))===FALSE,'Do not expose explanations during active attempt');
    $fixture['attempt']['status']='graded';
    practice_unsaved_assert(strpos(practice_unsaved_render($fixture),nl2br(html_escape($item['explanation'])))!==FALSE,'Render full explanation and line breaks after grading');
}
practice_unsaved_assert($ready===29,'Exactly29 approved explanations');
practice_unsaved_assert(3*2+2*9===24&&2+2*9===20,'Q6 figure equations');
practice_unsaved_assert(3+6===9&&2*3+6===12,'Q7 weights');
practice_unsaved_assert(substr_count(implode('',range(11,39)),'3')===13,'Q16 thirteen digit occurrences');
practice_unsaved_assert(27+37===64&&2+7===9,'Q17 column addition');
practice_unsaved_assert(155-89===66&&5+9===14,'Q18 column subtraction');
echo "PASS084:29 explanations/58 active+graded renders, stable IDs, UTF-8/length, figure arithmetic and one held ambiguity; no database writes.\n";
