<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Configuration only: provisioning and ownership of this private folder are operator tasks.
$lesson_media_root = getenv('STUDY_MEDIA_ROOT');
$config['root'] = is_string($lesson_media_root) && $lesson_media_root !== '' ? $lesson_media_root : (ENVIRONMENT === 'development' ? dirname(rtrim(FCPATH, '/\\'), 2).'/study-private/lesson-images' : '');
