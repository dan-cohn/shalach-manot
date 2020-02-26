<?php
// -------------------------------------------------
// SHALACH MANOT ADMIN INTERFACE
// for Congregation Anshai Torah
// -------------------------------------------------
//
// Copyright (c) 2009-2020 Daniel M. Cohn
//
// -------------------------------------------------

include("shul-settings.php");
include("common.php");

// filename globals
$usage_log_name = "cat_sm_admin_usage_log.txt";
$error_log_name = "cat_sm_admin_error_log.txt";

// prefix for SMS error alerts
$alert_prefix = "ATSM ADMIN ";

// logged in user (only used if user already logged in when page first loaded)
$admin_user = "";
$admin_name = "";
$admin_token = "";

// autoLogin: user has token, so check it to see who's already logged in
function autoLogin() {
    global $admin_user, $admin_name, $admin_token, $db_link;
    
    if (connectDB()) {
        $result = mysqli_query($db_link,"SELECT Email,Name FROM Admins WHERE Token='".$_COOKIE["admin_token"]."'");
        if ($result) {
            $row = mysqli_fetch_array($result);
            if ($row) {
                $admin_user = $row[0];
                $admin_name = $row[1];
                $admin_token = $_COOKIE["admin_token"];
            }
        }
    }
}

// checkAdminToken: lookup admin user (by email) and ensure cookie is set with proper token
function checkAdminToken() {
  global $db_link;
  if (array_key_exists("id", $_REQUEST) && isset($_COOKIE["admin_token"])) {
    $row = mysqli_fetch_array(mysqli_query($db_link,"SELECT Token FROM Admins WHERE Email='".$_REQUEST["id"]."'"));
    if ($row && $row[0]) {
      if (!strcmp($row[0], $_COOKIE["admin_token"])) return true;
    }
  }
  return false;
}

// action_login: handle AJAX login request and return true for a successful login
function action_login() {
  global $db_link;
  if ($_REQUEST["magic_key"] != "shalom31528") {
    $logdata = date(DATE_RFC822)." LOGIN_ATTEMPT_IGNORED";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"]."\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    return false;
  } else {
    if (connectDB()) {
    
      if (array_key_exists("user", $_REQUEST) && array_key_exists("pwd", $_REQUEST)) {

        $result = mysqli_query($db_link,"SELECT Name,Passhash,Token FROM Admins WHERE Email='".$_REQUEST["user"]."'");

        if ($result) {
      
          $row = mysqli_fetch_assoc($result);
      
          if ($row) {
            if (strcmp(hash('sha256',$_REQUEST["pwd"]), $row["Passhash"]) == 0) {
              $token = $row["Token"];
              if ($token == "") {
                  $token = md5($_REQUEST["user"]."+".$_REQUEST["pwd"]);
                  mysqli_query($db_link,"UPDATE Admins SET Token='".$token."' WHERE Email='".$_REQUEST["user"]."'");
              }
              echo '{"rc":true,"name":"'.addcslashes($row["Name"],'"').'","token":"'.$token.'"}';
              return true;
            }
          }
        }
      }
    }
    echo '{"rc":false}';
    return false;
  }
}

// action_summary: handle AJAX request to download order summary list
function action_summary() {
  global $db_link;
  if (!connectDB()) return;
    
  // security check
  if (!checkAdminToken()) {
      echo '{"rc":false}';
      return;
  }
  
  $result = mysqli_query($db_link,"SELECT OrderNumber,AllMembers,Reciprocity,ExtraDonation,Subtotal,PmtType,TotalPaid,PriceOverride,PmtConfirmed,Notes,Driver,".
    "p.LastName,p.FirstNames,p.Email,p.PhoneNumber,p.Status FROM Orders INNER JOIN People AS p ON Orders.NameID=p.NameID WHERE Year=".date("Y").
    " ORDER BY OrderNumber ASC");

  if ($result) {
    echo '{"rc":true,"orders":[';  // start of JSON output
    $comma = 0;

    while ($row = mysqli_fetch_assoc($result)) {
      if ($comma) {
        echo ',{';
      } else {
        echo '{';
        $comma = 1;
      }
      
      $subtotal = $donation = $paid = 0;
      
      if ($row["ExtraDonation"]) $donation = (float) $row["ExtraDonation"];
      if ($row["TotalPaid"]) $paid = (float) $row["TotalPaid"];
      if ($row["Subtotal"]) $subtotal = (float) $row["Subtotal"];
      
      echo '"num":'.$row["OrderNumber"];
      echo ',"last":"'.addcslashes($row["LastName"],'"').'"';
      echo ',"first":"'.addcslashes($row["FirstNames"],'"').'"';
      echo ',"email":"'.addcslashes($row["Email"],'"').'"';
      echo ',"phone":"'.addcslashes($row["PhoneNumber"],'"').'"';
      echo ',"benefactor":'.boolify($row["AllMembers"]);
      echo ',"reciprocity":'.boolify($row["Reciprocity"]);
      echo ',"subtotal":'.$subtotal;
      echo ',"donation":'.$donation;
      echo ',"paid":'.$paid;
      echo ',"pmt_type":"'.$row["PmtType"].'"';
	  echo ',"pmt_rcvd":'.boolify($row["PmtConfirmed"]);
      echo ',"notes":"'.preg_replace('/(\r\n?)|(\n\r?)/',' / ',addcslashes($row["Notes"],'"\\')).'"';
      echo ',"driver":'.boolify($row["Driver"]);
      echo ',"status":"'.$row["Status"].'"';
      echo '}';
    }
    
    echo ']}';  // end of array & output
  } else {
    echo '{"rc":true,"orders":[]}';
  }
}

