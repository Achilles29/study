<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Salin file ini sebagai database.local.php untuk pengembangan lokal.
 * Jangan menaruh kredensial produksi di sini atau memasukkan file lokal ke Git.
 */
return array(
	'hostname' => '127.0.0.1',
	'username' => 'study_app',
	'password' => '',
	'database' => 'db_study'
);
