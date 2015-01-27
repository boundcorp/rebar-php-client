<?php
include('app/site.config.php');

$r = new rebar();

if (!empty($_POST)) {
    if (@$_POST['lead_id'] == '_new')
        unset($r->visitor->lead);

    $r->visitor->cart->brand_id = $config['rebar']['brand_id'];

    // Shipping Info
    $r->visitor->cart->first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->address_one = filter_var($_POST['address_one'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->address_two = filter_var($_POST['address_two'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->city = filter_var($_POST['city'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->region = filter_var($_POST['region'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->zipcode = filter_var($_POST['zipcode'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->country = filter_var($_POST['country'], FILTER_SANITIZE_STRING);

    // Contact Info
    $r->visitor->cart->cell = filter_var($_POST['cell'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Optional
    $r->visitor->cart->gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
    $r->visitor->cart->birthdate = filter_var($_POST['birthdate'], FILTER_SANITIZE_STRING);

    $r->rebar_update_cart_data();
    $r->debug_redirect('billing_data.php');
    exit();
}
$redirect = true;

?>
<div class="address-form cf shipping_data content">
    <div class="block-center cf">
        <div class="left contact-form">
            <h2> Where would you like it shipped? </h2> <br/>
            <?php
            $jqueryReady = $r->rebar_output_shipping_data_form('shipping_data.php');
            ?>
        </div>
        <div class="right cf shipping_data_right">
            <?php require_once('show_cart_summary.php'); ?>
        </div>
    </div>
</div>
