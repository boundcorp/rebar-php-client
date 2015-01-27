<?php

class rebar {

    public $visitor = null;
    public $domain = null;
    public $call_count = 0;

    function __construct() {
        global $config; // This comes from the rebar config

        if ($config['rebar']['debug'])
            ini_set('display_errors', 1);

        // Set cart field defaults
        $this->visitor = new stdClass();
        $this->visitor->visitor_id = $this->rebar_get_cached_val('visitor_id');
        $this->visitor->cart = (object) $_SESSION['rebar'];
        $this->domain = $_SERVER['HTTP_HOST'];
    }

    /**
     * Count number of items in the cart
     * You must call rebar_track_visitor before calling this so that you can populate the cart value.
     *
     * @param string $name
     * @return string
     */
    function rebar_cart_size() {
        $size = 0;
        if ($this->visitor->cart->items > 0) {
            foreach ($this->visitor->cart->items as $item) {
                $size = $size + $item->quantity;
            }
        }
        return (int) $size;
    }

    /**
     * Get Value from Cookie
     *
     * @param string $name
     * @return string
     */
    function rebar_get_cached_val($name) {
        $var = "_rebar_${name}";
        global $$var;
        if (empty($$var)) {
            $$var = @$_SESSION['rebar'][$name];
            $this->debug_print("<br />Reading $var from session: " . $$var . "<br /><hr />");
        }
        if (empty($$var)) {
            $$var = @$_COOKIE['rebar_' . $name];
            $this->debug_print("<br />Reading $var from cookies: " . $$var . "<br /><hr />");
        }
        return $$var;
    }

    /**
     * Set Value in Cookie
     *
     * @param string $name
     * @param string $val
     * @param string $ttl
     */
    function rebar_set_cached_val($name, $val, $ttl = 86400) {
        $this->debug_print("<br />Setting SESSION: rebar[$name] = $val<br /><hr />");
        if (!empty($val))
            $_SESSION['rebar'][$name] = $val;
        $this->debug_print("<br />Setting COOKIE: $name = $val (ttl: $ttl, path: /, domain: .{$this->domain})<br /><hr />");
        if (!empty($val))
            setcookie("rebar_${name}", $val, time() + $ttl, '/', '.' . $this->domain);
    }

    /**
     * Track Visitor
     * Send visitor headers and initial fields
     * This function is used to track a visitor as they move around the site, it will also provide a visitor_id if one doesn't already exists.
     */
    function rebar_track_visitor() {
        $pass_headers = array('HTTP_HOST', 'PATH_INFO', 'HTTP_USER_AGENT', 'REMOTE_ADDR',
                'HTTP_REFERER', 'HTTP_X_FORWARDED_FOR', 'QUERY_STRING', 'REQUEST_URI');
        $pass_gets = array_keys($_GET);
        $info = array();
        $id = empty($this->visitor->visitor_id) ? '' : $this->visitor->visitor_id; // This makes u
        foreach ($pass_headers as $h) {
            if (isset($_SERVER[$h])) {
                $info[$h] = $_SERVER[$h];
            } else {
                $info[$h] = '';
            }
        }

        if (!empty($this->visitor->cart->brand_id))
            $info['brand_id'] = $this->visitor->cart->brand_id;
        if (!empty($this->visitor->cart->product_id))
            $info['product_id'] = $this->visitor->cart->product_id;

        // Lets send off the affiliate_id and sub_id so that we can track the visitor
        if (!empty($this->visitor->cart->aff_id))
            $info['affiliate_id'] = $this->visitor->cart->aff_id;
        if (!empty($this->visitor->cart->sub_id))
            $info['sub_id'] = $this->visitor->cart->sub_id;

        $i = base64_encode(json_encode($info)); // Set i var with get params
        $cart = (array) $this->visitor->cart; // Set existing cart
        // There is a lot of information in the visitor object. I think once the tracking is fired, you could potentially use that call to set all the values locally.
        $visitor = $this->rebar_post("crm/track_visit?_id=$id&i=$i");

        unset($visitor->lead->carts);
        unset($visitor->cart->lead);

        if ($visitor->cart->locked AND $visitor->cart->payment_attempted) {
            $visitor->cart = (object) $cart;
            unset($visitor->cart->cart_id);
        }

        $this->visitor = $visitor;

        $this->rebar_set_cached_val('visitor_id', $this->visitor->visitor_id, 86400 * 365);

        if (!empty($this->visitor->cart->cart_id)) { // lets cache the cart_id
            $this->rebar_set_cached_val('cart_id', $this->visitor->cart->cart_id, 86400 * 365); // not even sure that setting this is important, seems only the visitor_id is important
        }

        return true;
    }

