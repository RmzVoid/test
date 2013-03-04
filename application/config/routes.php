<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$route['default_controller'] = 'imagecache';
$route['(:any)/(:any).jpg'] = 'imagecache/$1/$2';

?>