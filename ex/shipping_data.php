<?
if(!empty($_POST))
{
		require_once('rebar.include.php');
		rebar_update_lead_data($_POST);
		debug_redirect('billing_data.php');
		die();
}
require_once('header.php');
?>
<form class="leadform" method="post" action="shipping_data.php" >
					<input type=text class="name" name="first_name" value="First Name"><br /><br />

	 <input type=text class="name" name="last_name" value="Last Name"><br /><br />

<input type=text class="name" name="email" value="email"><br /><br />


<input type=text class="name" name="address1" value="Address1"><br /><br />

<input type=text class="name" name="city" value="City"><br /><br />

<input type=text class="name" name="state" value="State"><br /><br />

<input type=text class="name" name="country" value="Country">



<input type="submit" width="170" height="51" class="right form-submit-button">

</form>
<?
require_once('footer.php');
?>