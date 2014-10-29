<?php

class rebar {

    public $lead = null;
    public $cart = null;
    public $order = null;
    public $visitor = null;
    public $domain = null;
    public $call_count = 0;

    function __construct() {
        global $config;

        if ($config['rebar']['debug'])
            ini_set('display_errors', 1);

        // Set cart field defaults
        $this->cart = new stdClass();
        $this->visitor = new stdClass();
        $this->domain = $_SERVER['HTTP_HOST'];
    }

    function rebar_cart_size() {
        $size = 0;
        if ($this->cart->items > 0) {
            foreach ($this->cart->items as $item) {
                $size = $size + $item->quantity;
            }
        }
        return (int) $size;
    }

    function rebar_get_cached_val($name) {
        $var = "_rebar_${name}";
        global $$var;
        if (empty($$var)) {
            $$var = @$_COOKIE['rebar_' . $name];
            $this->debug_print("<br />Reading $var from cookies: " . $$var . "<br /><hr />");
        }
        return $$var;
    }

    function rebar_set_cached_val($name, $val, $ttl = 86400) {
        $this->debug_print("<br />Setting COOKIE: $name = $val (ttl: $ttl, path: /, domain: .{$this->domain})<br /><hr />");
        if (!empty($val))
            setcookie("rebar_${name}", $val, time() + $ttl, '/', '.' . $this->domain);
    }

    function rebar_track_visitor() {
        if (!empty($this->cart->visitor_id))
            return $this->cart->visitor_id;
        $this->cart->visitor_id = $this->rebar_get_cached_val('visitor_id');
        $pass_headers = array('HTTP_HOST', 'PATH_INFO', 'HTTP_USER_AGENT', 'REMOTE_ADDR',
                'HTTP_REFERER', 'HTTP_X_FORWARDED_FOR', 'QUERY_STRING', 'REQUEST_URI');
        $pass_gets = array_keys($_GET);
        $info = array();
        $id = empty($this->cart->visitor_id) ? '' : $this->cart->visitor_id;
        foreach ($pass_headers as $h) {
            if (isset($_SERVER[$h])) {
                $info[$h] = $_SERVER[$h];
            } else {
                $info[$h] = '';
            }
        }

        $info['brand_id'] = $this->cart->brand_id;
        $info['product_id'] = $this->cart->product_id;

        // Lets send off the affiliate_id and sub_id so that we can track the visitor
        $info['affiliate_id'] = $this->cart->aff_id;
        $info['sub_id'] = $this->cart->sub_id;

        $i = base64_encode(json_encode($info));

        // There is a lot of information in the visitor object. I think once the tracking is fired, you could potentially use that call to set all the values locally.
        $obj = $this->rebar_post("crm/track_visit?_id=$id&i=$i");
        $this->cart->visitor_id = $obj->visitor_id;
        return $this->rebar_set_cached_val('visitor_id', $this->cart->visitor_id, 30 * 86400);
    }

    function rebar_ensure_cart_exists_or_make_one() {
        $this->rebar_ensure_lead_exists_or_make_one();
        if (empty($this->cart->cart_id))
            $this->cart->cart_id = $this->rebar_get_cached_val('cart_id');
        if (empty($this->cart->cart_id)) {
            $this->cart = $this->rebar_post("crm/create_cart", array('lead_id' => $this->cart->lead_id));
            $this->rebar_set_cached_val('cart_id', $this->cart->cart_id, 86400);
        }
    }

    function rebar_ensure_lead_exists_or_make_one() {
        if (empty($this->cart->lead_id))
            $this->cart->lead_id = $this->rebar_get_cached_val('lead_id');
        if (empty($this->cart->lead_id)) {
            $obj = $this->rebar_post("crm/create_lead", $this->cart);
            $this->cart->lead_id = $obj->lead->lead_id;
            $this->rebar_set_cached_val('lead_id', $this->cart->lead_id, 86400 * 365);
        }
    }

    function rebar_update_lead_data($fields) {
        $this->rebar_ensure_lead_exists_or_make_one();
        $lead = $this->rebar_post('crm/create_lead', array(
                'lead_id' => $this->cart->lead_id,
                'brand_id' => $this->cart->brand_id,
                'product_id' => $this->cart->product_id,
                'visitor_id' => $this->cart->visitor_id,
                'first_name' => $fields->firstName,
                'last_name' => $fields->lastName,
                'email' => $fields->email,
                'address_one' => $fields->address1,
                'address_two' => $fields->address2,
                'city' => $fields->city,
                'region' => $fields->state,
                'country' => $fields->country,
                'zipcode' => $fields->zip,
                'affiliate_id' => $fields->subid,
                'sub_id' => $fields->lp
        ));

        $this->cart->lead_id = $lead->lead_id;
        $this->rebar_set_cached_val('lead_id', $this->cart->lead_id, 86400 * 365);
    }

