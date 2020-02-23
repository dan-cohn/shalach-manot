<?php

$page_title = "Congregation ABC &ndash; Shalach Manot Order Form";

// e-mail & web address globals
$mail_from        = "Your Shul <purim@shalachmanot.org>";
$mail_help        = "webmaster@shalachmanot.org";
$mail_orders      = "webmaster@shalachmanot.org";
$mail_bcc_list    = "";
$mail_alerts      = "webmaster@shalachmanot.org";
$mail_drivers     = "deliveries@shalachmanot.org";
$mail_benefactors = "benefactors@shalachmanot.org";
$mail_changes     = "accounting@shalachmanot.org";

$mail_recipients  = true;

$web_addr         = "http://localhost:8080/shalachmanot";
$cc_checkout      = "";
$cc_account       = "";

// prices
//
// important: if these change, it's also necessary to change the Prices table in the DB
//            before running reciprocity
$price_basket     =   7.50;
$price_extra      =  12.50;
$price_nm_local   =  12.50;  // not used right now
$price_nm_ship    =  25.00;
$price_benefactor = 500.00;

// MySQL globals
$db_server   = "localhost";
$db_database = "catsm";

// DB credentials; contains the following:
// $db_user = ""
// $db_pwd  = ""
include("credentials-local.php");

// other globals
$alert_prefix = "SM ";
$db_connect_err = "Unable to connect to database. If this problem persists, please contact a synagogue representative.";

// fake mail function - write to files for local testing
function send_email($to, $subject, $body, $headers) {
  $prefix = "mail/".date("Y-m-d.His").".".hash("crc32",$body,false);
  $suffix = ".txt";
  if (!file_exists("mail")) mkdir("mail");
  if (stripos($headers, "text/html")) $suffix=".html";
  $filename = $prefix."-msg".$suffix;
  $filename_meta = $prefix."-meta.txt";
  file_put_contents($filename, $body);
  $metadata = "To: ".$to."\r\nSubject: ".$subject."\r\n\r\n".$headers;
  file_put_contents($filename_meta, $metadata);
  return true;
}

// real mail function
//
// function send_email($to, $subject, $body, $headers) {
//   return mail($to, $subject, $body, $headers);
// }

?>
