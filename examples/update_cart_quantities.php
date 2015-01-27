<?php
include('app/site.config.php');

$r = new rebar();

if (isset($_POST))
    $r->rebar_update_cart_quantities(@$_POST);
$r->debug_redirect('view_cart.php');