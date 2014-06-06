<?
$REBAR_DEBUG = 1;
$rebar_api_url = 'http://rebarsecure.com/';
$rebar_client_domain = $_SERVER['HTTP_HOST'];
require_once('blank-conf.php');
if(isset($REBAR_CONF)) require_once($REBAR_CONF);
if($REBAR_DEBUG) ini_set('display_errors', 1);

function rebar_cart_size() {
		return (int)rebar_get_cached_val('cart_count') + (int)rebar_get_cached_val ('subscription_count');
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
		global $_rebar_lead_id, $_rebar_lead;
		$_rebar_lead_id = rebar_get_cached_val('lead_id');
		if(empty($_rebar_lead_id)) {
				$_rebar_lead =rebar_post("crm/create_lead");
				$_rebar_lead_id = $_rebar_lead->lead_id;
				rebar_set_cached_val('lead_id', $_rebar_lead_id, 86400*365);
		}
}
function rebar_update_lead_data($fields) {
		rebar_ensure_lead_exists_or_make_one();
		global $_rebar_lead_id;
		$lead = rebar_post('crm/create_lead', array(
				'lead_id' => $_rebar_lead_id,
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
		$_rebar_lead_id = $lead->lead_id;
		rebar_set_cached_val('lead_id', $_rebar_lead_id, 86400*365);
}

function rebar_get_cart_info() {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/cart_info', array());
		$obj = rebar_update_cart_cookies_from_obj($obj);
		global $_rebar_cart;
		$_rebar_cart = $obj;
		return $obj;
}

function rebar_update_cart_cookies_from_obj($obj) {
		if(isset($obj->products)) rebar_set_cached_val('cart_count', count($obj->products));
		if(isset($obj->subscription_products)) rebar_set_cached_val('subscription_count', count($obj->subscription_products));
		if(isset($obj->error) && ($obj->error == 'No such cart' ||
				$obj->error == 'Invalid cart_id')
			 	|| @$obj->payment_success) {
				$obj =rebar_post("crm/create_cart");
				global $_rebar_cart_id, $_rebar_cart, $_rebar_lead_id, $_rebar_lead;
				$_rebar_cart = $obj;
				$_rebar_lead = $obj->lead;
				$_rebar_cart_id = $obj->cart_id;
				$_rebar_lead_id = $obj->lead_id;
				rebar_set_cached_val('cart_id', $_rebar_cart_id);
				rebar_set_cached_val('lead_id', $_rebar_lead_id);
				return $obj;
		}
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

function rebar_add_to_cart($product_id, $first_attempt=true) {
		rebar_ensure_cart_exists_or_make_one();
		$obj = rebar_post('crm/add_to_cart', array(
				'product_id' => $product_id
		));
		if(!rebar_update_cart_cookies_from_obj($obj) && $first_attempt) rebar_add_to_cart($product_id, false);
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
		global $rebar_api_url;
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

function rebar_purchase_cart($cart_id) {
		//OLD WAY OF DOING IT
		//$url = $REBAR.'/crm/purchase_cart';
		//$create_cart_response = rebar_post($url, array(
		//'cart_id' => $cart_id,
		//'token' => $token,
		//'environment' => $brand_id,
		//'secret' => $brand_secret));

		//NEW WAY
		$obj = rebar_post('crm/purchase_cart', array('cart_id' => $cart_id));
		return $obj;
}


function rebar_output_shipping_data_form($action='') {
	global $REBAR_DEBUG, $rebar_environment;
	?>


	<script src="jquery.validation.js"></script>
		<form id="validatedLeadForm" class="leadform" action="<?=$action?>" method="post">
	                      <input type=text class="name" name="first_name" value="First Name"><br /><br />

	                       <input type=text class="name" name="last_name" value="Last Name"><br /><br />

	                       <input type="email" class="name" name="email" value="email"><br /><br />


	                      <input type=text class="name" name="address_one" value="address_one"><br /><br />

	                      <input type=text class="name" name="address_two" value="address_two"><br /><br />

	                      <input type=text class="name" name="city" value="City"><br /><br />

	                       <input type=text class="name" name="region" value="region"><br /><br />

	                       <input type=text class="name" name="country" value="USA"><br /><br />



					<input type="image" src="theme/images/continue.png" width="470" >

	                    </form>
	                    <script>
							$("#validatedLeadForm").validate();
						</script>
	                    <?
	                }

function rebar_output_billing_data_form($action='') {
	global $REBAR_DEBUG, $rebar_environment;
	?>
			<h2> Provide Your Credit Card Details Below </h2> <br/>
			<? if($REBAR_DEBUG == 1) { ?>
			TEST BILLING DETAILS:<br />
			<a href="#" class="rebar_cc_debug" id="authnet_good_hot_test">Auth.Net GOOD HOT TEST</a>
			<br />
			<a href="#" class="rebar_cc_debug" id="authnet_good_faux_test">Auth.Net GOOD faux TEST</a>
			<br />
			<a href="#" class="rebar_cc_debug" id="stripe_good_faux_test">STRIPE BLT GOOD faux TEST</a>
			<br />

			<script>
			var billing_test_details = {
					'authnet_good_hot_test': {
						'first_name': 'Leeward',
						'last_name': 'Bound',
						'email': 'l@lwb.co',
						'number': '371538646043000',
						'month': '9', 'year': '2017', 'cvv': '4506'
					},
					'authnet_good_faux_test': {
						'first_name': 'REBAR',
						'last_name': 'POUND',
						'email': 'lee@rebarsecure.com',
						'number': '378282246310005',
						'month': '8', 'year': '2017', 'cvv': '999'
					},
					'stripe_good_faux_test':	{
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
			$(function() {$
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
			<? } ?>
					 <form action='//rebarsecure.com/v1/public/payment_methods' class="form-horizontal salespage_form ratchet"  method="post" class="ratchet-vault">
								 <!-- Brand Environment (usually hidden): --><input name="environment" class="name" value="<?=$rebar_environment?>" type="hidden">
								 <div style="color:red" class="billing_error" id="billing_error_first_name"></div>
								 <input type=text name="first_name" class="name" value="<?=@$_rebar_cart->lead->first_name?>"><br /><br />
								 <div style="color:red" class="billing_error" id="billing_error_last_name"></div>
								 <input type=text name="last_name" class="name" value="<?=@$_rebar_cart->lead->last_name?>"><br /><br />
								 <div style="color:red" class="billing_error" id="billing_error_email"></div>
								 <input type=text name="email" class="name" value="<?=@$_rebar_cart->lead->email?>"><br /><br />
								 <div style="color:red" class="billing_error" id="billing_error_number"></div>
								 <input type=text class="name" name="number" value="<?=@$_rebar_cart->lead->number?>"><br /><br />
								 <div style="color:red" class="billing_error" id="billing_error_month"></div>
								 <input type=text class="name" name="month" value="<?=@$_rebar_cart->lead->month?>"><br /><br />
								 <div style="color:red" class="billing_error" id="billing_error_year"></div>
								 <input type=text name="year" class="name" value="<?=@$_rebar_cart->lead->year?>"> <br /><br />
								 <div style="color:red" class="billing_error" id="billing_error_cvv"></div>
								 <input type=text name="cvv" class="name" value="<?=@$_rebar_cart->lead->cvv?>"> <br /><br />
								 <input type="image" width="460" src="theme/images/purchase.png" id="vaultthis" name="vault" value="BUY NOW">
				 </form>
 <div id="checkoutform" style="display:none">
    <form id="hiddencheckout" action="<?=$action?>" method="post">
       <input type="text" id="insert_token" name="token"></input>
       Authorizing...
    </form>
 </div>
<script type="text/javascript" src="./jquery.js"></script>
<script>

// Use this to vault ratchet details
$(function() {
  $('form.ratchet').each(function() {
    f = $(this);
    $('.billing_error', f).hide();
    f.submit(function(e) {
      e.preventDefault();
      // Debug only
      f = $(e.target)
      $.ajax(
        {
          type: 'post',
          url: '//rebarsecure.com/v1/public/payment_methods',
          data: f.serialize(),
          success: function(data) {
            js = $.parseJSON(data);
            if(js['token'])
            {
                $('form.ratchet').hide('fast');
                $('#loading').show();
                $('#insert_token').val(js['token'])
				$('#hiddencheckout').show();
                $('#hiddencheckout').submit();
            }
            if(js['errors']) {
            	$('.billing_error', f).hide();
            	$.each(js['errors'], function(k, v) {
            		$('#billing_error_'+k, f).html(v).show()
            	})
            }
          }
        });
      return false;
    });
  });
});
</script>
<?
}