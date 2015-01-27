<?php
$REBAR_DEBUG = 0;
$REBAR_BRAND = '';
$REBAR_PRODUCT = '';
$rebar_api_url = 'http://rebarsecure.com/';
$rebar_client_domain = $_SERVER['HTTP_HOST'];
require_once('blank-conf.php');
if(isset($REBAR_CONF)) require_once($REBAR_CONF);
if($REBAR_DEBUG) ini_set('display_errors', 1);
$GLOBAL_REBAR_CALL_MAX = 20;
$GLOBAL_REBAR_CALL_COUNT = 0;

function rebar_cart_size() {
    global $_rebar_cart;
    $size = 0;
    if ($_rebar_cart->items > 0) {
        foreach ($_rebar_cart->items as $item) {
            $size = $size + $item->quantity;
        }
    }
    return (int) $size;
}

function rebar_get_cached_val($name) {
		$var = "_rebar_${name}";
		global $$var;
		if(empty($$var))
		{
				$$var = @$_COOKIE['rebar_'.$name];
				debug_print("<br />Reading $var from cookies: ".$$var."<br /><hr />");
		}
		return $$var;
}

function rebar_set_cached_val($name, $val, $ttl=86400) {
		global $rebar_client_domain;
		debug_print("<br />Setting COOKIE: $name = $val (ttl: $ttl, path: /, domain: .$rebar_client_domain)<br /><hr />");
		if(!empty($val)) setcookie("rebar_${name}", $val, time()+$ttl, '/', '.'.$rebar_client_domain);
}


function rebar_track_visitor() {
		global $_rebar_visitor_id, $_rebar_visitor, $REBAR_BRAND, $REBAR_PRODUCT;
		if(!empty($_rebar_visitor_id)) return $_rebar_visitor_id;
		$_rebar_visitor_id = rebar_get_cached_val('visitor_id');
		$pass_headers = array('HTTP_HOST', 'PATH_INFO', 'HTTP_USER_AGENT', 'REMOTE_ADDR',
				'HTTP_REFERER', 'HTTP_X_FORWARDED_FOR', 'QUERY_STRING', 'REQUEST_URI');
		$pass_gets = array_keys($_GET); $info=array();
		$id = empty($_rebar_visitor_id) ? '' : $_rebar_visitor_id;
		foreach ($pass_headers as $h) {
				if (isset($_SERVER[$h])) {
								$info[$h] = $_SERVER[$h];
						} else {
								$info[$h] = '';
						}
		}
		$info['brand_id'] = $REBAR_BRAND;
		$info['product_id'] = $REBAR_PRODUCT;
		$i=base64_encode(json_encode($info));

		$_rebar_visitor =rebar_post("crm/track_visit?_id=$id&i=$i");
		$_rebar_visitor_id = $_rebar_visitor->visitor_id;
		global $_rebar_lead_id, $_rebar_lead;
		$_rebar_lead = $_rebar_visitor->lead;
		$_rebar_lead_id = @$_rebar_visitor->lead->lead_id;
		return rebar_set_cached_val('visitor_id', $_rebar_visitor_id, 30*86400);
}

function rebar_ensure_cart_exists_or_make_one() {
		global $_rebar_cart_id, $_rebar_cart, $_rebar_lead_id;
		rebar_ensure_lead_exists_or_make_one();
		$_rebar_cart_id = rebar_get_cached_val('cart_id');
		if(empty($_rebar_cart_id)) {
				$_rebar_cart =rebar_post("crm/create_cart", array('lead_id' => $_rebar_lead_id));
				$_rebar_cart_id = $_rebar_cart->cart_id;
				rebar_set_cached_val('cart_id', $_rebar_cart_id, 86400);
		}
}

function rebar_ensure_lead_exists_or_make_one() {
		rebar_track_visitor();
		global $_rebar_lead_id, $_rebar_lead, $_rebar_visitor_id, $REBAR_BRAND;
		if(empty($_rebar_lead_id)) {
				$_rebar_lead_id = rebar_get_cached_val('lead_id');
		}
		if(empty($_rebar_lead_id)) {
				$_rebar_lead =rebar_post("crm/create_lead", array('visitor_id'=> $_rebar_visitor_id, 'brand_id' => $REBAR_BRAND));
				$_rebar_lead_id = $_rebar_lead->lead_id;
				rebar_set_cached_val('lead_id', $_rebar_lead_id, 86400*365);
		}
}
function rebar_update_lead_data($fields) {
		rebar_ensure_lead_exists_or_make_one();
		global $_rebar_lead_id, $_rebar_visitor_id, $REBAR_BRAND, $REBAR_PRODUCT;
		$lead = rebar_post('crm/create_lead', array(
				'lead_id' => @$fields['lead_id'],
				'brand_id' => $REBAR_BRAND,
				'product_id' => $REBAR_PRODUCT,
				'visitor_id' => $_rebar_visitor_id,
				'first_name' => @$fields['first_name'],
				'last_name' => @$fields['last_name'],
				'email' => @$fields['email'],
				'address_one' => @$fields['address_one'],
				'address_two' => @$fields['address_two'],
				'city' => @$fields['city'],
				'region' => @$fields['region'],
				'country' => @$fields['country'],
				'zipcode' => @$fields['zipcode'],
		));
		global $_rebar_lead_id;
		$_rebar_lead_id = $lead->lead_id;
		rebar_set_cached_val('lead_id', $_rebar_lead_id, 86400*365);
}

