<?php
include('app/site.config.php');

$r = new rebar();

$redirect = true;

?>
<div class="address-form cf view_cart content">
    <div class="block-center cf">
        <div class="left cf">
            <b style="font-size:40px;">YOUR CART:</b><br><br>
            <?php include('show_cart_partial.php'); ?>
            <div class="clear"></div>
            <a href="shipping_data.php">Go To Shipping Data Input </a>
        </div>
    </div>
</div>
<?php
    $jqueryReady = <<<JS
        $('.checkout').click(function(){
            showLoading();
        });

JS;