// action_update_order: handle AJAX request to update one or more fields of an order; returns false if a failure occurs
function action_update_order() {
  global $db_link;
  if (!connectDB()) return false;

    if (array_key_exists("num", $_REQUEST) && $_REQUEST["num"]) {
    $ref = -1;
    $num = $_REQUEST["num"];
    $sql = "UPDATE Orders SET LastUpdated=CURRENT_TIMESTAMP";

    if (array_key_exists("ref", $_REQUEST)) $ref = $_REQUEST["ref"];

    if (array_key_exists("paid", $_REQUEST)) {
      $sql .= ",TotalPaid=".$_REQUEST["paid"];
    }
    
    if (array_key_exists("pmt_type", $_REQUEST)) {
      $sql .= ",PmtType='".$_REQUEST["pmt_type"]."'";
    }

    if (array_key_exists("pmt_rcvd", $_REQUEST)) {
      $sql .= ",PmtConfirmed=".($_REQUEST["pmt_rcvd"]=="true"?"1":"0");
    }

    if (array_key_exists("notes", $_REQUEST)) {
      // note: for some reason, this doesn't work correctly if mysqli_real_escape_string is used,
      //       probably because the string comes across already escaped from the front end
      $sql .= ",Notes='".$_REQUEST["notes"]."'";
    }
 
    if (array_key_exists("donation", $_REQUEST)) {
      $sql .= ",ExtraDonation=".$_REQUEST["donation"];
    }
    
    $sql .= " WHERE OrderNumber=".$num;

    if (checkAdminToken() && mysqli_query($db_link,$sql)) {  // write to database if security check passes
      echo '{"rc":true,"num":'.$num.',"ref":'.$ref.'}';
      return true;
    } else {
      echo '{"rc":false,"num":'.$num.',"ref":'.$ref.'}';
      if (checkAdminToken()) report_db_error($sql);
      return false;
    }
  } else {
    return false;
  }
}

// action_export_csv: produce CSV version of any table or view
function action_export_csv($view) {
  global $db_link;
  if (!connectDB()) return false;
    
  // security check
  if (!checkAdminToken()) return false;

  if (!mysqli_query($db_link,"set session group_concat_max_len = 10000")) return false;

  $sql = "SELECT * FROM `".$view."`";
  $result = mysqli_query($db_link,$sql);
  
  if ($result) {
    $comma = 0;
    while ($field = mysqli_fetch_field($result)) {
      if ($comma) echo ',';
      else $comma = 1;
      echo '"'.$field->name.'"';
    }
    
    while ($row = mysqli_fetch_row($result)) {
      echo "\n";
      $comma = 0;
      for ($i=0; $i<count($row); ++$i) {
        if ($comma) echo ',';
        else $comma = 1;
        echo '"'.str_replace('"','""',$row[$i]).'"';
      }
    }

    return true;  
  } else {
    report_db_error($sql);
    return false;
  }
}

// output_list: helper function for action_checklist
function output_list($names) {
  global $db_link;
  if (count($names) > 120) {  // only separate into alpha-groups if more than 120 names in list (typically just members)
    $groups = TRUE;
    $keys = array_keys($names);
    $initial = substr($names[$keys[0]],0,1);  // remember first letter of last name of first entry
  } else {
    $groups = FALSE;
  }

  echo "<p>";
     
  foreach ($names as $name) {
    if ($groups && ($initial != substr($name,0,1))) {
      echo "</p><p>";  // start new paragraph for each new letter group
      $initial = substr($name,0,1);
    }
    echo "___ ".$name."<br/>";
  }
  
  echo "</p>";
}

// action_checklist: hidden method for generating an HTML version of the membership checklist
function action_checklist() {
  global $db_link;
  if (!connectDB()) return false;

  echo "<html><body><h1>Anshai Torah Shalach Manot Order Checklist</h1>";

  echo "<h2>Members</h2>";
  $namelist = getNames("Member");
  output_list($namelist);
  
  echo "<h2>Associate Members</h2>";
  $namelist = getNames("Associate");
  output_list($namelist);

  echo "<h2>College Students</h2>";
  $namelist = getNames("College");
  output_list($namelist);

  echo "<h2>Teachers &amp Staff (non-members only)</h2>";
  $namelist = getNames("Non-Member", TRUE);
  output_list($namelist);
  
  echo "</body></html>";
}

