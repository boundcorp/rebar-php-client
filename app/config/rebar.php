<?php

$dev_domains = array('lwb.co', 'clients.inteliclic.com');
$config['rebar']['site_host'] = (in_array($_SERVER['HTTP_HOST'], $dev_domains)) ? '//' . $_SERVER['HTTP_HOST'] . '/argan' : '//argan.com';
$config['rebar']['environment'] = '';
$config['rebar']['secret'] = '';
$config['rebar']['assets'] = '//rebarsecure.com/crm/public/assets';
$config['rebar']['endpoint'] = 'https://rebarsecure.com/';
$config['rebar']['call_max'] = 20;

include_once($config['base_classes'] . '/api.rebar.php');

