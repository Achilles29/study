<?php
if (PHP_SAPI !== 'cli' || getenv('DB_DATABASE') !== 'study_qa_monitor_sso039') { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate');
ob_start();
require dirname(__DIR__).'/index.php';
ob_end_clean();
$ci =& get_instance();
$ci->load->model('Review_model');
$db = $ci->db;
$db->db_debug = FALSE;

function check088($condition, $label)
{
	if ( ! $condition) { throw new RuntimeException($label); }
}

$db->trans_begin();
try
{
	$real = $db->where('is_demo', 0)->where('user_id IS NOT NULL', NULL, FALSE)->order_by('id', 'DESC')->get('st_reviews')->row_array();
	check088((bool)$real, 'QA fixture must include one account-linked review');
	$db->where('is_demo', 0)->update('st_reviews', array('status' => 'hidden'));
	$db->where('id', $real['id'])->update('st_reviews', array('status' => 'published'));
	$db->insert('st_reviews', array(
		'user_id' => NULL,
		'demo_key' => NULL,
		'is_demo' => 0,
		'display_name' => 'Legacy tanpa akun',
		'rating' => 5,
		'body' => 'Baris lama tanpa bukti akun tidak boleh menjadi testimoni publik.',
		'status' => 'published',
		'revision' => 1,
		'created_at' => gmdate('Y-m-d H:i:s'),
		'updated_at' => gmdate('Y-m-d H:i:s')
	));
	$unlinked_id = (int)$db->insert_id();

	$landing = $ci->Review_model->landing_sample(3);
	$landing_ids = array_map('intval', array_column($landing['rows'], 'id'));
	check088(count($landing['rows']) === 2, 'Landing must include published non-demo reviews with or without a user ID');
	check088(in_array((int)$real['id'], $landing_ids, TRUE) && in_array($unlinked_id, $landing_ids, TRUE), 'Landing must include both linked and unlinked non-demo reviews');
	check088((int)$landing['summary']['total'] === 2, 'Landing summary must count every published non-demo review');
	$public = $ci->Review_model->listing('published', 1);
	$public_ids = array_map('intval', array_column($public['rows'], 'id'));
	check088((int)$public['total'] === 2 && in_array((int)$real['id'], $public_ids, TRUE) && in_array($unlinked_id, $public_ids, TRUE), 'Public listing must use the same eligibility rule');
}
finally
{
	$db->trans_rollback();
}

echo "PASS 088: landing random sample and public summary exclude demo rows while allowing published non-demo reviews without a user ID.\n";