// main
if (array_key_exists("action", $_REQUEST)) {
  $act = $_REQUEST["action"];

  if ($act != "export") header("Content-type: application/json");

  switch($act) {

  case 'login':
    $success = action_login();

    // log login
    $logdata = date(DATE_RFC822)." LOGIN";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"];
    $logdata.=" of ".$_REQUEST["user"].(($success)?" (success)":" (failure)")."\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    break;

  case 'summary':
    action_summary();
    break;

  case 'update_order':
    $success = action_update_order();

    // log data update
    if (array_key_exists("ref", $_REQUEST) && ($_REQUEST["ref"] >= 0)) {
      $logdata = date(DATE_RFC822)." UPDATE";
    } else {
      $logdata = date(DATE_RFC822)." UNDO";
    }
    $logdata.=" from ".$_REQUEST["id"]." (".$_SERVER["REMOTE_ADDR"].")";
    $logdata.=" of order #".$_REQUEST["num"].(($success)?" (success)":" (failure)")."\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    break;

  case 'error':
    js_error_report($_REQUEST["message"]);
    echo "{}";  // empty return result
    break;

  case 'export':
    if (array_key_exists("view", $_REQUEST)) {
      $view = $_REQUEST["view"];
      
      header("Content-type: text/csv");
      header("Content-Disposition:attachment;filename=".$view.".csv");
      $success = action_export_csv($view);
      
      // log export
      $logdata = date(DATE_RFC822)." EXPORT";
      $logdata.=" from ".((array_key_exists("id",$_REQUEST))?$_REQUEST["id"]:"unknown")." (".$_SERVER["REMOTE_ADDR"].")";
      $logdata.=" of view ".$view.(($success)?" (success)":" (failure)")."\r\n";
      file_put_contents($usage_log_name, $logdata, FILE_APPEND);
      break;
    }
    
  case 'checklist':
    header("Content-type:text/html;charset=iso-8859-1");
    action_checklist();
    break;
  }

  // very important - do not output rest of this file
  exit();

} else {
  // initial page loading - log it
  $logdata = date(DATE_RFC822)." LOAD";
  $logdata.=" from ".$_SERVER["REMOTE_ADDR"]." on ".(array_key_exists("HTTP_USER_AGENT",$_SERVER)?$_SERVER["HTTP_USER_AGENT"]:"<unknown>")."\r\n";
  file_put_contents($usage_log_name, $logdata, FILE_APPEND);

  // check if user is already logged in (possibly in another window or tab)
  if (isset($_COOKIE["admin_token"])) autoLogin();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head><title>Anshai Torah Shalach Manot Orders</title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<meta name="author" content="Dan Cohn" />
<meta name="description" content="Congregation Anshai Torah Shalach Manot administrator interface" />
<link rel="shortcut icon" href="./anshai.ico" />
<link rel="stylesheet" href="styles/dynamicTable.css" type="text/css" />
<style type="text/css"><!--
    body {font-family:Arial,Helvetica,sans-serif;font-size:11pt;background-color:#f0f0a0;}
    h1 {text-align:center;font-size:15pt;background-color:#f0c0c0;padding:6pt;min-width:600px;border-width:2px;border-style:solid none;border-color:#f00000;}
    h2 {margin-top:0px;text-align:center;}
    button,input[type="button"],input[type="submit"] {font-family:Arial,Helvetica,sans-serif;font-size:10pt;border:solid 1px #808080;background-color:#f0f0f0;margin:0px;padding:3px 6px;cursor:pointer;}
    input[type="text"],input[type="password"],select {border-style:solid;border-width:1px;border-radius: 4px;padding:2px 3px;}

    #login {position:relative;min-width:600px;padding:0px;top:40px;text-align:center;}
    #login_box {position:relative;width:400px;border:3px double #0000f0;padding:10px;margin:auto;text-align:left;}
    #pages {position:relative;width:100%;min-width:100%;padding:0pt;top:10px;clear:both;text-align:center;}
    #page1 {width:100%;min-width:100%;margin:auto;}
    #page2 {width:600px;margin:auto;text-align:left;}
    #page3 {width:800px;margin:auto;text-align:left;}

    #order_summary {background-color:#f0f0f0;border-collapse:collapse;min-width:600px;margin:auto;border:none;}
    #order_summary thead {cursor:pointer;cursor:hand;}
    #order_summary thead tr th,#order_summary tfoot tr td {font-size:8pt;font-weight:bold;text-align:center;background-color:#0000f0;color:#f0f0f0;padding:3px 3px;border:2px solid #f0f080;}
    #order_summary thead tr th {padding-right:20px;}
    #order_summary tbody tr td {font-size:8pt;padding:1px 3px;border:1px solid #f0f080;}
    #order_summary tfoot tr.totals td {background-color:#6060f0;}
    div.button_row {padding:6px 2px;}
    td.left {text-align:left;}
    td.right {text-align:right;}
    td.center {text-align:center;}
    textarea {font-family:Arial,Helvetica,sans-serif;font-size:8pt;border-width:1px;border-style:solid;border-radius: 4px;padding:2px 3px;}
    select {font-size:8pt;}
    option {font-size:9pt;text-align:center;}
    .refresh {width:400px;font-size:9pt;font-weight:bold;color:#0000f0;margin:4pt 0pt;}
    .export {font-size:9pt;margin:2pt 0pt;}

    #status {position:fixed;bottom:0px;right:0px;width:auto;height:auto;margin:4px;padding:4px;background-color:#00f000;font-weight:bold;cursor:default;
             opacity:0.75;filter:alpha(opacity=75);z-index:20;display:none}

    .menubar {width:100%;min-width:600px;}
    .menubar div {vertical-align:middle;display:inline;margin:0px;}

    .tab {background-color:transparent;font-size:11pt;border-width:1px;border-style:none solid solid solid;border-color:#404040;float:left;
          padding:4px 2px;margin:0px;text-align:center;min-width:10em;width:10em;cursor:pointer;}
    .act {font-weight:bold;background-color:#f0f0f0;cursor:default;}
    .over {background-color:#c0c0c0;}
    .logout {float:right;margin:auto;}
    .page {position:relative;padding:8px;margin:auto;}

    td.loglab {text-align:right;padding-right:8px;font-size:10pt;}
    p.instrux {font-size:8pt;background-color:#c0f0c0;margin:0pt 30pt 7pt 30pt;padding:3pt;border:1px dashed #606060;width:auto;}

    .hide {display:none;}
    .textfocus {border-color:#f00000;}
    .nonmember {color:#a000a0;}
    
    .reports {list-style-type: none;}
    .reports li {font-size: 9pt; margin-bottom: 6px;}
    .report {border-radius: 4px; width: 24ex; margin-right: 8px !important;}
--></style>

<script type="text/javascript" src="json2.js"></script>
<script type="text/javascript" src="ezajax.js"></script>
<script type="text/javascript" src="common.js"></script>
<script type="text/javascript" src="dynamicTable_c.js"></script>
<script type="text/javascript">
// globals
var currtab=0,num_orders=0,orderEdit=false,sumhead,userid="";
var changes=[],changeIndex=0,statusTimeout=0,statusOpacity=0.75,currOpacity=0.75;

function DataChange(elem, field, oldVal, newVal) {
  this.undo = function() {
    var parms;
    parms={"num":elem.parentNode.parentNode.id.substr(6),"id":userid,"ref":-1};
    if (field=="pmt_type") {
      elem.selectedIndex=oldVal;
      parms[field]=elem.options[oldVal].value;
    } else if (field=="pmt_rcvd") {
	  elem.checked=oldVal;
	  parms[field]=oldVal;
      updatePmtRcvdTotal(elem);
	} else {
      elem.value=oldVal;
      parms[field]=oldVal;
      if (field=="donation") updateDonationTotals(elem);
      if (field=="paid") updatePaidTotals(elem);
    }
    if (field != "notes") elem.nextSibling.innerHTML=parms[field]; // update hidden field for sorting
    this.reset();
    ezAjax.initiate("update_order",parms,update_callback);
  }

  this.reset = function () {
    elem.style.borderColor="";
  }
  
  elem.style.borderColor="#00c000";
}

function undoChanges() {
  var numEdits=changeIndex;
  hideStatus();
  while (changeIndex > 0) {
    if (changes[--changeIndex]) {
      changes[changeIndex].undo();
      delete changes[changeIndex];
    }
  }
  if (numEdits==1) showStatus("Last change cancelled",5000);
  else if (numEdits>1) showStatus("Last "+numEdits+" changes cancelled",5000);
}

function doLogin(event) {
  var pwd=gebi("login_pass").value;
  var but=gebi("login_but");
  var form=gebi("login_form");
  userid=gebi("login_email").value; // global

  if (userid && pwd) {
    var dat={"user":userid, "pwd":pwd, "magic_key":"shalom"+(3*10000+1528)};
    but.focus();
    but.blur();
    but.disabled=true;
    but.value="Please Wait";
    document.body.style.cursor="wait";
    ezAjax.initiate("login",dat,login_callback);
    return true;
  }
  
  return false;
}

function login_callback(result) {
  gebi("login_pass").value="";
  document.body.style.cursor="auto";
  if (result.rc){
    gebi("login").style.display="none";
    gebi("username").innerHTML=htmlspecialchars(result.name);
    gebi("menubar").style.display="block";
    if (result.token) {
        document.cookie="admin_token="+result.token+"; path=/;";
    }
    refreshOrders();
    tab_click(gebi("tab1"));
  } else {
    alert("Invalid login!");
    gebi("login_pass").focus();
    gebi("login_but").value="Login";
    gebi("login_but").disabled=false;
  }
}

function refreshOrders() {
  var i,sumbody=gebi("summary_body"),nodes=sumbody.childNodes,but1=gebi("refresh_but"),but2=gebi("refresh_but2");
  gebi("page1").style.cursor="wait";
  but1.value="Loading Data...";
  but2.value="Loading Data...";
  but1.blur();
  but2.blur();
  but1.disabled=true;
  but2.disabled=true;
  lastRefresh=new Date();
  for (i=nodes.length-1;i>=0;--i) {
    sumbody.removeChild(nodes[i]);
  }
  ezAjax.initiate("summary",{"id":userid},summary_callback);
  
  // check for when browser regains focus
  // window.onfocus = periodicRefresh;
}

function periodicRefresh() {
  // refresh orders if visible, user is not editing, and at least 10 minutes has passed
  // NOT CURRENTLY IN USE
  var now=new Date(), elapsed=(now.getTime()-lastRefresh.getTime())/60000;
  if ((currtab == 1) && !orderEdit && (elapsed >= 10)) refreshOrders();
}

function editOn(e) {
  orderEdit=true;
  e.parentNode.parentNode.style.backgroundColor="#f0c0c0";
  e.className="textfocus";
  if (e.nodeName.toLowerCase()=="select") {
    e.valueBeforeEditing=e.selectedIndex;
  } else if (e.nodeName.toLowerCase()=="input" && e.type=="checkbox") {
	e.valueBeforeEditing=e.checked;
  } else {
    e.valueBeforeEditing=e.value;
  }
}

function editOff(e) {
  orderEdit=false;
  e.parentNode.parentNode.style.backgroundColor="transparent";
  e.className="";
}

function over_row() {
  if (!orderEdit) this.style.backgroundColor="#c0f0c0";
}

function out_row() {
  if (!orderEdit) this.style.backgroundColor="transparent";
}

function createTd(html,cls) {
  var td=document.createElement("td");
  td.innerHTML=html;
  if (cls!==undefined) td.className=cls;
  return td;
}

function dataEnter() {
  document.activeElement.blur();
}

function recordChange(e,field) {
  changes[changeIndex]=new DataChange(e,field,e.valueBeforeEditing,(e.nodeName.toLowerCase()=="select")?e.selectedIndex:e.value);
  hideStatus();
  return changeIndex++;
}

function savePaid(e) {
  var parms={"num":e.parentNode.parentNode.id.substr(6),"id":userid};
  parms["paid"]=e.value;
  parms["ref"]=recordChange(e,"paid");
  ezAjax.initiate("update_order",parms,update_callback);
  e.nextSibling.innerHTML=e.value; // update hidden field for sorting
  var e2=e.parentNode.nextElementSibling.nextElementSibling.firstChild;  // pmt rcvd checkbox
  var wasChecked=e2.checked;
  e2.checked=(e.value > 0.0);
  if (wasChecked?!e2.checked:e2.checked) e2.onchange();
}

function saveDonation(e) {
  var parms={"num":e.parentNode.parentNode.id.substr(6),"id":userid};
  parms["donation"]=e.value;
  parms["ref"]=recordChange(e,"donation");
  ezAjax.initiate("update_order",parms,update_callback);
  e.nextSibling.innerHTML=e.value; // update hidden field for sorting
}

function savePmtType(e) {
  var parms={"num":e.parentNode.parentNode.id.substr(6),"id":userid};
  parms["pmt_type"]=e.options[e.selectedIndex].value;
  parms["ref"]=recordChange(e,"pmt_type");
  ezAjax.initiate("update_order",parms,update_callback);
  e.nextSibling.innerHTML=parms["pmt_type"]; // update hidden field for sorting
}

function savePmtRcvd(e) {
  var parms={"num":e.parentNode.parentNode.id.substr(6),"id":userid};
  parms["pmt_rcvd"]=e.checked;
  parms["ref"]=recordChange(e,"pmt_rcvd");
  ezAjax.initiate("update_order",parms,update_callback);
  e.nextSibling.innerHTML=parms["pmt_rcvd"]?'y':'n'; // update hidden field for sorting
}
	
function saveNotes(e) {
  var parms={"num":e.parentNode.parentNode.id.substr(6),"id":userid};
  if (e.value.length > 500) {
    alert("Notes field exceeds the limit of 500 characters. Only the first 500 characters will be saved.");
    parms["notes"]=e.value.substr(0,500);
  } else {
    parms["notes"]=e.value;
  }
  parms["ref"]=recordChange(e,"notes");
  ezAjax.initiate("update_order",parms,update_callback);
}

function updatePaidTotals(e) {
  var i,paid=parseFloat(e.value),nodes=e.parentNode.parentNode.childNodes,pos,total_paid=0,total_bal=0;
  for (i=0;i<nodes.length;++i) {
    if (nodes[i]===e.parentNode) {
      var balance=parseFloat(nodes[i-2].innerHTML)-paid;
      nodes[i+1].innerHTML=balance.toFixed(2);
      nodes[i+1].style.fontWeight=((balance>0)?"bold":"normal");
      pos=i;
      break;
    }
  }
  nodes=gebi("summary_body").childNodes;
  for (i=0;i<nodes.length;++i) {
    total_paid+=parseFloat(nodes[i].childNodes[pos].firstChild.value);
    total_bal+=parseFloat(nodes[i].childNodes[pos+1].innerHTML);
  }
  gebi("total_paid").innerHTML=total_paid.dollars2();
  gebi("total_balance").innerHTML=total_bal.dollars2();
}

function updateDonationTotals(e) {
  var i,donation=parseFloat(e.value),nodes=e.parentNode.parentNode.childNodes,pos,total_due=0,total_bal=0;
  for (i=0;i<nodes.length;++i) {
    if (nodes[i]===e.parentNode) {
      var due=parseFloat(nodes[i-1].innerHTML)+donation;
      var balance=due-parseFloat(nodes[i+3].firstChild.value);
      nodes[i+1].innerHTML=due.toFixed(2);
      nodes[i+4].innerHTML=balance.toFixed(2);
      nodes[i+4].style.fontWeight=((balance>0)?"bold":"normal");
      pos=i;
      break;
    }
  }
  nodes=gebi("summary_body").childNodes;
  for (i=0;i<nodes.length;++i) {
    total_due+=parseFloat(nodes[i].childNodes[pos+1].innerHTML);
    total_bal+=parseFloat(nodes[i].childNodes[pos+4].innerHTML);
  }
  gebi("total_due").innerHTML=total_due.dollars2();
  gebi("total_balance").innerHTML=total_bal.dollars2();
}

function updatePmtRcvdTotal(e) {
  var i,nodes=e.parentNode.parentNode.childNodes,pos,total_conf=0;
  for (i=0;i<nodes.length;++i) {
    if (nodes[i]===e.parentNode) {
      pos=i;
      break;
    }
  }
  nodes=gebi("summary_body").childNodes;
  for (i=0;i<nodes.length;++i) {
    if (nodes[i].childNodes[pos].firstChild.checked) ++total_conf;
  }
  gebi("total_confirmed").innerHTML=total_conf;
}

function fadeStatus() {
  if (currOpacity > 0) {
    currOpacity-=0.05;
    gebi("status").style.opacity = currOpacity;
    gebi("status").style.filter="alpha(opacity="+currOpacity*100+")";
    statusTimeout=window.setTimeout(fadeStatus,100);
  } else {
    statusTimeout=0;
    gebi("status").style.display="none";
    while (changeIndex > 0) {
      if (changes[--changeIndex]) {
        changes[changeIndex].reset();
        delete changes[changeIndex];
      }
    }
  }   
}

function showStatus(msg,ttl) {
  var s = gebi("status");
  s.innerHTML=msg;
  s.style.display="block";
  s.style.opacity=statusOpacity;
  s.style.filter="alpha(opacity="+statusOpacity*100+")";
  currOpacity=statusOpacity;
  statusTimeout=window.setTimeout(fadeStatus,ttl);
}

function hideStatus() {
  gebi("status").style.display="none";

  if (statusTimeout) {
    window.clearTimeout(statusTimeout);
    statusTimeout=0;
  }
}

function holdStatus() {
  if (statusTimeout) {
    window.clearTimeout(statusTimeout);
    statusTimeout=0;
    gebi("status").style.opacity=statusOpacity;
    gebi("status").style.filter="alpha(opacity="+statusOpacity*100+")";
    currOpacity=statusOpacity;
  }
}

function resumeStatus() {
  statusTimeout=window.setTimeout(fadeStatus,5000);
}

function dismissStatus() {
  while (changeIndex > 0) {
    if (changes[--changeIndex]) {
      changes[changeIndex].reset();
      delete changes[changeIndex];
    }
  }
  hideStatus();
}

function update_callback(result) {
  var msg=" - <a href='javascript:null' onclick='undoChanges();event.stopPropagation();return false;'>";
  
  if (result.rc) {
    if (result.ref >= 0) {
      hideStatus();
      if (result.ref > 0) {
        msg=(1+result.ref)+" changes saved"+msg+"undo all</a>";
      } else {
        msg="Change saved"+msg+"undo</a>";
      }
      showStatus(msg,10000);
    }
  } else {
    var msg;
    if (result.num) {
      msg="Sorry, there was an error updating the data for order #"+result.num+".";
    } else {
      msg="One of your updates did not complete successfully.";
    }
    if (result.ref >= 0) msg+=" Your recent edits will be undone.";
    msg+=" Please refresh and try again.";
    alert(msg);
    undoChanges(); // much simpler to just revert all edits (at least for now)
  }
}

function summary_callback(result) {
  var i,sumtable=gebi("order_summary"),sumbody=gebi("summary_body"),ord,but1=gebi("refresh_but"),but2=gebi("refresh_but2");
  var st,don,due,paid,bal,total_bene=0,total_recip=0,total_due=0,total_paid=0,total_bal=0,total_conf=0;
  var sh=gebi("summary_head");
  if (sh.childNodes.length > 0) sh.removeChild(sh.firstChild);
  if (result.rc && result.hasOwnProperty("orders")) {
    num_orders=result.orders.length;
    if (num_orders > 0) sh.appendChild(sumhead.cloneNode(true));  // do not show header if table empty
    for (i=0;i<num_orders;++i) {
      var tr=document.createElement("tr"),td;
      ord=result.orders[i];
      tr.id="order_"+ord.num;
      tr.onmouseover=over_row;
      tr.onmouseout=out_row;
      st=parseFloat(ord.subtotal);
      don=parseFloat(ord.donation);
      due=st+don;
      paid=parseFloat(ord.paid);
      bal=due-paid;
      tr.appendChild(createTd(ord.num,"center"));
      td=createTd(htmlspecialchars(ord.first),"left");
      if (ord.status=="Non-Member") {
        td.className="left nonmember";
        td.title="Non-member";
      }
      tr.appendChild(td);
      td=createTd(htmlspecialchars(ord.last),"left");
      if (ord.status=="Non-Member") {
        td.className="left nonmember";
        td.title="Non-member";
      }
      tr.appendChild(td);
      tr.appendChild(createTd(htmlspecialchars(ord.phone),"left"));
      tr.appendChild(createTd(htmlspecialchars(ord.email),"left"));
      td=createTd((ord.benefactor)?"<img src='tick.png' alt='yes' width='12' height='12'/><span class='hide'>y</span>":"<span class='hide'>n</span>","center");
      td.className="center";
      tr.appendChild(td);
      td=createTd((ord.reciprocity)?"<img src='tick.png' alt='yes' width='12' height='12'/><span class='hide'>y</span>":"<span class='hide'>n</span>");
      td.className="center";
      tr.appendChild(td);
      td=createTd(st.toFixed(2));
      td.className="right";
      tr.appendChild(td);
      td=createTd("<input type='text' style='text-align:right;font-size:8pt' size='8' maxlength='10' onkeydown='return keydown(event,dataEnter);' onchange='convert_to_dollars(this,false);saveDonation(this);updateDonationTotals(this);' value='"+don.toFixed(2)+"' onfocus='editOn(this);' onblur='editOff(this);'/><span class='hide'>"+don.toFixed(2)+"</span>");
      td.className="center";
      tr.appendChild(td);
      td=createTd(due.toFixed(2));
      td.className="right";
      tr.appendChild(td);
      td=createTd("<select onkeydown='return keydown(event,dataEnter);' onchange='savePmtType(this);' onfocus='editOn(this);' onblur='editOff(this);'><option value='check'"+((ord.pmt_type=="check")?" selected='selected'":"")+">check</option><option value='credit'"+((ord.pmt_type=="credit")?" selected='selected'":"")+">credit</option><option value='echeck'"+((ord.pmt_type=="echeck")?" selected='selected'":"")+">e-check</option></select><span class='hide'>"+ord.pmt_type+"</span>");
      td.className="center";
      tr.appendChild(td);
      td=createTd("<input type='text' style='text-align:right;font-size:8pt' size='6' maxlength='8' onkeydown='return keydown(event,dataEnter);' onchange='convert_to_positive_dollars(this,false);savePaid(this);updatePaidTotals(this);' value='"+paid.toFixed(2)+"' onfocus='editOn(this);' onblur='editOff(this);'/><span class='hide'>"+paid.toFixed(2)+"</span>");
      td.className="center";
      tr.appendChild(td);
      td=createTd(bal.toFixed(2));
      td.className="right";
      td.style.fontWeight=((bal>0)?"bold":"normal");
      tr.appendChild(td);
      td=createTd("<input type='checkbox' onchange='savePmtRcvd(this);updatePmtRcvdTotal(this);' value='PmtRcvd' onfocus='editOn(this);' onblur='editOff(this);'"+((ord.pmt_rcvd)?" checked":"")+"/><span class='hide'>"+((ord.pmt_rcvd)?"y":"n")+"</span>");
      td.className="center";
      tr.appendChild(td);
      td=createTd("<textarea rows='1' cols='30' onkeydown='return noBS(event)&&keydown(event,dataEnter);' onchange='saveNotes(this);' onfocus='this.rows=3;editOn(this);' onblur='this.rows=1;editOff(this);' title='WARNING: These notes are visible to anyone editing their order.'>"+htmlspecialchars(ord.notes)+"</textarea>");
      td.className="center";
      tr.appendChild(td);
      sumbody.appendChild(tr);
      total_bene+=(ord.benefactor)?1:0;
      total_recip+=(ord.reciprocity)?1:0;
      total_due+=due;
      total_paid+=paid;
      total_bal+=bal;
	  total_conf+=(ord.pmt_rcvd)?1:0;
    }
    gebi("total_orders").innerHTML=num_orders;
    gebi("total_benefactors").innerHTML=total_bene;
    gebi("total_reciprocity").innerHTML=total_recip;
    gebi("total_due").innerHTML=total_due.dollars2();
    gebi("total_paid").innerHTML=total_paid.dollars2();
    gebi("total_balance").innerHTML=total_bal.dollars2();
    gebi("total_confirmed").innerHTML=total_conf;
    if (num_orders > 0) {
      sumtable.className="dynamicTable";
      dynamicTableInit(sumtable,false,true); // no column hiding
    }
  } else {
    gebi("total_orders").innerHTML="0";
    gebi("total_benefactors").innerHTML="0";
    gebi("total_reciprocity").innerHTML="0";
    gebi("total_due").innerHTML="0";
    gebi("total_paid").innerHTML="0";
    gebi("total_balance").innerHTML="0";
    alert("Unable to download order summary. Please try again. You may need to logout and log back in.");
  }
  but1.value="Refresh Data";
  but2.value="Refresh Data";
  but1.disabled=false;
  but2.disabled=false;
  gebi("page1").style.cursor="auto";
}

function tab_mouseover(e) {
  if (currtab !== parseInt(e.id.substring(3))) e.className="tab over";
}

function tab_mouseout(e) {
  if (currtab !== parseInt(e.id.substring(3))) e.className="tab";
}

function tab_click(e) {
  var n=parseInt(e.id.substring(3));
  if (currtab !== n) {
    currtab=n;
    e.className="tab act";
    n=1;
    while (e=gebi("tab"+n)) {
      if (currtab !== n) {
        e.className="tab";
        gebi("page"+n).style.display="none";
      }
      ++n;
    }
    gebi("page"+currtab).style.display="block";
  }
}

function openOrderForm() {
  window.open("index.php?admin="+encodeURIComponent(userid), "_blank", "", true);
  gebi("order_but").blur();
}

function exportData(reportName) {
  location.assign("?action=export&view="+reportName+"&id="+encodeURIComponent(userid));
}

function init() {
  sumhead=gebi("summary_head").firstChild;

<?php
    // is user already logged in?
    if ($admin_user != "") {
        echo "  userid='".$admin_user."';\n";
        echo "  login_callback({'rc':true,'name':'".addcslashes($admin_name,"'")."','token':'".$admin_token."'});\n  return;\n\n";
    }
?>

  gebi("login_but").disabled=false;
  gebi("login").style.display="block";
  if (gebi("login_email").value) {
    gebi("login_pass").focus();
  } else {
    gebi("login_email").focus();
  }
}

function logout() {
//  warnOnUnload=false; // for some reason this var change is ineffective on IE; not sure why
  document.cookie="admin_token=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/;";
  window.location.reload();
}
</script>
</head>

<body id="body" onload="init();">
<h1>Congregation Anshai Torah &ndash; Shalach Manot Orders</h1>

<div id="menubar" style="display:none;">
<div id="tab1" class="tab" onmouseover="tab_mouseover(this);" onmouseout="tab_mouseout(this);" onclick="tab_click(this);return ignore(event);">
Order Summary</div>
<div id="tab2" class="tab" onmouseover="tab_mouseover(this);" onmouseout="tab_mouseout(this);" onclick="tab_click(this);return ignore(event);">
Order Submission</div>
<div id="tab3" class="tab" onmouseover="tab_mouseover(this);" onmouseout="tab_mouseout(this);" onclick="tab_click(this);return ignore(event);">
Reports</div>
<div class="logout"><span id="username"></span>&nbsp;&nbsp;&nbsp;[ <a href="javascript:logout()">Logout</a> ]</div>
</div>

<div id="login"><div id="login_box">
<form id="login_form" action="blankpage.html" target="bogus" onsubmit="return doLogin();">
<p align="left"><b>Please sign in:</b></p>
<table border="0">
<tr><td class="loglab"><label for="login_email">E-mail address</label></td>
<td><input id="login_email" type="text" class="textinput" name="username" size="32" maxlength="64" onfocus="inputF(this);" onblur="inputB(this);" onchange="trim_contents(this);"/></td>
</tr><tr>
<td class="loglab"><label for="login_pass">Password</label></td>
<td><input id="login_pass" type="password" class="textinput" name="password" size="20" maxlength="20" onfocus="inputF(this);" onblur="inputB(this);"/></td>
</tr>
</table>
<p align="center"><input id="login_but" type="submit" value="Login"/></p>
</form>
<iframe src="blankpage.html" id="bogus" name="bogus" style="display:none"></iframe>
</div></div>

<div id="pages">
<div id="page1" class="page" style="display:none;">
<form id="summary_form" action="" method="post" onkeydown="return ignoreEnter(event);">
<p class="instrux"><b>Instructions:</b> To see the latest orders, click the <span style="color:#0000f0">Refresh Data</span> button.
Sort any column by clicking the column heading at the top of the table; click a second time to reverse sort.
A refresh is required to properly sort fields which have been edited since the data was last refreshed.
<span style="color:#f00000">Changes are saved automatically.</span></p>
<div class="button_row">
<input type="button" id="refresh_but" class="refresh" value="Refresh Data" onclick="refreshOrders();"/>
<input type="button" class="export" value="Export Data to CSV File (for Excel)" onclick="exportData('OrderSummary')"/>
</div>
<table id="order_summary">
<thead id="summary_head"><tr>
<th title="Click to sort by order number">Order #</th><th title="Click to sort by first name">First Name(s)</th>
<th title="Click to sort by last name">Last Name</th>
<th>Phone Number</th><th>E-mail Address</th>
<th title="Click twice to bring all benefactors to the top">Benefactor</th>
<th title="Click twice to bring all those with reciprocity selected to top of the list">Recip.</th>
<th title="Price of order before added donation: Click to sort">Subtotal</th>
<th title="Optional donation">Added Donation</th>
<th title="Click to sort by total amount due (refresh first if edits were made)">Total Due</th>
<th title="Click to sort by payment type (refresh first if edits were made)">Pmt. Type</th>
<th title="Click to sort by amount paid (refresh first if edits were made)">Amt. Paid</th>
<th title="Balance due: click to sort">Balance</th>
<th title="Click to sort by payment received">Pmt. Rcvd</th>
<th title="WARNING: These notes are visible to anyone editing their order.">Notes or Special Instructions</th>
</tr></thead>
<tbody id="summary_body"></tbody>
<tfoot id="summary_foot"><tr>
<td>Orders</td><td>First Name(s)</td><td>Last Name</td><td>Phone Number</td><td>E-mail Address</td>
<td>Benefactors</td><td>Reciprocity</td><td>Subtotal</td><td>Added Donation</td><td>Total Due</td>
<td>Pmt. Type</td><td>Amt. Paid</td><td>Balance</td><td>Pmt. Rcvd</td><td>&nbsp;</td>
</tr><tr class="totals">
<td id="total_orders" class="center" title="Total number of orders">0</td><td></td><td></td><td></td><td></td>
<td id="total_benefactors" class="center" title="Total # of benefactors">0</td>
<td id="total_reciprocity" class="center" title="Total # of orders with reciprocity selected">0</td><td></td><td></td>
<td id="total_due" class="right" title="Total of all orders">0</td><td></td>
<td id="total_paid" class="right" title="Total amount paid">0</td>
<td id="total_balance" class="right" title="Total balance owed">0</td>
<td id="total_confirmed" class="center" title="Total # of orders with payments received">0</td>
<td></td>
</tr></tfoot>
</table>
<div class="button_row">
<input type="button" id="refresh_but2" class="refresh" value="Refresh Data" onclick="refreshOrders();"/>
<input type="button" class="export" value="Export Data to CSV File (for Excel)" onclick="exportData('OrderSummary')"/>
</div>
<div style="margin:23px 0px;font-size:small;">
<a title="Dynamic Table - A javascript table sort widget." href="http://dynamictable.com">Quick and easy table sorting powered by Dynamic Table</a>
</div>
</form>
<div id="status" onmouseover="holdStatus();" onmouseout="resumeStatus();" onclick="dismissStatus();"> </div>
</div>

<div id="page2" class="page" style="display:none;">
<h2>Submit a Shalach Manot Order</h2>
<p><b>Click the button below to place or update an order on someone else's behalf.</b>&nbsp;
This will open a new window or tab where you can complete the order form.</p>
<p>Please note:</p>
<ul><li>If you include an e-mail address with the order, the person/family you are ordering for will receive a confirmation via e-mail.</li>
<li>Be sure to include the total amount paid if a payment was received with the order.</li>
<li>After submitting the order, you may close the window/tab or use the refresh button to enter another order.</li>
</ul>
<p></p>
<form action="" method="get" onkeydown="return ignoreEnter(event);">
<center><input id="order_but" type="button" value="Open Order Form" onclick="openOrderForm();"/></center>
</form>
</div>

<div id="page3" class="page" style="display:none;">
    <h2>Downloadable Reports (in CSV format)</h2>
    <form id="report_form" action="">
        <p style="text-align: center;"><b>Select a report from the list below.</b></p>
        <ul class="reports">
            <li>
                <input type="button" class="report" value="Basket Counts" onclick="exportData('BasketCounts');"/>
                Number of baskets to be delivered and shipped by membership category
            </li>
            <li>
                <input type="button" class="report" value="Benefactors" onclick="exportData('Benefactors');"/>
                List of Benefactors for each year along with email address &amp; phone numbers
            </li>
            <li>
                <input type="button" class="report" value="Assembly Volunteers" onclick="exportData('Volunteers');"/>
                List of this year&apos;s basket assembly volunteers with contact info
            </li>
            <li>
                <input type="button" class="report" value="Delivery Volunteers" onclick="exportData('Drivers');"/>
                List of this year&apos;s delivery volunteers (drivers) with contact info
            </li>
            <li>
                <input type="button" class="report" value="Order Summary" onclick="exportData('OrderSummary');"/>
                List of this year&apos;s orders (same as on first tab)
            </li>
            <li>
                <input type="button" class="report" value="Reciprocity Preview" onclick="exportData('Reciprocity-Preview');"/>
                Additional names to be added when Reciprocity is applied
            </li>
            <li>
                <input type="button" class="report" value="Reciprocity Review" onclick="exportData('Reciprocity-ReviewSummary');"/>
                Names added for Reciprocity (only applicable after Reciprocity applied)
            </li>
            <li>
                <input type="button" class="report" value="Self Pickups" onclick="exportData('SelfPickup');"/>
                Members picking up extra baskets and how many
            </li>
            <li>
                <input type="button" class="report" value="Sponsor Counts" onclick="exportData('BigWinners');"/>
                Number of sponsors per basket (including Benefactors), sorted from most to least
            </li>
        </ul>
    </form>
</div>
    
</div>

</body>
</html>