    function rebar_ensure_cart_exists_or_make_one() {
        if (empty($this->visitor->cart->cart_id)) // Check if we have a cart_id saved in cache
            @$this->visitor->cart->cart_id = $this->rebar_get_cached_val('cart_id');
        if (!empty($this->visitor->cart->cart_id) AND ! isset($this->visitor->cart->locked)) { // Check if the cart is set, if not get a cart
            @$this->visitor->cart = $this->rebar_post("crm/create_cart", array('visitor_id' => $this->visitor->visitor_id, 'cart_id' => $this->visitor->cart->cart_id));
            $this->rebar_set_cached_val('cart_id', $this->visitor->cart->cart_id, 86400);
        }
        if (empty($this->visitor->cart->cart_id) OR $this->visitor->cart->locked) { // If no cart or cart is locked, create a new cart
            unset($this->visitor->cart->cart_id);
            @$this->visitor->cart = $this->rebar_post("crm/create_cart", array('visitor_id' => $this->visitor->visitor_id, 'cart_id' => $this->visitor->cart->cart_id));
            $this->rebar_set_cached_val('cart_id', $this->visitor->cart->cart_id, 86400);
        }
    }

    function rebar_update_cart_data() {
        $data = array(
                'brand_id' => $this->visitor->cart->brand_id,
                'product_id' => $this->visitor->cart->product_id,
                'visitor_id' => $this->visitor->visitor_id,
                'first_name' => $this->visitor->cart->first_name,
                'last_name' => $this->visitor->cart->last_name,
                'cell' => $this->visitor->cart->cell,
                'email' => $this->visitor->cart->email,
                'address_one' => $this->visitor->cart->address_one,
                'address_two' => $this->visitor->cart->address_two,
                'city' => $this->visitor->cart->city,
                'region' => $this->visitor->cart->region,
                'country' => $this->visitor->cart->country,
                'zipcode' => $this->visitor->cart->zipcode,
                'affiliate_id' => $this->visitor->cart->affiliate_id,
                'sub_id' => $this->visitor->cart->sub_id,
                'ip' => $_SERVER['REMOTE_ADDR']
        );

        $this->rebar_ensure_cart_exists_or_make_one();

        $data['cart_id'] = $this->visitor->cart->cart_id;

        $cart = $this->rebar_post('crm/create_cart', $data);

        $this->visitor->cart->cart_id = $cart->cart_id;
        $this->rebar_set_cached_val('cart_id', $this->visitor->cart->cart_id, 86400 * 365);
    }

    function rebar_get_cart_info($cart_id) {
        if (!empty($cart_id)) {
            $cart = $this->rebar_post('crm/cart_info', array('cart_id' => $cart_id));
        } else {
            $cart = false;
        }
        return $cart;
    }

    function _rebar_cart_is_locked() {
        return (isset($this->visitor->cart->error) && ($this->visitor->cart->error == 'No such cart' ||
            $this->visitor->cart->error == 'Invalid cart_id') || $this->visitor->cart->payment_success);
    }

    function rebar_update_cart_cookies_from_obj() {
        if (isset($this->visitor->cart->items))
            $this->rebar_set_cached_val('cart_count', $this->rebar_cart_size());
        if ($this->_rebar_cart_is_locked($this->visitor->cart)) {
            $this->visitor = $this->rebar_post("crm/create_cart", array('visitor_id' => $this->visitor->visitor_id, 'cart_id' => $this->visitor->cart->cart_id));
            $this->rebar_set_cached_val('cart_id', $this->visitor->cart->cart_id);
            return false;
        }
        $this->rebar_set_cached_val('cart_id', $this->visitor->cart->cart_id);
        return true;
    }

