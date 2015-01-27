<?php
include('app/site.config.php');

$r = new rebar();

$product_id = @$_GET['product_id'];
$subscription_product_id = @$_GET['subscription_product_id'];

if (isset($product_id))
    $r->rebar_add_to_cart($product_id);
if (isset($subscription_product_id))
    $r->rebar_add_subscription_to_cart($subscription_product_id);

if ($r->visitor->cart->payment_ready)
    $r->debug_redirect('thankyou.php?order_id=' . $new_cart->cart_id);
else
    $r->debug_redirect('view_cart.php');