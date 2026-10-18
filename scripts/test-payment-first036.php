<?php
$database = getenv('DB_DATABASE');
if (PHP_SAPI !== 'cli' || $database !== 'study_qa_monitor_payment036') { exit(2); }
$_SERVER['argv'] = array('index.php', 'cli/migrate'); ob_start(); require dirname(__DIR__).'/index.php'; ob_end_clean(); $ci =& get_instance();
$ci->load->model('Payment_config_model');
if ($ci->db->count_all('st_payment_configs') !== 0) { throw new RuntimeException('Use empty isolated payment fixture.'); }
$values = array('environment' => 'sandbox', 'merchant_id' => ' QA-FIRST ', 'server_key' => '', 'client_key' => '');
$failed = $ci->Payment_config_model->save($values, '0', 1);
if ($failed['ok'] || ! isset($failed['errors']['server_key']) || $ci->db->count_all('st_payment_configs') !== 0) { throw new RuntimeException('First-save required-key check failed.'); }
$values['server_key'] = " \tSB-Mid-server-QAOnly.With+Safe/Token=2026\r\n";
$saved = $ci->Payment_config_model->save($values, '0', 1);
if ( ! $saved['ok']) { throw new RuntimeException('First save failed.'); }
$row = $ci->db->get_where('st_payment_configs', array('id' => $saved['id']))->row_array();
$ci->load->library('payment_secrets');
$secret = $ci->payment_secrets->open($row['credentials_cipher']);
if ($row['merchant_id'] !== 'QA-FIRST' || $secret['server_key'] !== trim($values['server_key']) || $secret['client_key'] !== '') { throw new RuntimeException('Credential normalization mismatch.'); }
$values['server_key'] = '';
$next = $ci->Payment_config_model->save($values, (string) $saved['id'], 1);
if ( ! $next['ok']) { throw new RuntimeException('Blank rotation failed.'); }
$later = $ci->db->get_where('st_payment_configs', array('id' => $next['id']))->row_array();
if ($ci->payment_secrets->open($later['credentials_cipher']) !== $secret || $ci->db->get_where('st_payment_configs', array('id' => $saved['id']))->row_array() !== $row) { throw new RuntimeException('Secret/immutable previous config changed.'); }
echo "PASS first save: merchant alone rejected with field-specific error, sandbox server key + optional blank client accepted, clipboard whitespace normalized, exact encrypted token preserved, old version immutable.\n";
