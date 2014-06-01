<?
if(!empty($_POST) && isset($_POST['token']))
{
		require_once('rebar.include.php');
		var_dump($_POST);
		$obj = rebar_authorize_cart(@$_POST['token']);
		debug_redirect('upsells.php');
		die();
}
require_once('header.php');
?>
			<h2> Provide Your Credit Card Details Below </h2> <br/>
					 <form action='//rebarsecure.com/v1/public/payment_methods' class="form-horizontal salespage_form ratchet"  method="post" class="ratchet-vault">
								 <!-- Brand Environment (usually hidden): --><input name="environment" vclass="name" alue="test" type="hidden">
								 <input type=text name="first_name" class="name" value="Leeward"><br /><br />
								 <input type=text name="last_name" class="name" value="Bound"><br /><br />
								 <input type=text name="email" class="name" value="l@lwb.co"><br /><br />
								 <input type=text class="name" name="number" value="378282246310005"><br /><br />
								 <input type=text class="name" name="month" value="8"><br /><br />
								 <input type=text name="year" class="name" value="2017"> <br /><br />
								 <input type=text name="cvv" class="name" value="999"> <br /><br />
								 <input type="submit" id="vaultthis" name="vault" value="BUY NOW">
				 </form>
 <div id="checkoutform" style="display:none">
    <form id="hiddencheckout"action="billing_data.php" method="post">
       <input type="text" id="insert_token" name="token"></input>
       <input type="submit" name="vault" value="Check Out">
    </form>
 </div>
<script type="text/javascript" src="./jquery.js"></script>
<script>

// Use this to vault ratchet details
$(function() {
  $('form.ratchet').each(function() {
    f = $(this);
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
                $('#hiddencheckout').submit();
            }

          }
        });
      return false;
    });
  });
});
</script>
<?
require_once('footer.php');
?>