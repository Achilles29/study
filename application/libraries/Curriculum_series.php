<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Versioned, original learning guides based on the 2025 national learning outcomes. */
class Curriculum_series
{
	private const GRADES = array('sd_5','sd_6','smp_7','smp_8','smp_9','sma_10','sma_11','sma_12');

	public function load($grade)
	{
		if ( ! in_array($grade, self::GRADES, TRUE)) { throw new InvalidArgumentException('Unknown grade.'); }
		$path = APPPATH.'content_series/2026/'.$grade.'.php';
		if ( ! is_file($path)) { throw new RuntimeException('Grade manuscript is missing.'); }
		$manuscript = require $path;
		if ( ! is_array($manuscript) || ! $manuscript) { throw new RuntimeException('Empty grade manuscript.'); }
		$courses = array();
		foreach ($manuscript as $subject => $definition)
		{
			if ( ! preg_match('/\A[a-z][a-z0-9-]{2,40}\z/', $subject) || ! is_array($definition) || count($definition['chapters'] ?? array()) < 6)
			{ throw new RuntimeException('Incomplete subject: '.$subject); }
			$source_hash = hash('sha256', json_encode($definition,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
			$chapters = array(); $lesson_titles = array();
			foreach ($definition['chapters'] as $index => $row)
			{
				if ( ! is_array($row) || count($row) !== 8) { throw new RuntimeException('Incomplete chapter row.'); }
				foreach ($row as $part => $value) { if ( ! is_string($value) || mb_strlen(trim($value),'UTF-8') < ($part === 0 ? 5 : 15)) { throw new RuntimeException('Chapter text too short.'); } }
				list($title,$concept,$worked,$task,$answer,$application,$application_task,$application_answer) = $row;
				$chapter_title = 'Bab '.($index + 1).'. '.$title;
				$concept_title = 'Memahami '.$title;
				$application_title = 'Menerapkan '.$title;
				foreach (array($concept_title,$application_title) as $lesson_title)
				{
					if (isset($lesson_titles[$lesson_title])) { throw new RuntimeException('Duplicate lesson title.'); }
					$lesson_titles[$lesson_title] = TRUE;
				}
				$body_concept = "## Tujuan belajar\nMempelajari topik: $title. Jelaskan alasan di balik jawabanmu.\n\n## Inti materi\n$concept\n\n## Contoh dibahas\n$worked\n\n## Coba sendiri\n$task\n\n## Pembahasan\n$answer\n\n## Periksa pemahaman\nJelaskan langkahmu dengan kalimatmu sendiri. Bandingkan alasanmu dengan pembahasan, lalu perbaiki bagian yang belum tepat sebelum melanjutkan. Jika menggunakan angka atau data, tuliskan satuan dan sumbernya.\n\n## Catatan belajar\nTuliskan satu hal yang sudah kamu pahami dan satu pertanyaan untuk dibahas bersama guru atau teman.";
				$body_application = "## Tujuan belajar\nMenerapkan topik: $title. Gunakan alasan yang dapat diperiksa.\n\n## Situasi dan tantangan\n$application\n\n## Cara mengerjakan\nBaca situasi sekali lagi. Tandai informasi yang diketahui, apa yang diminta, dan batasan yang perlu diperhatikan. Gunakan gagasan inti dari sesi sebelumnya untuk menyusun jawaban. Tulis langkah atau bukti yang membuat jawabanmu dapat diperiksa orang lain.\n\n## Coba sendiri\n$application_task\n\n## Cek dan ceritakan\n$application_answer\n\n## Refleksi\nCoba ubah satu kondisi dalam situasi tadi. Jelaskan apakah hasilnya tetap sama atau berubah, dan mengapa. Diskusikan jika ada lebih dari satu jawaban yang masuk akal; sebutkan alasan serta sumber informasi yang dipakai.";
				foreach (array($body_concept,$body_application) as $body) { if (mb_strlen($body,'UTF-8') < 600) { throw new RuntimeException('Lesson too short.'); } }
				$chapters[] = array('title'=>$chapter_title,'lessons'=>array(
					array('title'=>$concept_title,'body'=>$body_concept,'minutes'=>20),
					array('title'=>$application_title,'body'=>$body_application,'minutes'=>20)
				));
			}
			$courses[] = array(
				'grade'=>$grade,'category'=>$subject,'title'=>$definition['title'],
				'slug'=>$definition['slug'],'summary'=>$definition['summary'],
				'objectives'=>$definition['objectives'],'prerequisites'=>$definition['prerequisites'],
				'source'=>$definition['source'],'source_sha256'=>$source_hash,
				'chapters'=>$chapters,'source_row'=>$definition['chapters']
			);
		}
		return $courses;
	}
}
