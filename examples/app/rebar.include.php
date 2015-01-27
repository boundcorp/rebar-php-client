<?php
$rebar_client_dir = 'app';
$rebar_environment = 'Your Public API Key Here';
$rebar_secret = 'Your Secret API Key Here';
$REBAR_CONF = 'rebar-conf.php';

require_once("$rebar_client_dir/api.php");

$dev_domains = array('Your Dev Domain Here');
$site_host = (in_array($_SERVER['HTTP_HOST'], $dev_domains))?'//' . $_SERVER['HTTP_HOST'] . '/argan':'//argan.com';
$crm_assets = '//rebarsecure.com/crm/public/assets';

$REBAR_DEBUG = 0; //Debug variable to show output
$REBAR_BRAND = 'Insert Your Brand ID Here';

rebar_track_visitor();

// Create a common place for the products array.
$products = array(
    array('title' => 'Demo Item', 'id' => 'Insert Product ID Here', 'price' => '$5.00', 'item_type' => 'product', 'item_url' => 'demo-item'),
    array('title' => 'Demo Item 2', 'id' => 'Insert Proudct ID Here', 'price' => '$9.99', 'item_type' => 'product', 'item_url' => 'demo-item-2')
    
);