<div class="container pt-3"><a class="btn btn-outline-secondary" href="<?php echo site_url('kelola/donasi').'?environment='.html_escape($record['environment']); ?>">Kembali ke laporan donasi</a></div>
<?php $this->load->view('donations/order', array('record' => $record)); ?>
