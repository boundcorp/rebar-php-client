<?php
ob_start();

if ($r->visitor->cart->cart_id && !empty($r->visitor->cart->items)) {
    $itemsHtml = '';
    $totalItems = $n = 0;
    $cartItems = array();

    foreach ($r->visitor->cart->items as $i => $val) {
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
                    <a href="update_cart_quantities.php?{$val->item_type}_id={$val->id}" class="update">Update</a>
                    <a href="remove_from_cart.php?{$val->item_type}_id={$val->id}" class="remove">Remove</a>
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
                    <a href="remove_from_cart.php?{$val->item_type}_id={$val->id}" class="remove">Remove</a>
                </td>
                <td valign="middle" class="price">{$subTotal}</td>
                <td valign="middle" class="price">{$val->extra->price_phrase}</td>
            </tr>

HTML;
    }
    ?>
    <form action="update_cart_quantities.php" method="POST" id="order">
        <table style="width: 100%" border="1" id="showCartItems">
            <tr>
                <th colspan="2">Items</th>
                <th>Quantity</th>
                <th class="price">Price</th>
                <th class="price">Total</th>
            </tr>
<?php echo $itemsHtml ?>
        </table>
    </form>
    <div class="orderTotals">
        <div class="right">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td class="price"><?php echo $r->visitor->cart->price_info_formatted->pretax_price ?></td>
                </tr>
                <tr>
                    <td>Shipping:</td>
                    
                </tr>
<?php
    if($_rebar_cart->price_info_formatted->tax_price !== '$0.00'){
?>
                <tr>
                    <td>Tax:</td>
                    <td class="price"><?php echo $r->visitor->cart->price_info_formatted->tax_price ?></td>
                </tr>
<?php
    }
?>
<?php
    if($_rebar_cart->price_info_formatted->coupon_discount !== '$0.00'){
?>
                <tr>
                    <td>Discount:</td>
                    <td class="price"><?php echo $r->visitor->cart->price_info_formatted->coupon_discount ?></td>
                </tr>
<?php
    }
?>
                <tr>
                    <th>Total:</th>
                    <th class="price"><?php echo $r->visitor->cart->price_info_formatted->final_price ?></th>
                </tr>
            </table>
            <br />
            <a href="shipping_data.php" class="checkout">CHECKOUT</a>
        </div>
        
    </div>
    <?php
} else {
    ?>
    <b>You don't have anything in your cart yet!</b>
    <?php
}
ob_end_flush();
?>
<br />