    function rebar_get_cart_info($cart_id = '') {
        if (empty($this->cart->cart_id)) {
            if (!empty($this->cart))
                return $this->cart;
            $this->cart = $this->rebar_post('crm/cart_info', array());
        } else {
            $this->cart = $this->rebar_post('crm/cart_info', array('cart_id' => $this->cart->cart_id));
        }
        $this->rebar_update_cart_cookies_from_obj();
        return $this->cart;
    }

    function _rebar_cart_is_locked() {
        return (isset($this->cart->error) && ($this->cart->error == 'No such cart' ||
            $this->cart->error == 'Invalid cart_id') || $this->cart->payment_success);
    }

    function rebar_update_cart_cookies_from_obj() {
        if (isset($this->cart->items))
            $this->rebar_set_cached_val('cart_count', $this->rebar_cart_size());
        if ($this->_rebar_cart_is_locked($this->cart)) {
            $this->cart = $this->rebar_post("crm/create_cart", array('lead_id' => $this->cart->lead_id));
            $this->rebar_set_cached_val('cart_id', $this->cart->cart_id);
            $this->rebar_set_cached_val('lead_id', $this->cart->lead_id);
            return false;
        }
        $this->rebar_set_cached_val('cart_id', $this->cart->cart_id);
        $this->rebar_set_cached_val('lead_id', $this->cart->lead_id);
        return true;
    }

