<?php
include('app/site.config.php');

$r = new rebar();

$cart = $r->rebar_get_cart_info($_GET['cart_id']);


if (!empty($cart->items)) {
    $itemsHtml = '';
    $totalItems = $n = 0;
    $cartItems = array();
    foreach ($cart->items as $i => $val) {
        $n = $n + 1;
        $cartItems[] = $val->id;
        $subTotal = $val->price_in_cents / $val->quantity / 100;
        $subTotal = "$" . number_format($subTotal, 2);
				if($val->item_type == 'product')
						$itemsHtml .= <<<HTML
            <tr>
                <td valign="middle"><img src="//rebarsecure.com/crm/public/assets/products/{$val->id}.png" style="max-height: 60px; max-width: 60px;" /></td>
                <td valign="middle"><strong>{$val->title}</strong></td>
                <td valign="middle">
                    <input type="text" size="2" name="{$val->item_type}_{$val->id}" value="{$val->quantity}" />
                    <a href="./update_cart_quantities.php?{$val->item_type}_id={$val->id}" class="update">Update</a>
                    <a href="./remove_from_cart.php?{$val->item_type}_id={$val->id}" class="remove">Remove</a>
                </td>
                <td valign="middle" class="price">{$subTotal}</td>
                <td valign="middle" class="price">{$val->price_fmt}</td>
            </tr>

HTML;
				elseif($val->item_type == 'subscription')
						$itemsHtml .= <<<HTML
            <tr>
                <td valign="middle"><img src="//rebarsecure.com/crm/public/assets/products/{$val->id}.png" style="max-height: 60px; max-width: 60px;" /></td>
                <td valign="middle"><strong><b>Subscription to</b> {$val->title}</strong><br />{$val->extra->terms}</td>
                <td valign="middle">
                    <a href="./remove_from_cart.php?{$val->item_type}_id={$val->id}" class="remove">Remove</a>
                </td>
                <td valign="middle" class="price">{$subTotal}</td>
                <td valign="middle" class="price">{$val->extra->price_phrase}</td>
            </tr>

HTML;
    }
}
?>
<center>
    <div style="background-color:#5d4f46; padding:20px;">
        <h1> THANK YOU! </h1>
       
        
    </div>
