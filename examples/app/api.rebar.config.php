<?php
    // CRM: Rebar
    $config['rebar']['site_domain'] = $_SERVER['HTTP_HOST'];
    $config['rebar']['site_host'] = '//' . $config['rebar']['site_domain'];
    $config['rebar']['environment'] = 'Put Your Public API Key Here';
    $config['rebar']['secret'] = 'Put You Secret API Key Here';
    $config['rebar']['assets'] = '//rebarsecure.com/crm/public/assets';
    $config['rebar']['endpoint'] = 'https://rebarsecure.com/';
    $config['rebar']['ratchet'] = 'https://ratchet.rebarsecure.com/v1/public/';
    $config['rebar']['call_max'] = 20;
    $config['rebar']['brand_id'] = 'Put Your Brand ID Here';
    $config['rebar']['debug'] = false; //Debug variable to show output

    // Create a common place for the products array.
    $config['rebar']['products'] = array(
        array('title' => 'Demo Item', 'id' => '730e94c81d2629222aceac3fb40225b045617505', 'price' => '$5.00', 'item_type' => 'product', 'item_url' => 'demo-item')
        
    );