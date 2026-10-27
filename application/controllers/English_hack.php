<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read-only editorial preview; never changes a learner's existing course or progress. */
class English_hack extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->require_role(array('system_admin'));
        $this->require_http_methods(array('GET', 'HEAD'));
        $this->output->set_header('Cache-Control: private, no-store')
            ->set_header('X-Robots-Tag: noindex, nofollow');
    }

    public function index()
    {
        $file = APPPATH.'content/english_hack/v1/curriculum.json';
        $curriculum = is_readable($file) ? json_decode(file_get_contents($file), TRUE) : NULL;
        if (!is_array($curriculum) || empty($curriculum['levels'])) {
            show_error('Naskah kurikulum belum tersedia.', 503);
            return;
        }
        $curriculum['downloads'] = array(
            'pdf' => site_url('kelola/kelas-inggris/konsep/unduh/pdf'),
            'book' => site_url('kelola/kelas-inggris/konsep/unduh/buku'),
            'studio' => site_url('kelola/kelas-inggris/konsep/unduh/studio'),
            'json' => site_url('kelola/kelas-inggris/konsep/unduh/data')
        );
        $this->render_page('admin/english_hack/index', array(
            'page_title' => 'English Hack · Konsep Kelas Bahasa Inggris',
            'admin_section' => 'english_course',
            'admin_tab' => 'kurikulum',
            'curriculum' => $curriculum
        ));
    }

    public function download($kind = '')
    {
        $files = array(
            'pdf' => 'english-hack-coursebook-v1.pdf',
            'buku' => 'english-hack-coursebook-v1.html',
            'studio' => 'english-hack-studio-v1.html',
            'data' => 'curriculum.json'
        );
        if (!isset($files[$kind])) { show_404(); return; }
        $file = APPPATH.'content/english_hack/v1/'.$files[$kind];
        if (!is_readable($file)) { show_404(); return; }
        $this->output->set_content_type('application/octet-stream')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Content-Disposition: attachment; filename="'.$files[$kind].'"')
            ->set_output(file_get_contents($file));
    }
}
