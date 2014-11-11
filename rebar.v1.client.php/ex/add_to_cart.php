<?
require_once('rebar.include.php');

$product_id = @$_GET['product_id'];
$subscription_product_id = @$_GET['subscription_product_id'];

if(isset($product_id))
		rebar_add_to_cart($product_id);
if(isset($subscription_product_id))
		rebar_add_subscription_to_cart($subscription_product_id);
debug_redirect('view_cart.php');

?>