function rebar_get_cart_info($cart_id='') {
		if(empty($cart_id))
		{
				global $_rebar_cart, $_rebar_cart_id;
				if(!empty($_rebar_cart)) return $_rebar_cart;
				$obj = rebar_post('crm/cart_info', array());
				$_rebar_cart = $obj;
		} else {
				$obj = rebar_post('crm/cart_info', array('cart_id' => $cart_id));
		}
		rebar_update_cart_cookies_from_obj($obj);
		return $obj;
}


function _rebar_cart_is_locked($obj) {
		return (isset($obj->error) && ($obj->error == 'No such cart' ||
				$obj->error == 'Invalid cart_id')
			 	|| @$obj->payment_success);
}
function rebar_update_cart_cookies_from_obj($obj) {
		if(isset($obj->items)) rebar_set_cached_val('cart_count', count($obj->items));
		if(_rebar_cart_is_locked($obj)) {
				global $_rebar_cart_id, $_rebar_cart, $_rebar_lead_id, $_rebar_lead;
				$obj =rebar_post("crm/create_cart", array('lead_id' => $_rebar_lead_id));
				$_rebar_cart = $obj;
				$_rebar_lead = $obj->lead;
				$_rebar_cart_id = $obj->cart_id;
				$_rebar_lead_id = $obj->lead_id;
				rebar_set_cached_val('cart_id', $_rebar_cart_id);
				rebar_set_cached_val('lead_id', $_rebar_lead_id);
				return false;
		}
		$_rebar_cart = $obj;
		$_rebar_lead = $obj->lead;
		$_rebar_cart_id = $obj->cart_id;
		$_rebar_lead_id = $obj->lead_id;
		rebar_set_cached_val('cart_id', $_rebar_cart_id);
		rebar_set_cached_val('lead_id', $_rebar_lead_id);
		return $obj;
}

function rebar_authorize_cart($token) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/purchase_cart', array(
				'token' => $token,
				'authorize' => 'true'
		));
		rebar_update_cart_cookies_from_obj($obj);
		return $obj;
}
function rebar_authorize_dollar($token) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/purchase_cart', array(
				'token' => $token,
				'authorize' => 'true',
				'amount' => 100
		));
		rebar_update_cart_cookies_from_obj($obj);
		return $obj;
}
function rebar_autofinalize_cart($token) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/purchase_cart', array(
				'token' => $token,
				'autofinalize' => 'true'
		));
		rebar_update_cart_cookies_from_obj($obj);
		return $obj;
}

function rebar_remove_from_cart($product_id) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/remove_from_cart', array(
				'product_id' => $product_id
		));
}

function rebar_update_cart_quantities($quantities) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/update_cart_quantities', $quantities);
}

function rebar_add_to_cart($product_id, $first_attempt=true) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/add_to_cart', array(
				'product_id' => $product_id
		));

		if(_rebar_cart_is_locked($obj) && $first_attempt)
		{
				rebar_update_cart_cookies_from_obj($obj);
				$obj = rebar_add_to_cart($product_id, false);
		}
				rebar_update_cart_cookies_from_obj($obj);
		return $obj;
}

function rebar_add_subscription_to_cart($product_id) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/add_to_cart', array(
				'subscription_product_id' => $product_id
		));
		if(!rebar_update_cart_cookies_from_obj($obj)) rebar_add_subscription_to_cart($product_id);
		return $obj;
}

function rebar_post($stub, $fields=array()) {
		global $rebar_api_url, $GLOBAL_REBAR_CALL_COUNT, $GLOBAL_REBAR_CALL_MAX;
		$GLOBAL_REBAR_CALL_COUNT = $GLOBAL_REBAR_CALL_COUNT + 1;
		if($GLOBAL_REBAR_CALL_COUNT > $GLOBAL_REBAR_CALL_MAX) return object();
		$response = post_data($rebar_api_url . $stub, rebar_params($fields));
		$obj = @json_decode($response);
		return $obj;
}


