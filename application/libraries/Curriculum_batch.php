<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Trusted, versioned content batches only. Never loads a path supplied by a request. */
class Curriculum_batch
{
 public function load($batch)
 {
  $files = array('050'=>array('math_sd1','english_sd1'),'051'=>array('indonesia_sd1','pancasila_sd1'),'052'=>array('pjok_sd1','seni_sd1'),'053'=>array('math_sd2','indonesia_sd2','english_sd2'),'054'=>array('pancasila_sd2','pjok_sd2','seni_sd2'),'055'=>array('math_sd3','indonesia_sd3','english_sd3'),'056'=>array('ipas_sd3','pancasila_sd3','pjok_sd3','seni_sd3'),'057'=>array('math_sd4','indonesia_sd4','english_sd4'),'058'=>array('ipas_sd4','pancasila_sd4','pjok_sd4','seni_sd4'),'059'=>array('math_sd5','indonesia_sd5','english_sd5'));
  $files['060']=array('ipas_sd5','pancasila_sd5','pjok_sd5','seni_sd5');
  $files['061']=array('math_sd6','indonesia_sd6','english_sd6');
  $files['062']=array('ipas_sd6','pancasila_sd6','pjok_sd6','seni_sd6');
  if (!isset($files[$batch])) { throw new InvalidArgumentException('Unknown curriculum batch.'); }
  $courses = array();
  foreach ($files[$batch] as $file)
  {
   $path = APPPATH.'content_batches/'.$batch.'/'.$file.'.php';
   $course = require $path;
   $course['source_sha256'] = hash_file('sha256', $path);
   if (isset($course['builder_version'])) {
    if ($course['builder_version'] !== 1) { throw new InvalidArgumentException('Unknown lesson formatter.'); }
    $course['source_sha256'] = hash('sha256', $course['source_sha256'].hash_file('sha256', APPPATH.'content_batches/lesson_builder_v1.php'));
   }
   $courses[] = $course;
  }
  $this->validate($courses);
  return $courses;
 }

 private function text($value, $min, $max)
 {
  return is_string($value) && mb_check_encoding($value, 'UTF-8')
   && mb_strlen(trim($value), 'UTF-8') >= $min && mb_strlen($value, 'UTF-8') <= $max
   && ! preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value);
 }

 public function validate($courses)
 {
  if (!is_array($courses) || !$courses) { throw new InvalidArgumentException('Empty batch.'); }
  $slugs = $keys = array();
  foreach ($courses as $course)
  {
   foreach (array('title'=>180,'summary'=>2000,'objectives'=>5000,'prerequisites'=>5000,'source'=>1000) as $key=>$max)
   {
    if (!$this->text($course[$key] ?? NULL, 10, $max)) { throw new InvalidArgumentException('Invalid course '.$key); }
   }
   if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $course['slug'] ?? '') || strlen($course['slug'])>170
    || !preg_match('/\A[a-z0-9-]{5,80}\z/', $course['key'] ?? '')
    || !preg_match('/\Asd_[1-6]\z/', $course['grade'] ?? '')
    || !in_array($course['category'] ?? '', array('matematika','bahasa-inggris','bahasa-indonesia','ppkn','penjaskes','seni-budaya','ipas'), TRUE)
    || isset($slugs[$course['slug']]) || isset($keys[$course['key']])
    || !isset($course['sections']) || !is_array($course['sections']) || !$course['sections'])
   { throw new InvalidArgumentException('Invalid/duplicate course identity.'); }
   $slugs[$course['slug']] = $keys[$course['key']] = TRUE;
   $question_count = $lesson_count = 0;
   $titles = $prompts = array();
   foreach ($course['sections'] as $section)
   {
    if (!$this->text($section['title'] ?? NULL, 5, 180) || empty($section['lessons']) || !is_array($section['lessons']))
    { throw new InvalidArgumentException('Invalid/empty chapter.'); }
    foreach ($section['lessons'] as $lesson)
    {
     if (!$this->text($lesson['title'] ?? NULL, 5, 180) || !$this->text($lesson['body'] ?? NULL, 1000, 30000)
      || !is_int($lesson['minutes'] ?? NULL) || $lesson['minutes']<5 || $lesson['minutes']>60
      || isset($titles[$lesson['title']]) || count($lesson['cards'] ?? array())<2 || count($lesson['questions'] ?? array())<2)
     { throw new InvalidArgumentException('Invalid/incomplete lesson.'); }
     $titles[$lesson['title']] = TRUE; $lesson_count++;
     foreach (array('## Tujuan belajar','## Coba sendiri','## Cek dan ceritakan','## Untuk pendamping','## Aku sudah bisa') as $heading)
     { if (strpos($lesson['body'], $heading)===FALSE) { throw new InvalidArgumentException('Missing teaching section.'); } }
     foreach ($lesson['cards'] as $card)
     {
      if (count($card)!==3 || !$this->text($card[0], 3, 1000) || !$this->text($card[1], 3, 3000) || !$this->text($card[2], 3, 1000))
      { throw new InvalidArgumentException('Invalid card.'); }
     }
     foreach ($lesson['questions'] as $q)
     {
      if (count($q)!==4 || !$this->text($q[0], 10, 3000) || !$this->text($q[3], 15, 3000)
       || !is_array($q[1]) || array_keys($q[1])!==array(0,1,2,3) || !is_int($q[2]) || $q[2]<0 || $q[2]>3
       || isset($prompts[$q[0]])) { throw new InvalidArgumentException('Invalid/duplicate question.'); }
      $options = array();
      foreach ($q[1] as $option)
      {
       if (!$this->text($option,1,1000)) { throw new InvalidArgumentException('Invalid option.'); }
       $options[] = mb_strtolower(trim($option),'UTF-8');
      }
      if (count(array_unique($options))!==4) { throw new InvalidArgumentException('Duplicate answer option.'); }
      $prompts[$q[0]]=TRUE; $question_count++;
     }
    }
   }
   if ($lesson_count>50 || $question_count>50) { throw new InvalidArgumentException('Course exceeds editor limits.'); }
  }
  return TRUE;
 }
}
