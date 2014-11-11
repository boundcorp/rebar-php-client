<?
$REBAR_DEBUG = 0;
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


function rebar_track_visitor() {
		global $_rebar_visitor_id, $_rebar_visitor;
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
		global $_rebar_lead_id, $_rebar_lead, $_rebar_visitor_id;
		if(empty($_rebar_lead_id)) {
				$_rebar_lead_id = rebar_get_cached_val('lead_id');
		}
		if(empty($_rebar_lead_id)) {
				$_rebar_lead =rebar_post("crm/create_lead", array('visitor_id', $_rebar_visitor_id));
				$_rebar_lead_id = $_rebar_lead->lead_id;
				rebar_set_cached_val('lead_id', $_rebar_lead_id, 86400*365);
		}
}
function rebar_update_lead_data($fields) {
		rebar_ensure_lead_exists_or_make_one();
		global $_rebar_lead_id, $_rebar_visitor_id;
		$lead = rebar_post('crm/create_lead', array(
				#'lead_id' => $_rebar_lead_id,
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
				rebar_update_cart_cookies_from_obj($obj);
				$_rebar_cart = $obj;
		} else {
				$obj = rebar_post('crm/cart_info', array('cart_id' => $cart_id));
		}
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
		//if($REBAR_DEBUG) echo $str;
}

function debug_redirect($url) {
		global $REBAR_DEBUG;
		if($REBAR_DEBUG) {
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


function rebar_output_shipping_data_form($action='') {
	global $REBAR_DEBUG, $rebar_environment, $_rebar_lead;
	?>

			<? rebar_print_js_include(); ?>
		<link rel="stylesheet" href="http://yui.yahooapis.com/pure/0.5.0/pure-min.css">

		<form id="validatedLeadForm" class="pure-form" action="<?=$action?>" method="post">
				<?
	if(!empty($_rebar_lead)){
?>
		<input type="radio" class="new_or_old_lead" name="lead_id" id="new_lead_radio" value=""><label for="new_lead_radio">New shipping details</label>
- or -
		<input type="radio" checked class="new_or_old_lead" name="lead_id" id="old_lead_radio" value="<?=$_rebar_lead->lead_id?>"><label for="old_lead_radio">Re-use these details below...</label>
<br />
			<script>
			$(function() {
					f = $('#validatedLeadForm');
					$('input.new_or_old_lead[type=radio]', f).change(function() {
							if($(this).val() == '') {
									$('input.name', f).removeAttr('disabled');
							}
							else {
									$('input.name', f).attr('disabled', 'disabled');
							}
					})
			})

</script>
<?
	}
?>
						  <center>
	                      <input type=text size="55" class="name new_lead" name="first_name" required value="<?=@$_rebar_lead->first_name?>" placeholder="First Name"><br /><br />

	                       <input type=text size="55" class="name new_lead" name="last_name" required value="<?=@$_rebar_lead->last_name?>" placeholder="Last Name"><br /><br />

	                       <input type=text size="55" class="name new_lead" name="email" required value="<?=@$_rebar_lead->email?>" placeholder="email"><br /><br />

	                       


	                      <input type=text size="55" class="name new_lead" name="address_one" required value="<?=@$_rebar_lead->address_one?>" placeholder="address_one"><br /><br />

	                      <input type=text size="55" class="name new_lead" name="address_two" value="<?=@$_rebar_lead->address_two?>" placeholder="Address Line 2"><br /><br />

	                      <input type=text size="55" class="name new_lead" name="city" required value="<?=@$_rebar_lead->city?>" placeholder="City"><br /><br />

	                       <input type=text size="55" class="name new_lead" name="region" value="<?=@$_rebar_lead->region?>" placeholder="State or Region"><br /><br />

	                       <input type=text size="55" class="name new_lead" name="zip" required value="<?=@$_rebar_lead->zip?>" placeholder="zip"><br /><br />

	                       <input type=text size="55" class="name new_lead" name="country" required value="<?=@$_rebar_lead->country?>" placeholder="Country"><br /><br />


	                       <button style="font-size:30px; background-color:#fe8208;" type="submit" class="btn btn-lg">
                  <span class="glyphicon glyphicon-tint"></span> CLAIM YOUR TRIAL NOW
                  </button> </center>

	                    </form>
	                   <!--<script>
	                    $(function() {
	                    	$("#validatedLeadForm").validate();

	                    })
						</script>-->
	                    <?
	                }

function rebar_output_billing_data_form($action='') {
	global $REBAR_DEBUG, $rebar_environment, $_rebar_cart;
	?>		<link rel="stylesheet" href="rebar-client/card/card.css">
	 
			<script src="rebar-client/card/card.js"></script>
			<center><br><br><br><br>
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
<?




			if(!empty($_rebar_cart->errors))
			{

			foreach($_rebar_cart->errors as $i => $e)
				{
					echo "<li>".$e."</li>";
				}

			}

			?>
				<div class="card-wrapper">
					<div class="form-inputs" style="margin-top:200px">
					 <form action='https://ratchet.rebarsecure.com/v1/public/payment_methods' class="form-horizontal salespage_form ratchet"  method="post" >
								 <!-- Brand Environment (usually hidden): --><input name="environment" class="name" value="<?=$rebar_environment?>" type="hidden">
								 <div class="col-xs-6">
								 	
								 	<input type=text placeholder='First Name' name="first_name" class="name form-control" value="<?=@$_rebar_cart->lead->first_name?>">
								 	<div style="color:red" class="billing_error" id="billing_error_first_name"></div>
								 </div>
								 <div class="col-xs-6">
									 
									 <input type=text placeholder='Last Name' name="last_name" class="name form-control" value="<?=@$_rebar_cart->lead->last_name?>">
									 <div style="color:red" class="billing_error" id="billing_error_last_name"></div><br /><br />
								 </div>
								 <!--<div style="color:red" class="billing_error" id="billing_error_email"></div>
								 <input type=text placeholder='Email' name="email" class="name form-control" value="<?=@$_rebar_cart->lead->email?>"><br /><br />-->
								 <div class="col-xs-9">
								 	
									<input type=text class="name form-control" name="number" >
									<div style="color:red" class="billing_error" id="billing_error_number"></div>
									
									</div>

									<div class="col-xs-3">
										<input type=text placeholder='CVV' name="cvv" class="name form-control" value="<?=@$_rebar_cart->lead->cvv?>">
									</div>

								<br /><br />

								<div class="col-xs-9"> 
									
								<br>
								 <!--<input type=text placeholder='Expiry Month' class="name form-control" name="month" value="<?=@$_rebar_cart->lead->month?>"><br /><br />-->
								 <select name="month" id="fields_expmonth" class="form-control">
				                  <option  selected >Month</option>
									<option  value="01">(01) January</option>
									<option  value="02">(02) February</option>
									<option  value="03">(03) March</option>
									<option  value="04">(04) April</option>
									<option  value="05">(05) May</option>
									<option  value="06">(06) June</option>
									<option  value="07">(07) July</option>
									<option  value="08">(08) August</option>
									<option  value="09">(09) September</option>
									<option  value="10">(10) October</option>
									<option  value="11">(11) November</option>
									<option  value="12">(12) December</option>
									</select>  </p>
								</div><div style="color:red" class="billing_error" id="billing_error_month">
									</div>

								<div class="col-xs-3"><br>
								 <div style="color:red" class="billing_error" id="billing_error_year"></div>
								 
								 <!--<input type=text placeholder='Expiry Year' name="year" class="name form-control" value="<?=@$_rebar_cart->lead->year?>"> <br /><br />-->

								 <select name="year" id="fields_expyear" class="inputcvv2 pull-right form-control" ><option value='2014'>2014</option><option value='2015'>2015</option><option value='2016'>2016</option><option value='2017'>2017</option><option value='2018'>2018</option><option value='2019'>2019</option><option value='2020'>2020</option><option value='2021'>2021</option><option value='2022'>2022</option><option value='2023'>2023</option><option value='2024'>2024</option><option value='2025'>2025</option></select>
								</div>
								 <div style="color:red;" class="billing_error" id="billing_error_cvv"></div>
								 <br/>
								 <input type="submit" width="460" src="theme/images/purchase.png" class="btn btn-success btn-lg btn-block" id="vaultthis" name="vault" value="RUSH ORDER">
				 </form></div></div>
				 <script>
				 	$('form').card({
					    // a selector or jQuery object for the container
					    // where you want the card to appear
					    container: '.card-wrapper', // *required*
					    nameInput: 'input[name="first_name"], input[name="last_name"]',
					    
					   
					    width: 380, // optional — default 350px
					    formatting: true, // optional - default true

					    // Strings for translation - optional
					    messages: {
					        validDate: 'valid\ndate', // optional - default 'valid\nthru'
					        monthYear: 'mm/yyyy', // optional - default 'month/year'
					    },

					    // Default values for rendered fields - options
					    values: {
					        number: '',
					        name: 'Full Name',
					        expiry: '••/••',
					        cvc: '•••'
					    }
					});
				 </script>
 <div id="checkoutform" style="display:none">
    <form id="hiddencheckout" action="<?=$action?>" method="post">
       <input type="text" id="insert_token" name="token"></input>
       Authorizing...
    </form>
 </div>

<script type="text/javascript" src="jquery.js"></script>

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
          url: 'https://ratchet.rebarsecure.com/v1/public/payment_methods',
          data: f.serialize(),
          success: function(data) {
            js = $.parseJSON(data);
            console.log("ALMOST TO TOKEN");
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

function rebar_print_js_include() {
?>
	  	<script src='//rebarsecure.com/static/assets/jquery.js' type='text/javascript'></script>
	  	<script src='//rebarsecure.com/static/assets/countries-2.0-min.js' type='text/javascript'></script>
			<script src="//rebarsecure.com/static/assets/jquery.validation.js" type="text/javascript"></script>
<?
}