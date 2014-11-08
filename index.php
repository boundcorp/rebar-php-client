<?php
session_start();

$lp = 'landing_page_here';
$aff_id = filter_var($_GET['aff_id'], FILTER_SANITIZE_STRING);
$sub_id = filter_var($_GET['sub_id'], FILTER_SANITIZE_STRING);

$config['base_classes'] = '../app/classes';
$config['base_config'] = '../app/config';
include_once($config['base_config'] . '/rebar.php');

$rebar = new rebar();

if(count($_POST) > 0){
    $rebar->rebar_update_lead_data($_POST);
} else {
    $rebar->cart->brand_id = '5371cbcd9688a622e476ab5bb7cd9832de680353';
    $rebar->cart->product_id = '34d3e72e3d57d839b7ae0291c5e62a546d032a85';
    $rebar->cart->affiliate_id = $aff_id;
    $rebar->cart->sub_id = $sub_id;
    $rebar->rebar_track_visitor();
}
?>
<!DOCTYPE html>
<html xmlns="//www.w3.org/1999/xhtml" lang="en">
    <head>
        <title>Rebar</title>
        <!--[if IE]><meta http-equiv='X-UA-Compatible' content='IE=edge,chrome=1'/><![endif]-->
        <meta charset="utf-8">
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
        <link href="css/magnific-popup.1.0.1.css" rel="stylesheet" type="text/css">
    </head>
    <body>
        <div id="page">
            <form action="<?php echo htmlentities($_SERVER['PHP_SELF']) ?>" method="POST">
                <input type="hidden" name="lp" value="<?php echo $lp ?>" />
                <input type="hidden" name="aff_id" value="<?php echo $aff_id ?>" />
                <input type="hidden" name="sub_id" value="<?php echo $sub_id ?>" />
                <input type="hidden" name="brand_id" value="<?php echo $rebar->cart->brand_id ?>" />
                <input type="hidden" name="product_id" value="<?php echo $rebar->cart->product_id ?>" />
                <input type="hidden" name="visitor_id" value="<?php echo $rebar->cart->visitor_id ?>" />
                <input type="hidden" name="lead_id" value="<?php echo $rebar->cart->lead_id ?>" />
                <input type="hidden" name="response" value="false" />

                <div class="step show">
                    <div class="clearfix">
                        <label>First Name</label>
                        <div class="field"><input type="text" value="" id="firstName" name="firstName" placeholder="ex. John" class="Input1 full_name_val"></div>
                    </div>
                    <div class="clearfix">
                        <label>Last Name</label>
                        <div class="field"><input type="text" value="" id="lastName" name="lastName" placeholder="ex. Doe" class="Input1 full_name_val"></div>
                    </div>
                    <div class="clearfix">
                        <label>Email Address</label>
                        <div class="field"><input type="email" value="" id="email" name="email" placeholder="yourname@domain.com" class="Input2 email_val"></div>
                    </div>
                    <div class="clearfix">
                        <label>Phone Number</label>
                        <div class="field"><input type="tel" maxlength="12" value="" id="phone" name="phone" placeholder="XXX-XXX-XXXX" class="Input3 phone1" /></div>
                    </div>
                    <div class="clearfix">
                        <label>Zip Code</label>
                        <div class="field"><input type="tel" id="zip" name="zip" value="" placeholder="ZIP Code" class="zipcode_val"></div>
                    </div>
                    <div class="clearfix">
                        <label>State</label>
                        <div class="field">
                            <select id="state" name="state">
                                <option value="AL">Alabama</option>
                                <option value="AK">Alaska</option>
                                <option value="AZ">Arizona</option>
                                <option value="AR">Arkansas</option>
                                <option value="CA">California</option>
                                <option value="CO">Colorado</option>
                                <option value="CT">Connecticut</option>
                                <option value="DE">Delaware</option>
                                <option value="DC">District Of Columbia</option>
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
                            </select>
                        </div>
                    </div>
                    <input name="bd_submit_form" class="bd_submit_form" type="submit" value="Get Qualification Status" />
                </div>
                <!--step2 ends here-->

                <div id="ic-loading" class="ic-loading hidden">
                    <img src="images/loadingbar.gif" alt="Loading" /><br /><br />
                    Processing, one moment please...
                </div>

            </form>
        </div>

        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
        <script src="js/jquery.magnific-popup.js"></script>

        <script>
            $(document).ready(function () {
                $("form").submit(function (e) {
                    $('.step.show').hide();
                    $('.ic-loading').show();
                });
            });
        </script>
    </body>
</html>