function rebar_params($extra_params) {
		global $rebar_secret, $rebar_environment;
		return array_merge(array(
				'cart_id' => rebar_get_cached_val('cart_id'),
				'environment' => $rebar_environment,
				'secret' => $rebar_secret
		), $extra_params);
}


function debug_print($str) {
		global $REBAR_DEBUG;
		if($REBAR_DEBUG) echo $str;
}

function debug_redirect($url) {
		global $REBAR_DEBUG;
		if(! $REBAR_DEBUG) {
				header('Location: ' . $url);
		} else {
				debug_print('<a href="'.$url.'">HTTP Header Location: '.$url.'</a>');
		}
}

function post_data($url, $fields) {
		$fields_string = '';

		//url-ify the data for the POST
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
				rtrim($fields_string, '&');
		debug_print('POSTING TO API: ' . $url);
		debug_print('<br />');
		debug_print($fields_string);
		debug_print('<br />');
		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);


		//execute post
		$result = curl_exec($ch);
		debug_print('Got result:<br />'.$result.'<hr />');
		//close connection
		curl_close($ch);
		return $result;
}

function rebar_purchase_cart($token) {
		global $_rebar_cart;
		rebar_ensure_cart_exists_or_make_one();
		$_rebar_cart = rebar_post('crm/purchase_cart', array(
				'token' => $token
		));
		rebar_update_cart_cookies_from_obj($_rebar_cart);
		return $_rebar_cart;
}

function rebar_output_shipping_data_form($action = '') {
    global $REBAR_DEBUG, $rebar_environment, $_rebar_lead;
    rebar_print_js_include();
    ?>
    <form id="validatedLeadForm" class="pure-form" action="<?= $action ?>" method="post">
        <?php
        if (!empty($_rebar_lead)) {
            ?>
            <input type="radio" class="new_or_old_lead" name="lead_id" id="new_lead_radio" value="_new"><label for="new_lead_radio">New shipping details</label>
            - or -
            <input type="radio" checked class="new_or_old_lead" name="lead_id" id="old_lead_radio" value="<?= $_rebar_lead->lead_id ?>"><label for="old_lead_radio">Re-use these details below...</label>
            <br /><br />
            <script>
                $(function() {
                    f = $('#validatedLeadForm');
                    $('input.new_or_old_lead[type=radio]', f).change(function() {
                        if ($(this).val() == '_new') {
                            $('input.name', f).removeAttr('disabled');
                        }
                        else {
                            $('input.name', f).attr('disabled', 'disabled');
                        }
                    })
                })

            </script>
            <?php
        }
        ?>
        <input type=text class="name new_lead" name="first_name" required value="<?= @$_rebar_lead->first_name ?>" placeholder="First Name"><br /><br />
        <input type=text class="name new_lead" name="last_name" required value="<?= @$_rebar_lead->last_name ?>" placeholder="Last Name"><br /><br />
        <input type="email" class="email new_lead" name="email" required value="<?= @$_rebar_lead->email ?>" placeholder="Email"><br /><br />
        <input type="tel" class="phone new_lead" name="phone" required value="<?= @$_rebar_lead->phone ?>" placeholder="Phone"><br /><br />
        <input type=text class="name new_lead" name="address_one" required value="<?= @$_rebar_lead->address_one ?>" placeholder="Address Line 1"><br /><br />
        <input type=text class="name new_lead" name="address_two" value="<?= @$_rebar_lead->address_two ?>" placeholder="Address Line 2"><br /><br />
        <input type=text class="name new_lead" name="city" required value="<?= @$_rebar_lead->city ?>" placeholder="City"><br /><br />
        <input type=text class="name new_lead" name="region" value="<?= @$_rebar_lead->region ?>" placeholder="State or Region"><br /><br />
        <input type=text class="name new_lead" name="country" required value="<?= @$_rebar_lead->country ?>" placeholder="Country"><br /><br />
        <input type="image" width="460" src="theme/images/continue.png" class="btn btn-success btn-lg btn-block btn-continue" value="Next" >
    </form>
    <script>
        $(function() {
            $("#validatedLeadForm").validate();

        })
    </script>
    <?php
}

