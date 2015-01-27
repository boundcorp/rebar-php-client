<?php
include('app/site.config.php');

$r = new rebar();
$ci = new coreInterface();

if (!empty($_POST) && isset($_POST['token'])) {
    $result = $r->rebar_autofinalize_cart(@$_POST['token']);
    // $result = $r->rebar_purchase_cart(@$_POST['token']);
    if($result){
        $r->debug_redirect('thankyou.php?cart_id=' . $_POST['cart_id']);
    }
}

$ci->form->cards = 'centered';

$redirect = true;

?>
<div class="address-form cf shipping_data content">
    <div class="block-center cf">
        <div class="left contact-form">
            <?php
            $jqueryReady = $r->rebar_output_billing_data_form('billing_data.php');
            ?>
            <div class="top cf"></div>
        </div>
        <div class="right cf">
            <?php require_once('show_cart_summary.php'); ?>
        </div>
    </div>
</div>

<?php

