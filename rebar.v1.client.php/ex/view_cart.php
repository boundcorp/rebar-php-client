
<?
require_once('header.php');
$obj = rebar_get_cart_info();

if($_rebar_cart_id && !empty($obj))
{
		$totalItems = $n = 0;
		foreach($obj->products as $i => $val)
		{
				$n = $n + 1;
				echo "<span style=\"font-size:25px\"><b>$n )</b>"  . $val->title . "&nbsp$" . $val->price_in_cents/100 ."</span>" . "<br /><br />";
		}
		$totalItems = $n;
		foreach($obj->subscriptions as $i => $val)
		{
				$n = $n + 1;
				echo "<span style=\"font-size:25px\"><b>$n )</b>"  . $val->title . "&nbsp$" . $val->price_in_cents/100 ."</span>" . "<br /><br />";

		}
	  echo "<br /><b>Total: ".$obj->price_info_formatted->final_price."</b>";
}
else
{
?>
<b>You don't have a cart yet!
(cart id: <?=$_rebar_cart_id?>)
</b>
<?
}
?>
<br /><a href="shipping_data.php">GO TO CHECKOUT</a>
<?
require_once('footer.php');