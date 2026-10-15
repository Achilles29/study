<?php
define('BASEPATH', __DIR__);
require __DIR__.'/../application/libraries/Matching_cards.php';
$matcher = new Matching_cards();
$cards = array(array('front_text' => 'Satu', 'back_text' => 'One'), array('front_text' => 'Dua', 'back_text' => 'Two'));
if (count($matcher->eligible($cards)) !== 2)
{
	throw new RuntimeException('Valid cards were not eligible.');
}
$cards[] = array('front_text' => '  SATU ', 'back_text' => 'Another');
$cards[] = array('front_text' => 'Tiga', 'back_text' => ' two ');
$cards[] = array('front_text' => str_repeat('a', 161), 'back_text' => 'Long');
$cards[] = array('front_text' => ' ', 'back_text' => 'Empty');
if ($matcher->eligible($cards) !== array())
{
	throw new RuntimeException('Ambiguous, empty and long cards must be excluded.');
}
$unicode = array(array('front_text' => str_repeat('é', 160), 'back_text' => '<script>text</script>'));
if (count($matcher->eligible($unicode)) !== 1)
{
	throw new RuntimeException('Character lengths must be UTF-8 aware; escaping belongs to rendering.');
}
fwrite(STDOUT, "PASS matching eligibility: short unique pairs, duplicate sides, case/whitespace, empty text, and UTF-8 length.\n");