function rebar_output_billing_data_form($action = '') {
    global $REBAR_DEBUG, $rebar_environment, $_rebar_cart;
?>
    <h2> Provide Your Credit Card Details Below </h2> <br/>
<?php
    if ($REBAR_DEBUG == 1) {
?>
        TEST BILLING DETAILS:<br />
        <a href="#" class="rebar_cc_debug" id="authnet_good_hot_test">Auth.Net GOOD HOT TEST</a>
        <br />
        <a href="#" class="rebar_cc_debug" id="authnet_good_faux_test">Auth.Net GOOD faux TEST</a>
        <br />
        <a href="#" class="rebar_cc_debug" id="stripe_good_faux_test">STRIPE BLT GOOD faux TEST</a>
        <br />

        <script>
            var billing_test_details = {
                'authnet_good_faux_test': {
                    'first_name': 'REBAR',
                    'last_name': 'POUND',
                    'email': 'lee@rebarsecure.com',
                    'number': '378282246310005',
                    'month': '8', 'year': '2017', 'cvv': '999'
                },
                'stripe_good_faux_test': {
                    'first_name': 'Leeroy',
                    'last_name': 'Bound',
                    'email': 'lee@lwb.co',
                    'environment': 'test',
                    'month': 8,
                    'year': 2017,
                    'number': '378282246310005',
                    'cvv': '123'
                }
            };
            $(function() {
                $
                $('.rebar_cc_debug').click(function() {
                    var details = billing_test_details[$(this).attr('id')]
                    $('.ratchet input[name=first_name]').val(details['first_name']);
                    $('.ratchet input[name=last_name]').val(details['last_name']);
                    $('.ratchet input[name=email]').val(details['email']);
                    $('.ratchet input[name=number]').val(details['number']);
                    $('.ratchet input[name=month]').val(details['month']);
                    $('.ratchet input[name=year]').val(details['year']);
                    $('.ratchet input[name=cvv]').val(details['cvv']);


                })
            });
        </script>
    <?php } ?>
    <?php
    if (!empty($_rebar_cart->errors)) {
        ?>
        <ul class="errorlist">
<?php
        foreach ($_rebar_cart->errors as $i => $e) {
            echo "<li>" . $e . "</li>";
        }
?>
        </ul>
<?php
    }
    ?>

    <form action='https://ratchet.rebarsecure.com/v1/public/payment_methods' class="form-horizontal salespage_form ratchet"  method="post" >
        <!-- Brand Environment (usually hidden): --><input name="environment" class="name" value="<?= $rebar_environment ?>" type="hidden">
        <div style="color:red" class="billing_error" id="billing_error_first_name"></div>
        <input type=text placeholder='First Name' name="first_name" class="name form-control" value="<?= @$_rebar_cart->lead->first_name ?>"><br /><br />
        <div style="color:red" class="billing_error" id="billing_error_last_name"></div>
        <input type=text placeholder='Last Name' name="last_name" class="name form-control" value="<?= @$_rebar_cart->lead->last_name ?>"><br /><br />
        <div style="color:red" class="billing_error" id="billing_error_email"></div>
        <input type=text placeholder='Email' name="email" class="email form-control" value="<?= @$_rebar_cart->lead->email ?>"><br /><br />
        <div style="color:red" class="billing_error" id="billing_error_number"></div>
        <input type=text placeholder='Card Number' class="name form-control" name="number" value="<?= @$_rebar_cart->lead->number ?>"><br /><br />
        <div style="color:red" class="billing_error" id="billing_error_month"></div>
        <input type=text placeholder='Expiry Month' class="name form-control" name="month" value="<?= @$_rebar_cart->lead->month ?>"><br /><br />
        <div style="color:red" class="billing_error" id="billing_error_year"></div>
        <input type=text placeholder='Expiry Year' name="year" class="name form-control" value="<?= @$_rebar_cart->lead->year ?>"> <br /><br />
        <div style="color:red" class="billing_error" id="billing_error_cvv"></div>
        <input type=text placeholder='CVV' name="cvv" class="name form-control" value="<?= @$_rebar_cart->lead->cvv ?>"> <br /><br />
        <input type="image" width="460" src="theme/images/purchase.png" class="btn btn-success btn-lg btn-block btn-purchase" id="vaultthis" name="vault" value="BUY NOW">
    </form>
    <div id="checkoutform" style="display:none">
        <form id="hiddencheckout" action="<?= $action ?>" method="post">
            <input type="text" id="insert_token" name="token"></input>
        </form>
    </div>
    <?php
}

function rebar_print_js_include() {
?>
    <script src='//rebarsecure.com/static/assets/jquery.js' type='text/javascript'></script>
    <script src='//rebarsecure.com/static/assets/countries-2.0-min.js' type='text/javascript'></script>
    <script src="//rebarsecure.com/static/assets/jquery.validation.js" type="text/javascript"></script>
<?php
}