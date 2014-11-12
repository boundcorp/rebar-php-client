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

	                      <div class="col-xs-12"> 
	                       <select name="region" value="<?=@$_rebar_lead->region?>" class="form-control" >
							<option value="AL">Alabama</option>
							<option value="AK">Alaska</option>
							<option value="AZ">Arizona</option>
							<option value="AR">Arkansas</option>
							<option value="CA">California</option>
							<option value="CO">Colorado</option>
							<option value="CT">Connecticut</option>
							<option value="DE">Delaware</option>
							<option value="FL">Florida</option>
							<option value="GA">Georgia</option>
							<option value="HI">Hawaii</option>
							<option value="ID">Idaho</option>
							<option value="IL">Illinois</option>
							<option value="IN">Indiana</option>
							<option value="IA">Iowa</option>
							<option value="KS">Kansas</option>
							<option value="KY">Kentucky</option>
							<option value="LA">Louisiana</option>
							<option value="ME">Maine</option>
							<option value="MD">Maryland</option>
							<option value="MA">Massachusetts</option>
							<option value="MI">Michigan</option>
							<option value="MN">Minnesota</option>
							<option value="MS">Mississippi</option>
							<option value="MO">Missouri</option>
							<option value="MT">Montana</option>
							<option value="NE">Nebraska</option>
							<option value="NV">Nevada</option>
							<option value="NH">New Hampshire</option>
							<option value="NJ">New Jersey</option>
							<option value="NM">New Mexico</option>
							<option value="NY">New York</option>
							<option value="NC">North Carolina</option>
							<option value="ND">North Dakota</option>
							<option value="OH">Ohio</option>
							<option value="OK">Oklahoma</option>
							<option value="OR">Oregon</option>
							<option value="PA">Pennsylvania</option>
							<option value="RI">Rhode Island</option>
							<option value="SC">South Carolina</option>
							<option value="SD">South Dakota</option>
							<option value="TN">Tennessee</option>
							<option value="TX">Texas</option>
							<option value="UT">Utah</option>
							<option value="VT">Vermont</option>
							<option value="VA">Virginia</option>
							<option value="WA">Washington</option>
							<option value="WV">West Virginia</option>
							<option value="WI">Wisconsin</option>
							<option value="WY">Wyoming</option>
							</select></div>
	                       <div class="col-xs-3">
	                       <select placeholder="Country" value="<?=@$_rebar_lead->country?>" class="name new_lead form-control">
							<option value="AFG">Afghanistan</option>
							<option value="ALA">Åland Islands</option>
							<option value="ALB">Albania</option>
							<option value="DZA">Algeria</option>
							<option value="ASM">American Samoa</option>
							<option value="AND">Andorra</option>
							<option value="AGO">Angola</option>
							<option value="AIA">Anguilla</option>
							<option value="ATA">Antarctica</option>
							<option value="ATG">Antigua and Barbuda</option>
							<option value="ARG">Argentina</option>
							<option value="ARM">Armenia</option>
							<option value="ABW">Aruba</option>
							<option value="AUS">Australia</option>
							<option value="AUT">Austria</option>
							<option value="AZE">Azerbaijan</option>
							<option value="BHS">Bahamas</option>
							<option value="BHR">Bahrain</option>
							<option value="BGD">Bangladesh</option>
							<option value="BRB">Barbados</option>
							<option value="BLR">Belarus</option>
							<option value="BEL">Belgium</option>
							<option value="BLZ">Belize</option>
							<option value="BEN">Benin</option>
							<option value="BMU">Bermuda</option>
							<option value="BTN">Bhutan</option>
							<option value="BOL">Bolivia, Plurinational State of</option>
							<option value="BES">Bonaire, Sint Eustatius and Saba</option>
							<option value="BIH">Bosnia and Herzegovina</option>
							<option value="BWA">Botswana</option>
							<option value="BVT">Bouvet Island</option>
							<option value="BRA">Brazil</option>
							<option value="IOT">British Indian Ocean Territory</option>
							<option value="BRN">Brunei Darussalam</option>
							<option value="BGR">Bulgaria</option>
							<option value="BFA">Burkina Faso</option>
							<option value="BDI">Burundi</option>
							<option value="KHM">Cambodia</option>
							<option value="CMR">Cameroon</option>
							<option value="CAN">Canada</option>
							<option value="CPV">Cape Verde</option>
							<option value="CYM">Cayman Islands</option>
							<option value="CAF">Central African Republic</option>
							<option value="TCD">Chad</option>
							<option value="CHL">Chile</option>
							<option value="CHN">China</option>
							<option value="CXR">Christmas Island</option>
							<option value="CCK">Cocos (Keeling) Islands</option>
							<option value="COL">Colombia</option>
							<option value="COM">Comoros</option>
							<option value="COG">Congo</option>
							<option value="COD">Congo, the Democratic Republic of the</option>
							<option value="COK">Cook Islands</option>
							<option value="CRI">Costa Rica</option>
							<option value="CIV">Côte d'Ivoire</option>
							<option value="HRV">Croatia</option>
							<option value="CUB">Cuba</option>
							<option value="CUW">Curaçao</option>
							<option value="CYP">Cyprus</option>
							<option value="CZE">Czech Republic</option>
							<option value="DNK">Denmark</option>
							<option value="DJI">Djibouti</option>
							<option value="DMA">Dominica</option>
							<option value="DOM">Dominican Republic</option>
							<option value="ECU">Ecuador</option>
							<option value="EGY">Egypt</option>
							<option value="SLV">El Salvador</option>
							<option value="GNQ">Equatorial Guinea</option>
							<option value="ERI">Eritrea</option>
							<option value="EST">Estonia</option>
							<option value="ETH">Ethiopia</option>
							<option value="FLK">Falkland Islands (Malvinas)</option>
							<option value="FRO">Faroe Islands</option>
							<option value="FJI">Fiji</option>
							<option value="FIN">Finland</option>
							<option value="FRA">France</option>
							<option value="GUF">French Guiana</option>
							<option value="PYF">French Polynesia</option>
							<option value="ATF">French Southern Territories</option>
							<option value="GAB">Gabon</option>
							<option value="GMB">Gambia</option>
							<option value="GEO">Georgia</option>
							<option value="DEU">Germany</option>
							<option value="GHA">Ghana</option>
							<option value="GIB">Gibraltar</option>
							<option value="GRC">Greece</option>
							<option value="GRL">Greenland</option>
							<option value="GRD">Grenada</option>
							<option value="GLP">Guadeloupe</option>
							<option value="GUM">Guam</option>
							<option value="GTM">Guatemala</option>
							<option value="GGY">Guernsey</option>
							<option value="GIN">Guinea</option>
							<option value="GNB">Guinea-Bissau</option>
							<option value="GUY">Guyana</option>
							<option value="HTI">Haiti</option>
							<option value="HMD">Heard Island and McDonald Islands</option>
							<option value="VAT">Holy See (Vatican City State)</option>
							<option value="HND">Honduras</option>
							<option value="HKG">Hong Kong</option>
							<option value="HUN">Hungary</option>
							<option value="ISL">Iceland</option>
							<option value="IND">India</option>
							<option value="IDN">Indonesia</option>
							<option value="IRN">Iran, Islamic Republic of</option>
							<option value="IRQ">Iraq</option>
							<option value="IRL">Ireland</option>
							<option value="IMN">Isle of Man</option>
							<option value="ISR">Israel</option>
							<option value="ITA">Italy</option>
							<option value="JAM">Jamaica</option>
							<option value="JPN">Japan</option>
							<option value="JEY">Jersey</option>
							<option value="JOR">Jordan</option>
							<option value="KAZ">Kazakhstan</option>
							<option value="KEN">Kenya</option>
							<option value="KIR">Kiribati</option>
							<option value="PRK">Korea, Democratic People's Republic of</option>
							<option value="KOR">Korea, Republic of</option>
							<option value="KWT">Kuwait</option>
							<option value="KGZ">Kyrgyzstan</option>
							<option value="LAO">Lao People's Democratic Republic</option>
							<option value="LVA">Latvia</option>
							<option value="LBN">Lebanon</option>
							<option value="LSO">Lesotho</option>
							<option value="LBR">Liberia</option>
							<option value="LBY">Libya</option>
							<option value="LIE">Liechtenstein</option>
							<option value="LTU">Lithuania</option>
							<option value="LUX">Luxembourg</option>
							<option value="MAC">Macao</option>
							<option value="MKD">Macedonia, the former Yugoslav Republic of</option>
							<option value="MDG">Madagascar</option>
							<option value="MWI">Malawi</option>
							<option value="MYS">Malaysia</option>
							<option value="MDV">Maldives</option>
							<option value="MLI">Mali</option>
							<option value="MLT">Malta</option>
							<option value="MHL">Marshall Islands</option>
							<option value="MTQ">Martinique</option>
							<option value="MRT">Mauritania</option>
							<option value="MUS">Mauritius</option>
							<option value="MYT">Mayotte</option>
							<option value="MEX">Mexico</option>
							<option value="FSM">Micronesia, Federated States of</option>
							<option value="MDA">Moldova, Republic of</option>
							<option value="MCO">Monaco</option>
							<option value="MNG">Mongolia</option>
							<option value="MNE">Montenegro</option>
							<option value="MSR">Montserrat</option>
							<option value="MAR">Morocco</option>
							<option value="MOZ">Mozambique</option>
							<option value="MMR">Myanmar</option>
							<option value="NAM">Namibia</option>
							<option value="NRU">Nauru</option>
							<option value="NPL">Nepal</option>
							<option value="NLD">Netherlands</option>
							<option value="NCL">New Caledonia</option>
							<option value="NZL">New Zealand</option>
							<option value="NIC">Nicaragua</option>
							<option value="NER">Niger</option>
							<option value="NGA">Nigeria</option>
							<option value="NIU">Niue</option>
							<option value="NFK">Norfolk Island</option>
							<option value="MNP">Northern Mariana Islands</option>
							<option value="NOR">Norway</option>
							<option value="OMN">Oman</option>
							<option value="PAK">Pakistan</option>
							<option value="PLW">Palau</option>
							<option value="PSE">Palestinian Territory, Occupied</option>
							<option value="PAN">Panama</option>
							<option value="PNG">Papua New Guinea</option>
							<option value="PRY">Paraguay</option>
							<option value="PER">Peru</option>
							<option value="PHL">Philippines</option>
							<option value="PCN">Pitcairn</option>
							<option value="POL">Poland</option>
							<option value="PRT">Portugal</option>
							<option value="PRI">Puerto Rico</option>
							<option value="QAT">Qatar</option>
							<option value="REU">Réunion</option>
							<option value="ROU">Romania</option>
							<option value="RUS">Russian Federation</option>
							<option value="RWA">Rwanda</option>
							<option value="BLM">Saint Barthélemy</option>
							<option value="SHN">Saint Helena, Ascension and Tristan da Cunha</option>
							<option value="KNA">Saint Kitts and Nevis</option>
							<option value="LCA">Saint Lucia</option>
							<option value="MAF">Saint Martin (French part)</option>
							<option value="SPM">Saint Pierre and Miquelon</option>
							<option value="VCT">Saint Vincent and the Grenadines</option>
							<option value="WSM">Samoa</option>
							<option value="SMR">San Marino</option>
							<option value="STP">Sao Tome and Principe</option>
							<option value="SAU">Saudi Arabia</option>
							<option value="SEN">Senegal</option>
							<option value="SRB">Serbia</option>
							<option value="SYC">Seychelles</option>
							<option value="SLE">Sierra Leone</option>
							<option value="SGP">Singapore</option>
							<option value="SXM">Sint Maarten (Dutch part)</option>
							<option value="SVK">Slovakia</option>
							<option value="SVN">Slovenia</option>
							<option value="SLB">Solomon Islands</option>
							<option value="SOM">Somalia</option>
							<option value="ZAF">South Africa</option>
							<option value="SGS">South Georgia and the South Sandwich Islands</option>
							<option value="SSD">South Sudan</option>
							<option value="ESP">Spain</option>
							<option value="LKA">Sri Lanka</option>
							<option value="SDN">Sudan</option>
							<option value="SUR">Suriname</option>
							<option value="SJM">Svalbard and Jan Mayen</option>
							<option value="SWZ">Swaziland</option>
							<option value="SWE">Sweden</option>
							<option value="CHE">Switzerland</option>
							<option value="SYR">Syrian Arab Republic</option>
							<option value="TWN">Taiwan, Province of China</option>
							<option value="TJK">Tajikistan</option>
							<option value="TZA">Tanzania, United Republic of</option>
							<option value="THA">Thailand</option>
							<option value="TLS">Timor-Leste</option>
							<option value="TGO">Togo</option>
							<option value="TKL">Tokelau</option>
							<option value="TON">Tonga</option>
							<option value="TTO">Trinidad and Tobago</option>
							<option value="TUN">Tunisia</option>
							<option value="TUR">Turkey</option>
							<option value="TKM">Turkmenistan</option>
							<option value="TCA">Turks and Caicos Islands</option>
							<option value="TUV">Tuvalu</option>
							<option value="UGA">Uganda</option>
							<option value="UKR">Ukraine</option>
							<option value="ARE">United Arab Emirates</option>
							<option value="GBR">United Kingdom</option>
							<option value="USA">United States</option>
							<option value="UMI">United States Minor Outlying Islands</option>
							<option value="URY">Uruguay</option>
							<option value="UZB">Uzbekistan</option>
							<option value="VUT">Vanuatu</option>
							<option value="VEN">Venezuela, Bolivarian Republic of</option>
							<option value="VNM">Viet Nam</option>
							<option value="VGB">Virgin Islands, British</option>
							<option value="VIR">Virgin Islands, U.S.</option>
							<option value="WLF">Wallis and Futuna</option>
							<option value="ESH">Western Sahara</option>
							<option value="YEM">Yemen</option>
							<option value="ZMB">Zambia</option>
							<option value="ZWE">Zimbabwe</option>
						</select>
					</div><br/><br/><br/>
					     <input type=text size="55" class="name new_lead" name="zip" required value="<?=@$_rebar_lead->zip?>" placeholder="zip"><br /><br />

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
									 <div style="color:red" class="billing_error" id="billing_error_last_name"></div><br />
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