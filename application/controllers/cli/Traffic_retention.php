<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Traffic_retention extends CI_Controller
{
	public function index($mode = 'preview')
	{
		if (PHP_SAPI !== 'cli') { show_404(); return; }
		$this->load->model('Traffic_model');
		try
		{
			$s = $this->Traffic_model->settings();
			if ( ! in_array($mode, array('preview','scheduled','apply'), TRUE)) { exit(2); }
			$version = $mode === 'scheduled' ? $s['policy_version'] : (string) getenv('STUDY_RETENTION_APPROVED_VERSION');
			$r = $this->Traffic_model->retention($mode !== 'preview', $version, $mode === 'scheduled');
			$this->load->model('Operational_traffic_model');
			if ($r['ok']) { $r['operational'] = $this->Operational_traffic_model->retention($mode !== 'preview'); }
			echo json_encode($r)."\n"; if ( ! $r['ok']) { exit(1); }
		}
		catch (Throwable $error) { fwrite(STDERR, "Traffic retention unavailable; no successful completion claimed.\n"); exit(1); }
	}
}
