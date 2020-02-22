<?php
// -------------------------------------------------
// SHALACH MANOT ONLINE PAYMENT WRAPPER
// for ShulCloud
//
// Copyright (c) 2020 Daniel M. Cohn
// -------------------------------------------------

include("shul-settings.php");

$url = $cc_checkout.(array_key_exists("due", $_REQUEST) ? $_REQUEST["due"] : "0");
$pmt = array_key_exists("method", $_REQUEST) ? $_REQUEST["method"] : "credit";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<title>Anshai Torah Shalach Manot Payment</title>
		<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />
		<meta name="author" content="Dan Cohn" />
		<meta name="description" content="Purim Shalach Manot payment form for Congregation Anshai Torah, Plano, TX" />
		<link rel="shortcut icon" href="./anshai.ico" />

		<style type="text/css"><!--
			html {height:90%;}
			body {font-family:Georgia,Times,serif;font-size:12pt;background-color:#f0f0a0;height:100%;}
			a:link,a:visited {color:#0000f0;}
			button,input[type="button"],input[type="submit"] {font-family:Georgia,Times,serif;font-size:12pt;border:solid 2px #808080;background-color:#f0f0f0;margin:0px;padding:2px 6px;cursor:pointer;}
			h1 {font-family:Tahoma,Geneva,sans-serif;text-align:center;font-size:15pt;background-color:#8080c0;padding:6pt;min-width:600px;border-width:2px;border-style:solid none;border-color:rgb(93,58,114);}
			h2 {font-size:14pt;margin-top:7pt;color:#f00000;}
			h3 {font-size:13pt;font-weight:bold;margin-top:0pt;margin-bottom:6pt;}
			li {
				margin-bottom: 18px;
			}
			div.left {
				width: 30%;
				height: 95%;
				float: left;
			}
			div.right {
				width: 70%;
				height: 95%;
				float: left;
				text-align: right;
			}
			div.instrux {
				padding-left: 8px;
				padding-right: 12px;
				height: 100%;
				overflow-y: auto;
			}
			iframe {
				width: 98%;
				height: 100%;
				border: 2px solid #808080;
			}
		--></style>

	</head>
	<body>
		<h1>Congregation Anshai Torah &ndash; Shalach Manot Payment</h1>
		<div class='left'>
			<div class='instrux'>
				<h2>Follow these six steps to complete online payment:</h2>
				<ol>
					<li><b>Login to ShulCloud &gt;&gt;</b><br/>
						<br/>
						Scroll down (if necessary), enter the Email and Password associated with your account, and click Sign In.<br/>
						<br/>
						If you don&apos;t remember your password, enter your name, email, and phone to pay as a visitor.<br/>
						<br/>
						<i>Skip this step if you&apos;re already logged in.</i>
					</li>
					<li><b>Confirm amount &amp; click Continue to Payment &gt;&gt;</b><br/>
						Do not touch any other fields, including Dedicate. (Dedications are not appropriate for Shalach Manot.)
					</li>
					<li> 
						<?
							if ($pmt == "credit") {
								echo '<b>Confirm selected Payment Method &gt;&gt;</b><br/>Method should be "Pay by Credit Card" or "New Pay by Credit Card."';
							} else {
								echo '<b>Select Payment Method &gt;&gt;</b><br/>Choose "echeck" or "New Pay by eCheck."';
							}
						?>
					</li>
					<li><b>Click Confirm and Continue &gt;&gt;</b><br/>
						You may need to scroll down to find the button.
					</li>
					<li><b>Follow any other instructions &gt;&gt;</b><br/>
						No further action required if using an existing credit card or bank account.
					</li>
					<li><b>Close this window or tab</b><br/>
						Or click <a href="<?= $cc_account ?>" title="View My Account on ShulCloud">here</a> to go to your ShulCloud account.
					</li>
				</ol>
			</div>
		</div>
		<div class='right'>
			<iframe src='<?= $url ?>'></iframe>
		</div>
	</body>
</html>
