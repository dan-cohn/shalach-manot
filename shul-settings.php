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

// MySQL globals
$db_server   = "localhost";
$db_database = "catsm";
include("credentials-local.php");

// fake mail function - write to files
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


?>