    function rebar_authorize_cart($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->visitor = $this->rebar_post('crm/purchase_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'token' => $token,
                'authorize' => 'true'
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_authorize_dollar($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->visitor = $this->rebar_post('crm/purchase_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'token' => $token,
                'authorize' => 'true',
                'amount' => $this->visitor->cart->amount
        ));

        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_autofinalize_cart($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->visitor = $this->rebar_post('crm/purchase_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'token' => $token,
                'autofinalize' => 'true'
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_remove_from_cart($product_id) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $obj = $this->rebar_post('crm/remove_from_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'product_id' => $product_id
        ));
    }

    function rebar_update_cart_quantities($quantities) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $fields = $quantities;
        $fields['cart_id'] = $this->visitor->cart->cart_id;
        $obj = $this->rebar_post('crm/update_cart_quantities', $fields);
    }

    function rebar_add_to_cart($product_id, $first_attempt = true) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->visitor->cart = $this->rebar_post('crm/add_to_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'product_id' => $product_id
        ));

        if ($this->_rebar_cart_is_locked() && $first_attempt) {
            $this->rebar_update_cart_cookies_from_obj();
            $this->visitor->cart = $this->rebar_add_to_cart($product_id, false);
        }
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_add_subscription_to_cart($product_id) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->visitor->cart = $this->rebar_post('crm/add_to_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'subscription_product_id' => $product_id
        ));
        if (!$this->rebar_update_cart_cookies_from_obj())
            $this->rebar_add_subscription_to_cart($product_id);
        return true;
    }

    function rebar_post($stub, $fields = array()) {
        global $config;
        $this->call_count = $this->call_count + 1;
        if ($this->call_count > $config['rebar']['call_max'])
            return new stdClass(); // This should throw an exception
        $response = $this->post_data($config['rebar']['endpoint'] . $stub, $this->rebar_params($fields));
        // This should go into $this->response
        $obj = @json_decode($response);
        return $obj;
    }

    function ratchet_tokenize_fields() {
        global $config;
        $obj = $this->ratchet_post('payment_methods', array(
                'environment' => $config['rebar']['environment'],
                'first_name' => $this->visitor->cart->first_name,
                'last_name' => $this->visitor->cart->last_name,
                'address1' => $this->visitor->cart->address1,
                'address2' => $this->visitor->cart->address2,
                'city' => $this->visitor->cart->city,
                'state' => $this->visitor->cart->state,
                'zip' => $this->visitor->cart->zip,
                'country' => $this->visitor->cart->country,
                'email' => $this->visitor->cart->email,
                'number' => $this->visitor->cart->number,
                'month' => $this->visitor->cart->month,
                'year' => $this->visitor->cart->year,
                'cvv' => $this->visitor->cart->cvv
        ));
        return $obj->token;
    }

    function ratchet_post($stub, $fields = array()) {
        global $config;
        $this->call_count = $this->call_count + 1;
        if ($this->call_count > $config['rebar']['call_max'])
            return new stdClass();
        $response = $this->post_data($config['rebar']['ratchet'] . $stub, $fields);
        // This should go into $this->response
        $obj = @json_decode($response);
        return $obj;
    }

    function rebar_params($extra_params) {
        global $config;
        return array_merge(array(
                'environment' => $config['rebar']['environment'],
                'secret' => $config['rebar']['secret']
            ), (array) $extra_params);
    }

    function debug_print($str) {
        global $config;
        if ($config['rebar']['debug'])
            echo $str;
    }

    function debug_redirect($url) {
        global $config;
        if (!$config['rebar']['debug']) {
            header('Location: ' . $url);
        } else {
            $this->debug_print('<a href="' . $url . '">HTTP Header Location: ' . $url . '</a>');
        }
    }

    function post_data($url, $fields) {
        try {
            $fields_string = '';

            //url-ify the data for the POST
            foreach ($fields as $key => $value) {
                $fields_string .= $key . '=' . $value . '&';
            }

            rtrim($fields_string, '&');
            $this->debug_print('POSTING TO API: ' . $url);
            $this->debug_print('<br />');
            $this->debug_print($fields_string);
            $this->debug_print('<br />');

            //open connection
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            //execute post
            $result = curl_exec($ch);
            $this->debug_print('Got result:<br />' . $result . '<hr />');
            //close connection
            curl_close($ch);
            return $result;
        } catch (Exception $exc) {
            throw new Exception('There was an error posting to the api. ' . $exc->getTraceAsString());
        }
    }

    function rebar_purchase_cart($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->visitor = $this->rebar_post('crm/purchase_cart', array(
                'cart_id' => $this->visitor->cart->cart_id,
                'token' => $token
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_output_shipping_data_form($action = '') {
        global $config;
        // $this->rebar_print_js_include();
        ?>
        <form id="validatedLeadForm" class="pure-form" action="<?php echo $action ?>" method="post">
            <?php
            // I don't think the following makes all that much sense anymore.
            if (!empty($this->visitor->cart)) {
                ?>
                <input type="radio" class="new_or_old_lead" name="cart_id" id="new_lead_radio" value="_new"><label for="new_lead_radio">New shipping details</label>
                - or -
                <input type="radio" checked class="new_or_old_lead" name="cart_id" id="old_lead_radio" value="<?php echo @$this->visitor->cart->cart_id ?>"><label for="old_lead_radio">Re-use these details below...</label>
                <br /><br />
                <?php
            }
            ?>
            <input type="hidden" name="cart_id" class="name" value="<?php echo @$this->visitor->cart->cart_id ?>">
            <label for="first_name" class="error half-field"></label>
            <label for="last_name" class="error half-field last"></label>
            <div class="clear"></div>
            <input type=text class="name new_lead half-field" name="first_name" id="first_name" value="<?php echo @$this->visitor->lead->first_name ?>" placeholder="First Name">
            <input type=text class="name new_lead half-field last" name="last_name" id="last_name" value="<?php echo @$this->visitor->lead->last_name ?>" placeholder="Last Name"><br /><br />
            <label for="email" class="error half-field"></label>
            <label for="cell" class="error half-field last"></label>
            <div class="clear"></div>
            <input type="email" class="email new_lead half-field" name="email" id="email" value="<?php echo @$this->visitor->lead->email ?>" placeholder="Email">
            <input type="tel" class="phone new_lead half-field last" name="cell" id="cell" value="<?php echo @$this->visitor->lead->cell ?>" placeholder="Phone"><br /><br />
            <label for="address_one" class="error"></label>
            <input type=text class="name new_lead" name="address_one" id="address_one" value="<?php echo @$this->visitor->lead->address_one ?>" placeholder="Address Line 1"><br /><br />
            <input type=text class="name new_lead" name="address_two" id="address_two" value="<?php echo @$this->visitor->lead->address_two ?>" placeholder="Address Line 2"><br /><br />
            <label for="city" class="error half-field"></label>
            <label for="zipcode" class="error half-field last"></label>
            <div class="clear"></div>
            <input type=text class="name new_lead half-field" name="city" id="city" value="<?php echo @$this->visitor->lead->city ?>" placeholder="City">
            <input type=text class="name new_lead half-field last" name="zipcode" id="zipcode" value="<?php echo @$this->visitor->lead->zipcode ?>" placeholder="Zip or Postal Code"><br /><br />
            <label for="country" class="error half-field"></label>
            <label for="region" class="error half-field last"></label>
            <div class="clear"></div>
            <?php
            if (count($config['countries']) > 0) {
                ?>
                <select name="country" class="name new_lead select<?php echo $hideCountry ?> half-field" id="country" >
                    <option value="">Select Country</option>
                    <?php
                    foreach ($config['countries'] as $value => $name) {
                        $selected = '';
                        if ($hideCountry)
                            $selected = ' selected="selected"';
                        if ($this->visitor->lead->country == $value)
                            $selected = ' selected="selected"';
                        ?>
                        <option value="<?php echo $value ?>"<?php echo $selected ?>><?php echo $name ?></option>
                        <?php
                    }
                    ?>
                </select>
                <?php
            }
            ?>
            <select name="region" class="name new_lead select half-field last" id="region">
                <option value="">Select Country First</option>
            </select><br /><br />
            <input type="image" width="460" src="theme/images/continue.png" class="btn btn-success btn-lg btn-block btn-continue" value="Next" >
        </form>
        <?php
        $jqueryReady = <<<JS
                f = $('#validatedLeadForm');
                $('input.new_or_old_lead[type=radio]', f).change(function () {
                    if ($(this).val() == '_new') {
                        $('input.name', f).removeAttr('disabled');
                    }
                    else {
                        $('input.name', f).attr('disabled', 'disabled');
                    }
                })

                $.validator.setDefaults({
                    submitHandler: function(form) {
                        showLoading();
                        form.submit();
                    }
                });

                var MainForm = '#validatedLeadForm';
                $(MainForm).validate({
                    rules: {
                        first_name: { required: true, minlength: 2 },
                        last_name: { required: true, minlength: 2 },
                        email: { required: true, email: true },
                        cell: { required: true },
                        address_one: { required: true },
                        city: { required: true },
                        zipcode: { required: true },
                        country: { required: true },
                        region: { required: true }
                    },
                    errorPlacement: function(error, element) {
                        // Append error within linked label
                        $( element )
                            .closest( "form" )
                                .find( "label[for='" + element.attr( "id" ) + "']" )
                                    .append( error );
                    },
                    errorElement: "span",
                    messages: {
                        first_name: { required: "First name is required", minlength: "First name is too short" },
                        last_name: { required: "Last name is required", minlength: "Last name is too short" },
                        email: { required: "Email is required", email: "Email is invalid please check for typo" },
                        cell: { required: "Phone number is required" },
                        address_one: { required: "Address is required" },
                        city: { required: "City is required" },
                        zipcode: { required: "Zip Code is required" },
                        country: { required: "Country is required" },
                        region: { required: "State is required" }
                    }
                });

                if ($('select[name=country]', MainForm).length > 0) {
                    var selShipCountry = '{$this->visitor->lead->country}';
                    var selShipState = '{$this->visitor->lead->region}';
                    getStates(MainForm, '', selShipCountry, selShipState);
                    // Chain the state field
                    $('select[name=country]', MainForm).change(function () {
                        getStates(MainForm, '');
                    });
                }

JS;

        return $jqueryReady;
    }

    function rebar_output_billing_data_form($action = '') {
        global $config, $ci;
        ?>
        <h2> Provide Your Credit Card Details Below </h2> <br/>
        <?php
        if ($config['rebar']['debug'] == 1) {
            $jqueryReady .= <<<JS
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
                $(function () {
                    $
                    $('.rebar_cc_debug').click(function () {
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

JS;
            ?>
            TEST BILLING DETAILS:<br />
            <a href="#" class="rebar_cc_debug" id="authnet_good_hot_test">Auth.Net GOOD HOT TEST</a>
            <br />
            <a href="#" class="rebar_cc_debug" id="authnet_good_faux_test">Auth.Net GOOD faux TEST</a>
            <br />
            <a href="#" class="rebar_cc_debug" id="stripe_good_faux_test">STRIPE BLT GOOD faux TEST</a>
            <br />
        <?php } ?>
        <?php
        if (!empty($this->visitor->cart->errors)) {
            ?>
            <ul class="errorlist">
                <?php
                foreach ($this->visitor->cart->errors as $i => $e) {
                    echo "<li>" . $e . "</li>";
                }
                ?>
            </ul>
            <?php
        }
        ?>

        <form action='https://ratchet.rebarsecure.com/v1/public/payment_methods' class="form-horizontal salespage_form ratchet"  method="post" >
            <input type="hidden" name="environment" class="name" value="<?php echo $config['rebar']['environment'] ?>">
            <input type="hidden" name="brand_id" class="name" value="<?php echo @$this->visitor->cart->brand_id ?>">
            <input type="hidden" name="visitor_id" class="name" value="<?php echo @$this->visitor->visitor_id ?>">
            <input type="hidden" name="cart_id" class="name" value="<?php echo @$this->visitor->cart->cart_id ?>">
            <label for="first_name" class="error half-field"></label>
            <label for="last_name" class="error half-field last"></label>
            <div class="clear"></div>
            <input type=text placeholder='First Name' name="first_name" id="first_name" class="name form-control half-field" value="<?php echo @$this->visitor->lead->first_name ?>">
            <input type=text placeholder='Last Name' name="last_name" id="last_name" class="name form-control half-field last" value="<?php echo @$this->visitor->lead->last_name ?>"><br /><br />
            <label for="email" class="error"></label>
            <input type=text placeholder='Email' name="email" id="email" class="email form-control" value="<?php echo @$this->visitor->lead->email ?>"><br /><br />
            <?php
            // The fact that this calls an external dependant needs to be changed, I don't really think any of this html should be in this class at all.
            if (count((array) $ci->cards) > 0) {
                $accepted_cards = '';
                if ($ci->form->cards == 'centered') {
                    ?>
                    <div class="methods">
                        <ul class="cards">
                            <?php
                            foreach ((array) $ci->cards as $id => $card) {
                                $accepted_cards .= "'{$card['name']}',";
                                ?>
                                <li class="<?php echo $card['name'] ?>"><?php echo $card['card'] ?></li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                    <br />
                    <?php
                } else if ($ci->form->cards == 'we_accept') {
                    ?>
                    <div class="label left"><?php echo $lang['form']['We_Accept'] ?>:</div>
                    <div class="input left">
                        <ul class="cards">
                            <?php
                            foreach ((array) $ci->cards as $id => $card) {
                                $accepted_cards .= "'{$card['name']}',";
                                ?>
                                <li class="<?php echo $card['name'] ?>"><?php echo $card['card'] ?></li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                    <?php
                }
                $accepted_cards = rtrim($accepted_cards, ',');
            }
            ?>
            <label for="number" class="error"></label>
            <input type="text" placeholder="Card Number" class="name form-control" name="number" id="number" maxlength="19" value="<?php echo @$this->visitor->lead->number ?>"><br /><br />
            <label for="year" class="error"></label>
            <select name="month" id="month" class="name form-control half-field">
                <option value="">Expiry Month</option>
                <?php
                for ($i = 1; $i <= 12; $i++) {
                    $selected = ($si->fields->creditCardExpirationMonth == str_pad($i, 2, '0', STR_PAD_LEFT)) ? 'selected="selected"' : '';
                    ?>
                    <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?php echo $selected ?>><?php echo str_pad($i, 2, '0', STR_PAD_LEFT) ?></option>
                    <?php
                }
                ?>
            </select>
            <select name="year" id="year" class="name form-control half-field last">
                <option value="">Expiry Year</option>
                <?php
                for ($i = date('Y'); $i <= date('Y', strtotime("+10 YEAR")); $i++) {
                    $selected = ($si->fields->creditCardExpirationYear == $i) ? 'selected="selected"' : '';
                    ?>
                    <option value="<?php echo $i ?>" <?php echo $selected ?>><?php echo $i ?></option>
                    <?php
                }
                ?>
            </select> <br /><br />
            <label for="cvv" class="error"></label>
            <input type=text placeholder='CVV' name="cvv" id="cvv" class="name form-control" value="<?php echo @$this->visitor->cart->cvv ?>"><br /><br />
            <a href="#cvvTip" class="cvvTip">What's this?</a><br /><br />
            <input type="image" width="460" src="theme/images/purchase.png" class="btn btn-success btn-lg btn-block btn-purchase" id="vaultthis" name="vault" value="BUY NOW">
        </form>
        <div id="checkoutform" style="display:none">
            <form id="hiddencheckout" action="<?php echo $action ?>" method="post">
                <input type=hidden name="visitor_id" id="visitor_id" required value="<?php echo @$this->visitor->visitor_id ?>" />
                <input type=hidden name="cart_id" id="cart_id" required value="<?php echo @$this->visitor->cart->cart_id ?>" />
                <input type="text" id="insert_token" name="token" value="<?php echo @$this->visitor->cart->token ?>" />
            </form>
        </div>
        <?php
        $jqueryReady .= <<<JS
            // Check CreditCard Fields
            var MainForm = 'form.salespage_form';

            $.validator.addMethod(
                "CCExp",
                function(value, element, params) {
                    $('#month').removeClass('error');
                    $('#year').removeClass('error');
                    var minMonth = new Date().getMonth() + 1;
                    var minYear = new Date().getFullYear();
                    var month_field = $(params.month);
                    var year_field = $(params.year);

                    var month = parseInt(month_field.val(), 10);
                    var year = parseInt(year_field.val(), 10);

                    if(isNaN(month)){
                        return false;
                    }

                    if ((year > minYear) || ((year === minYear) && (month >= minMonth))) {
                        return true;
                    } else {
                        return false;
                    }
                },
                function(){
                    $('#month').addClass('error');
                    $('#year').addClass('error');
                    var msg = "Your Credit Card Expiration date is invalid.";
                    return msg;
                }

            );

            $.validator.setDefaults({
                submitHandler: function(form) {
                    showLoading();
                    f = $(form);
                    $.ajax({
                        type: 'post',
                        url: 'https://ratchet.rebarsecure.com/v1/public/payment_methods',
                        data: f.serialize(),
                        beforeSend: function(){
                            $('.billing_error',f).hide();
                        },
                        success: function(data) {
                            js = $.parseJSON(data);
                            if (js['errors'] != false) {
                                hideLoading();
                                $('.billing_error', f).hide();
                                $.each(js['errors'], function(k, v) {
                                    $('#billing_error_' + k, f).html(v).show()
                                })
                            } else if (js['token']) {
                                $('#insert_token').val(js['token'])
                                $('#hiddencheckout').show();
                                $('#hiddencheckout').submit();
                            }
                        }
                    });
                    return false;
                }
            });

            $(MainForm).validate({
                rules: {
                    first_name: { required: true, minlength: 2 },
                    last_name: { required: true, minlength: 2 },
                    email: { required: true, email: true },
                    number: { required: true, creditcard: true, rangelength: [13,19] },
                    year: { CCExp: { month: "#month", year: "#year" } },
                    cvv: { required: true, rangelength: [3,4] },
                },
                errorPlacement: function(error, element) {
                    // Append error within linked label
                    $( element )
                        .closest( "form" )
                            .find( "label[for='" + element.attr( "id" ) + "']" )
                                .append( error );
                },
                errorElement: "span",
                messages: {
                    first_name: { required: "First name is required", minlength: "First name is too short" },
                    last_name: { required: "Last name is required", minlength: "Last name is too short" },
                    email: { required: "Email required", email: "Email is invalid please check for typo" },
                    number: { required: "CreditCard number required", creditcard: "The CreditCard number is invalid, please check it and try again." },
                    year: { required: "Expiry date required" },
                    cvv: { required: "CVV required" }
                }
            });
            $('input[name=number]', MainForm).validateCreditCard(function (result) {
                var cvv = {visa: 'CVV2:', mastercard: 'CVC2:', discover: 'CID:'};
                if (!(result.card_type != null)) {
                    $('.cards li', MainForm).removeClass('off');
                    $('input[name=number]', MainForm).removeClass('valid');
                    $('.vertical.maestro').slideUp({
                        duration: 200
                    }).animate({
                        opacity: 0
                    }, {
                        queue: false,
                        duration: 200
                    });
                    return;
                }

                $('.cards li').addClass('off');

                if (result.card_type != null) {
                    $('.cards .' + result.card_type.name, MainForm).removeClass('off');
                    $('#cvvTip div').hide();
                    $('#cvvTip #' + result.card_type.name).show();
                    $('#cvvLabel', MainForm).text(cvv[result.card_type.name]);
                    $('input[name=type]', MainForm).val(result.card_type.name);
                    if (result.length_valid & result.luhn_valid) {
                        $('input[name=number]', MainForm).addClass('valid');
                    } else {
                        $('input[name=number]', MainForm).removeClass('valid');
                    }
                }
            }, {accept: [$accepted_cards]});

            $('.cvvTip').magnificPopup({
                type: 'inline',
                fixedContentPos: false,
                fixedBgPos: true,
                overflowY: 'auto',
                closeBtnInside: true,
                preloader: false,
                midClick: true,
                removalDelay: 300,
            });

JS;
        return $jqueryReady;
    }

    function rebar_print_js_include() {
        ?>
        <script src='//rebarsecure.com/static/assets/jquery.js' type='text/javascript'></script>
        <script src='//rebarsecure.com/static/assets/countries-2.0-min.js' type='text/javascript'></script>
        <script src="//rebarsecure.com/static/assets/jquery.validation.js" type="text/javascript"></script>
        <?php
    }

}
