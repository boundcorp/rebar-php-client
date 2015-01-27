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
        $itemsHtml .= <<<HTML
            <div class="item">
                <div class="thumb left"><img src="//rebarsecure.com/crm/public/assets/products/{$val->id}.png" style="max-height: 60px; max-width: 60px;" /></div>
                <div class="content left">
                    <p class="title"><strong>{$val->title}</strong></p>
                    <p class="text">QTY: {$val->quantity}<br />
                    <span class="price">{$val->price_fmt}</span></p>
                </div>
                <div class="clear"></div>
            </div>

HTML;
    }
    ?>
    <div class="orderTotals">
        <h2>CART SUMMARY</h2>
        <table>
            <tr>
                <td>Subtotal:</td>
                <td class="price"><?php echo $r->visitor->cart->price_info_formatted->pretax_price ?></td>
            </tr>
            <tr>
                <td>Shipping:</td>
                <td class="price">FREE</td>
            </tr>
            <?php
            if ($r->visitor->cart->price_info_formatted->tax_price !== '$0.00') {
                ?>
                <tr>
                    <td>Tax:</td>
                    <td class="price"><?php echo $r->visitor->cart->price_info_formatted->tax_price ?></td>
                </tr>
                <?php
            }
            ?>
            <?php
            if ($r->visitor->cart->price_info_formatted->coupon_discount !== '$0.00') {
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
    </div>
<?php
    if(!empty($r->visitor->cart->first_name)){
?>
    <div class="orderInfo">
        <h2>SHIPPING ADDRESS</h2>
        <div class="shipping">
            <p>
                <strong><?php echo $r->visitor->cart->first_name ?> <?php echo $r->visitor->cart->last_name ?></strong><br />
                <?php echo $r->visitor->cart->address_one ?> <?php echo $r->visitor->cart->address_two ?><br />
                <?php echo $r->visitor->cart->city ?>, <?php echo $r->visitor->cart->region ?> <?php echo $r->visitor->cart->zipcode ?><br />
            </p>
        </div>
    </div>
<?php
    }
?>
    <div class="itemSummary">
        <h2>IN YOUR CART (<?php echo $r->rebar_cart_size() ?>)</h2>
        <?php echo $itemsHtml ?>
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