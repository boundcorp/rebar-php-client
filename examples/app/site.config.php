<?php
    // Lets start the session
    session_start();

    // Hide Notices

    error_reporting(E_ALL ^ E_NOTICE);

    // CloudFlare $_SERVER vars

    if(!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) $_SERVER['HTTPS'] = ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')?true:false;

    // ROOT PATHS

    $config['root_domain'] = $_SERVER['HTTP_HOST'];

    // BASE PATHS

    $config['base_path'] = 'insert your base path here';
    $config['base_url'] = 'insert your base url here';

    $config['base_includes'] = 'includes';
    $config['base_classes'] = $config['base_includes'] . '/classes';
    $config['base_config'] = $config['base_includes'] . '/config';
    $config['base_views'] = $config['base_includes'] . '/views';

    $config['http_domain'] = 'http://' . $config['root_domain'];
    $config['https_domain'] = 'https://' . $config['root_domain'];
    $config['base_prefix'] = ($_SERVER['HTTPS'])?'https://':'http://';
    $config['base_domain'] = $config['base_prefix'] . $config['root_domain'];
    $config['base_images'] = $config['base_domain'] . '/images';
    $config['base_css'] = $config['base_domain'] . '/css';
    $config['base_js'] = $config['base_domain'] . '/js';

    // COOKIES

    $config['cookie_path'] = "/";
    $config['cookie_domain'] = "." . $config['root_domain']; //Set the cookie domain
    $config['cookie_expiry'] = strtotime("+90 day"); //Set the cookie expiration

    // Site Title
    $config['product_name'] = 'Insert Your Main Product Name Here';
    $config['copyright_name'] = 'Insert Your Copyright Name Here';
    $config['site_title'] = 'Insert Site Title here';

    // Emailing options
    $config['return_path'] = 'support@' . $config['root_domain']; // Required for some mail servers (refused without)
    $config['error_to'] = 'notices@inteliclic.com'; // Who should I send error to? You can comma separate ofcourse. user@domain.com, user2@domain.com
    $config['error_from'] = 'error@' . $config['root_domain']; // Who should the error come from?

    // Company Information
    $config['support_email'] = 'Insert Support E-mail Here';
    $config['support_phone'] = 'Insert Your Support Phone Number Here';
    $config['address_inline'] = 'Insert Your Inline Address Here';
    $config['address_formatted'] = 'Insert Your Formatted Address HEre';
    $config['address_returns'] = 'Insert Your Return Address Here';

    // Google Analytics Code
    $config['google_analytics_code'] = 'Insert Your Google Analytics Account Number Here'; // UA-XXXXX-X | Blank for none

    // Currency
    $config['currency'] = '$'; // $ | £ | ¥ | ¢ | €

    // Accepted Cards
    $config['accepted_cards'] = array('visa','mastercard','discover'); // Which CC's Do You Accept?

    // Countries
    $config['countries'] = array('US' => 'United States'); // Global countries for entire site

    // Shipping Delay
    $config['ship']['us'] = 3;

    // Form Fields
    $config['index_fields_include'] = array('idLog','campaign_id','product_id','product_qty','shipping_id','AFFID','AFID','SID','C1','C2','C3','AID','OPT','click_id');
    $config['payment_fields'] = array('shipToFirstName','shipToLastName','shipToAddress1','shipToCity','shipToState','shipToPostalCode','shipToCountry','shipToPhone','email');
    $config['payment_fields_include'] = array('idLog','prospect_id','campaign_id','product_id','product_qty','shipping_id','upsell_ids','AFID','AFFID','AID','SID','C1','C2','C3','OPT','click_id','idPerson','shippingId','sourceCode','products');
    $config['payment_fields_exclude'] = array('creditCardNumber','creditCardExpirationMonth','creditCardExpirationYear','creditCardExpirationDate','creditCardVerificationNumber','creditCardType');
    $config['tokens'] = array('order_id','AFID','AFFID','SID','AID','C1','C2','C3','OPT','click_id');

    // Include Files
    include_once('api.rebar.config.php');
    include_once('api.rebar.class.php');
    include_once('core.interface.php');
    include_once('email.interface.php');