    function rebar_authorize_cart($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->cart = $this->rebar_post('crm/purchase_cart', array(
                'token' => $token,
                'authorize' => 'true'
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_authorize_dollar($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->cart = $this->rebar_post('crm/purchase_cart', array(
                'token' => $token,
                'authorize' => 'true',
                'amount' => 100
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_autofinalize_cart($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->cart = $this->rebar_post('crm/purchase_cart', array(
                'token' => $token,
                'autofinalize' => 'true'
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_remove_from_cart($product_id) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $obj = $this->rebar_post('crm/remove_from_cart', array(
                'product_id' => $product_id
        ));
    }

    function rebar_update_cart_quantities($quantities) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $obj = $this->rebar_post('crm/update_cart_quantities', $quantities);
    }

    function rebar_add_to_cart($product_id, $first_attempt = true) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->cart = $this->rebar_post('crm/add_to_cart', array(
                'product_id' => $product_id
        ));

        if ($this->_rebar_cart_is_locked() && $first_attempt) {
            $this->rebar_update_cart_cookies_from_obj();
            $this->cart = $this->rebar_add_to_cart($product_id, false);
        }
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_add_subscription_to_cart($product_id) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->cart = $this->rebar_post('crm/add_to_cart', array(
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
            return new stdClass();
        $response = $this->post_data($config['rebar']['endpoint'] . $stub, $this->rebar_params($fields));
        // This should go into $this->response
        $obj = @json_decode($response);
        return $obj;
    }

    function rebar_params($extra_params) {
        global $config;
        return array_merge(array(
                'cart_id' => $this->rebar_get_cached_val('cart_id'),
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
    }

    function rebar_purchase_cart($token) {
        $this->rebar_ensure_cart_exists_or_make_one();
        $this->cart = $this->rebar_post('crm/purchase_cart', array(
                'token' => $token
        ));
        $this->rebar_update_cart_cookies_from_obj();
        return true;
    }

    function rebar_output_shipping_data_form($action = '') {
        $this->rebar_print_js_include();
        ?>
        <form id="validatedLeadForm" class="pure-form" action="<?php echo $action ?>" method="post">
            <?php
            if (!empty($this->cart->lead)) {
                ?>
                <input type="radio" class="new_or_old_lead" name="lead_id" id="new_lead_radio" value="_new"><label for="new_lead_radio">New shipping details</label>
                - or -
                <input type="radio" checked class="new_or_old_lead" name="lead_id" id="old_lead_radio" value="<?php echo $this->cart->lead_id ?>"><label for="old_lead_radio">Re-use these details below...</label>
                <br /><br />
                <script>
                    $(function () {
                        f = $('#validatedLeadForm');
                        $('input.new_or_old_lead[type=radio]', f).change(function () {
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
            <input type=text class="name new_lead" name="first_name" required value="<?php echo @$this->cart->lead->first_name ?>" placeholder="First Name"><br /><br />
            <input type=text class="name new_lead" name="last_name" required value="<?php echo @$this->cart->lead->last_name ?>" placeholder="Last Name"><br /><br />
            <input type="email" class="email new_lead" name="email" required value="<?php echo @$this->cart->lead->email ?>" placeholder="Email"><br /><br />
            <input type="tel" class="phone new_lead" name="phone" required value="<?php echo @$this->cart->lead->phone ?>" placeholder="Phone"><br /><br />
            <input type=text class="name new_lead" name="address_one" required value="<?php echo @$this->cart->lead->address_one ?>" placeholder="Address Line 1"><br /><br />
            <input type=text class="name new_lead" name="address_two" value="<?php echo @$this->cart->lead->address_two ?>" placeholder="Address Line 2"><br /><br />
            <input type=text class="name new_lead" name="city" required value="<?php echo @$this->cart->lead->city ?>" placeholder="City"><br /><br />
            <input type=text class="name new_lead" name="region" value="<?php echo @$this->cart->lead->region ?>" placeholder="State or Region"><br /><br />
            <input type=text class="name new_lead" name="country" required value="<?php echo @$this->cart->lead->country ?>" placeholder="Country"><br /><br />
            <input type="image" width="460" src="theme/images/continue.png" class="btn btn-success btn-lg btn-block btn-continue" value="Next" >
        </form>
        <script>
            $(function () {
                $("#validatedLeadForm").validate();
                var MainForm = '#validatedLeadForm';
                if ($('select[name=country]', MainForm).length > 0) {
                    var selShipCountry = '<?php echo @$this->cart->lead->country ?>';
                    var selShipState = '<?php echo @$this->cart->lead->region ?>';
                    if (typeof (geoip_country_code) === 'function') {
                        if (selShipCountry == '')
                            selShipCountry = geoip_country_code();
                        if (selShipState == '')
                            selShipState = geoip_region();
                    }
                    getStates(MainForm, '', selShipCountry, selShipState);
                    // Chain the state field
                    $('select[name=country]', MainForm).change(function () {
                        getStates(MainForm, '');
                    });
                }
            })
        </script>
        <?php
    }

    function rebar_output_billing_data_form($action = '') {
        global $config;
        ?>
        <h2> Provide Your Credit Card Details Below </h2> <br/>
        <?php
        if ($config['rebar']['debug'] == 1) {
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
            </script>
        <?php } ?>
        <?php
        if (!empty($this->cart->errors)) {
            ?>
            <ul class="errorlist">
                <?php
                foreach ($this->cart->errors as $i => $e) {
                    echo "<li>" . $e . "</li>";
                }
                ?>
            </ul>
            <?php
        }
        ?>

        <form action='https://ratchet.rebarsecure.com/v1/public/payment_methods' class="form-horizontal salespage_form ratchet"  method="post" >
            <!-- Brand Environment (usually hidden): --><input name="environment" class="name" value="<?php echo $config['rebar']['enviroment'] ?>" type="hidden">
            <div style="color:red" class="billing_error" id="billing_error_first_name"></div>
            <input type=text placeholder='First Name' name="first_name" class="name form-control" value="<?php echo @$this->cart->lead->first_name ?>"><br /><br />
            <div style="color:red" class="billing_error" id="billing_error_last_name"></div>
            <input type=text placeholder='Last Name' name="last_name" class="name form-control" value="<?php echo @$this->cart->lead->last_name ?>"><br /><br />
            <div style="color:red" class="billing_error" id="billing_error_email"></div>
            <input type=text placeholder='Email' name="email" class="email form-control" value="<?php echo @$this->cart->lead->email ?>"><br /><br />
            <div style="color:red" class="billing_error" id="billing_error_number"></div>
            <input type="text" placeholder="Card Number" class="name form-control" name="number" maxlength="19" value="<?php echo @$this->cart->lead->number ?>"><br /><br />
            <div style="color:red" class="billing_error" id="billing_error_month"></div>
            <div style="color:red" class="billing_error" id="billing_error_year"></div>
            <select name="month" id="month" class="name form-control">
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
            <select name="year" id="year" class="name form-control">
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
            <div style="color:red" class="billing_error" id="billing_error_cvv"></div>
            <input type=text placeholder='CVV' name="cvv" class="name form-control" value="<?php echo @$this->cart->lead->cvv ?>"> <br /><br />
            <a href="#cvvTip" class="cvvTip">What's this?</a><br /><br />
            <input type="image" width="460" src="theme/images/purchase.png" class="btn btn-success btn-lg btn-block btn-purchase" id="vaultthis" name="vault" value="BUY NOW">
        </form>
        <div id="checkoutform" style="display:none">
            <form id="hiddencheckout" action="<?php echo $action ?>" method="post">
                <input type="text" id="insert_token" name="token"></input>
            </form>
        </div>
        <script>
            // Check CreditCard Fields
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
            }, {accept: [<?php echo $accepted_cards ?>]});

            $('.cvvTip').magnificPopup({
                type: 'inline',
                fixedContentPos: false,
                fixedBgPos: true,
                overflowY: 'auto',
                closeBtnInside: true,
                preloader: false,
                midClick: true,
                removalDelay: 300,
                mainClass: 'my-mfp-zoom-in'
            });
        </script>
        <?php
    }

    function rebar_print_js_include() {
        ?>
        <script src='//rebarsecure.com/static/assets/jquery.js' type='text/javascript'></script>
        <script src='//rebarsecure.com/static/assets/countries-2.0-min.js' type='text/javascript'></script>
        <script src="//rebarsecure.com/static/assets/jquery.validation.js" type="text/javascript"></script>
        <?php
    }

}
