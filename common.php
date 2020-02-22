<?php
// --------------------------------------
// Common PHP constants & functions
// --------------------------------------
//
// Copyright (c) 2009-2020 Daniel M. Cohn

// price globals
//
// important: if these change, it's also necessary to change the Prices table in the DB
//            before running reciprocity
$price_basket     =   7.50;
$price_extra      =  12.50;
$price_nm_local   =  12.50;  // not used right now
$price_nm_ship    =  25.00;
$price_benefactor = 500.00;

// other globals
$alert_prefix = "ATSM ";  // override in other pages if necessary
$db_connect_err = "Unable to connect to database. If this problem persists, please contact Dan Cohn at 214-405-3044.";

// connectDB: connect to the SM database
function connectDB() {
  global $db_server, $db_database, $db_user, $db_pwd, $db_link;
  $db_link = mysqli_connect($db_server, $db_user, $db_pwd);
  if ($db_link) {
    if (!mysqli_select_db($db_link, $db_database)) {
      trigger_error("Could not select database '".$db_user);
      return false;
    }
  } else {
    trigger_error("Could not connect to MySQL: ". mysqli_connect_error());
    return false;
  }
  return true;
}

// boolify: helper function to convert an SQL bool into a Javascript bool as a string
function boolify($field) {
  return ($field=="1")?"true":"false";
}

// php_to_js_array: helper function to convert an array to a string representing a Javascript array
function php_to_js_array($a) {
  $str = "[";
  $first = true;
  foreach ($a as $item) {
    if ($first) $first=false;
    else $str .= ",";
    
    $str .= "'".$item."'";
  }
  $str .= "]";
  return $str;
}

// getNames: return a sorted array of names matching a particular membership/staff status; array index is NameID
function getNames($status, $staff=FALSE) {
  global $db_link;
  $names = array();

  if ($staff) {
    $rows = mysqli_query($db_link,"SELECT NameID,LastName,FirstNames FROM People WHERE Status='".$status."' AND Staff=1 ORDER BY LastName ASC, FirstNames ASC");
  } else {
    $rows = mysqli_query($db_link,"SELECT NameID,LastName,FirstNames FROM People WHERE Status='".$status."' ORDER BY LastName ASC, FirstNames ASC");
  }

  while ($row = mysqli_fetch_assoc($rows)) {
    $names[$row['NameID']]=$row['LastName'].", ".$row['FirstNames'];
  }

  return $names;
}

// sendAlert: send an SMS notification via e-mail
function sendAlert($msg) {
  global $mail_alerts, $alert_prefix;
  if ($mail_alerts) {
    mail($mail_alerts, "", $alert_prefix.$msg);
  }
}

// error_report: log all PHP errors to disk
function error_report($error_level, $error_message, $error_file, $error_line) {
  global $error_log_name;

  $logdata="PHP ERROR DETECTED on ".date(DATE_RFC822)." from ".$_SERVER["REMOTE_ADDR"]."\r\n";
  $logdata.="level: ".$error_level."\r\n";
  $logdata.="msg: ".$error_message."\r\n";
  $logdata.="line: ".$error_line."\r\n\r\n";

  file_put_contents($error_log_name, $logdata, FILE_APPEND);

  sendAlert("PHP error: ".$error_message);
}

set_error_handler("error_report");

// exception_report: log all PHP exceptions to disk
function exception_report($e) {
  global $error_log_name;

  $logdata="PHP EXCEPTION DETECTED on ".date(DATE_RFC822)." from ".$_SERVER["REMOTE_ADDR"]."\r\n";
  $logdata.="msg: ".$e->getMessage()."\r\n";
  $logdata.="line: ".$e->getLine()."\r\n\r\n";

  file_put_contents($error_log_name, $logdata, FILE_APPEND);

  echo "Sorry, an error has occurred on this page.\r\n\r\nMessage: ".$e->getMessage()."\r\n";

  sendAlert("PHP exception: ".$e->getMessage());

  die();
}

set_exception_handler("exception_report");

// report_db_error: log MySQL errors to disk and also alert
function report_db_error($sql) {
  global $db_link, $error_log_name;
  
  $logdata="MYSQL ERROR DETECTED on ".date(DATE_RFC822)." from ".$_SERVER["REMOTE_ADDR"]."\r\n";
  $logdata.="msg: ".mysqli_error($db_link)."\r\n";
  $logdata.="sql: ".$sql."\r\n\r\n";
  
  file_put_contents($error_log_name, $logdata, FILE_APPEND);
  
  sendAlert("MYSQL error: ".mysqli_error($db_link));
}

// js_error_report: log JavaScript errors received from the client
function js_error_report($error_message) {
  global $error_log_name;

  $logdata="JAVASCRIPT ERROR DETECTED on ".date(DATE_RFC822)." from ".$_SERVER["REMOTE_ADDR"]."\r\n";
  $logdata.="client: ".$_SERVER["HTTP_USER_AGENT"]."\r\n";
  $logdata.="msg: ".$error_message."\r\n\r\n";

  file_put_contents($error_log_name, $logdata, FILE_APPEND);

  sendAlert("JS error: ".$error_message);
}

?>
