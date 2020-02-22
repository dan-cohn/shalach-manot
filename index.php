<?php
// -------------------------------------------------
// SHALACH MANOT ORDER FORM
// for Congregation Anshai Torah
// -------------------------------------------------
//
// Copyright (c) 2009-2020 Daniel M. Cohn
//
// -------------------------------------------------

// setup database and other variables for test vs. live site
include("shul-settings.php");
// common globals and functions
include("common.php");

// *** IMPORTANT - STEPS TO ACTIVATE LIVE SITE ***
//
// 1. Change common.php to include "catsm.php" (on cloud server)
// 2. Set appropriate $membership and $delivery_msg
// 3. Set $mail_recipients to true in catsm.php
// 4. Set $site_online to true
//
// ************************************************

// global to determine if site is live or not
$site_online = true;
//$offline_msg = "Thanks for stopping by, but we are not yet accepting orders. Please come back soon.";
//$offline_msg = "Thanks for stopping by, but we can no longer accept orders for this year. The final deadline was March 14.";
$offline_msg = "Thanks for stopping by, but we are not yet accepting orders. Please come back in February.";

// assembly/pickup date
$assembly_date = "Sunday, March 1";

// special benefactor benefit
$benefactor_extra = "This year, Benefactors get free admittance to the Purim party on Monday, March 9 at Pete's Dueling Piano Bar!";

// global to determine if self pickup option should be offered
$self_pickup = true;
// $self_pickup_text = "This year you can choose to pickup your own basket on <b>Sunday, February 17</b>, the week before Purim.&nbsp; If you pickup your own basket, you also have the option to purchase additional baskets to share with family or friends outside the congregation.&nbsp;See below for details.";
$extra_baskets = true;  // replace self pickup with option for ordering extra baskets
$self_pickup_text = "You have the option of purchasing additional baskets to share with non-member family and friends.&nbsp; Any extra baskets you order will be available for <b>pickup</b> at the shul between 10 AM and Noon on ".$assembly_date.".&nbsp; <b>These baskets will NOT be delivered to your home.</b>";

// approximate number of members (including associates) plus staff
// does not count CATs in College
$membership = 600;

// approximate number of CATs in College deliveries
$cats = 100;

// approximate number of baskets delivered
$deliveries = 600;

// details about when deliveries will occur - if applicable
$delivery_msg = "Deliveries will begin on Sunday, March 1 and must be completed before Purim on March 10.";

// order deadline - for use in emails to recipients
$deadline = "February 21";

// SM chair and email address
$chair_name  = "Jacob Ratner";
$chair_email = "jaratner@gmail.com";

// filename globals
$order_number_file  = "next_order_number.txt";
$usage_log_name     = "cat_sm_usage_log.txt";
$error_log_name     = "cat_sm_error_log.txt";

// loadAdminData: check to see if order placed on behalf of someone else by an admin; set globals
function loadAdminData() {
  global $admin_user, $admin_name, $db_link;

  $admin_user="";

  if (array_key_exists("admin", $_REQUEST)) {
    $result = mysqli_query($db_link,"SELECT Name,Token FROM Admins WHERE Email='".$_REQUEST["admin"]."'");
    if ($result) {
      $row = mysqli_fetch_array($result);
      if ($row) {
        // check to ensure admin is logged in
        if (isset($_COOKIE["admin_token"]) && !strcmp($row[1],$_COOKIE["admin_token"])) {
          $admin_user = $_REQUEST["admin"];
          $admin_name = $row[0];
        }
      }
    }
  }
}

// getOrderNumber: assign the next sequential order number
function getOrderNumber() {
  global $db_link;
  
  $result = mysqli_query($db_link, "SELECT MAX(OrderNumber) FROM Orders");
  $row = mysqli_fetch_array($result);
  $max_num = (int)$row[0];
  return $max_num + 1;
}

// generatePIN: selects a random PIN of a specified # of digits (returns as a string)
function generatePIN($digits=4) {
  $pin=rand(0,pow(10,$digits)-1);
  return sprintf("%'0".$digits."d",$pin);
}

// getOrderData: reads an order from the DB based on order # and returns certain fields as an assoc array (for order submit handling)
function getOrderData($num) {
  global $db_link;
  $result = mysqli_query($db_link,"SELECT Subtotal,PmtType,TotalPaid,PriceOverride,PIN FROM Orders WHERE OrderNumber=".$num);
  if ($result) {
    return mysqli_fetch_assoc($result);
  } else {
    return 0;
  }
}

// orderExists: checks to see if an order exists; returns true if yes
function orderExists($nameid, $year) {
  global $db_link;
  $result = mysqli_query($db_link,"SELECT OrderNumber FROM Orders WHERE Year=".$year." AND NameID=".$nameid);
  if ($result) {
    if (mysqli_num_rows($result) > 0) return true;
  }
  return false;
}

// orderExistsAndFetchPin: checks to see if an order exists; returns PIN or -1 if order not found
function orderExistsAndFetchPin($nameid, $year) {
  global $db_link;
  $result = mysqli_query($db_link,"SELECT PIN FROM Orders WHERE Year=".$year." AND NameID=".$nameid);
  if ($result) {
    $row = mysqli_fetch_array($result);
    if ($row) {
      return $row[0];
    }
  }
  return -1;
}

// storeOrder: create a new order in the database
function storeOrder($existing,$orderNum,$year,$nameid,$status,$staff,$benefactor,$reciprocity,$extraBaskets,$extraDonation,$subtotal,$pmtType,$paid,$notes,$customName,$driver,$volunteer,$phone,$pin,$clrPmtConf) {
  global $db_link;
  $bene=($benefactor)?1:0;
  $recip=($reciprocity)?1:0;
  $ed=($extraDonation)?$extraDonation:"null";
  $name=(strlen($customName)>0)?("'".addslashes($customName)."'"):"null";
  $pmtType=($pmtType)?("'".$pmtType."'"):"null";
  $drive=($driver)?1:0;
  $vol=($volunteer)?1:0;
  $notes=addslashes($notes);
  $phone=($phone!="")?("'".addslashes($phone)."'"):"null";

  if ($existing) {
    // update all fields except NameID, MemberStatus, StaffMember, Year, and PIN
    $sql="UPDATE Orders SET AllMembers=".$bene.",AllAssociates=".$bene.",AllStaff=".$bene.",Reciprocity=".$recip.",ExtraBaskets=".$extraBaskets.",ExtraDonation=".$ed.",Subtotal=".$subtotal.
      ",PmtType=".$pmtType.",TotalPaid=".$paid.",Notes='".$notes."',CustomName=".$name.",Driver=".$drive.",Volunteer=".$vol.",PhoneProvided=".$phone.",LastUpdated=CURRENT_TIMESTAMP".
      ($clrPmtConf?",PmtConfirmed=0":"")." WHERE OrderNumber=".$orderNum;
  } else {
    $sql="INSERT INTO Orders (OrderNumber,Year,NameID,MemberStatus,StaffMember,AllMembers,AllAssociates,AllStaff,Reciprocity,ExtraBaskets,ExtraDonation,Subtotal,PmtType,TotalPaid,Notes,CustomName,Driver,Volunteer,PhoneProvided,PIN,Created)".
      " VALUES (".$orderNum.",".$year.",".$nameid.",'".$status."',".$staff.",".$bene.",".$bene.",".$bene.",".$recip.",".$extraBaskets.",".$ed.",".$subtotal.",".$pmtType.",".$paid.",'".$notes."',".$name.",".$drive.",".$vol.",".$phone.",'".$pin."',CURRENT_TIMESTAMP)";
  }

  if (mysqli_query($db_link,$sql)) {
    return true;
  } else {
    report_db_error($sql);
    return false;
  }
}

// find out which names were added to an order due to reciprocity and return them as an associative array
function getRecipNames($orderNum) {
    global $db_link;
    $rv = array();
    $result = mysqli_query($db_link,"SELECT NameID FROM OrderDetails WHERE OrderNumber=".$orderNum." AND Reciprocity=1");
    if ($result) {
        while ($row = mysqli_fetch_array($result)) {
            $rv[$row[0]] = true;
        }
    }
    return $rv;
}

// clearOrderDetails: delete all records from OrderDetails and OrderWriteins for a given order
function clearOrderDetails($orderNum) {
  global $db_link;
  $rc=true;
  $sql="DELETE FROM OrderDetails WHERE OrderNumber=".$orderNum;
  if (!mysqli_query($db_link,$sql)) {
    report_db_error($sql);
    $rc=false;
  }
  $sql="DELETE FROM OrderWriteins WHERE OrderNumber=".$orderNum;
  if (!mysqli_query($db_link,$sql)) {
    report_db_error($sql);
    $rc=false;
  }
  return $rc;
}

// storeDetails: add list of names to an order in the DB; returns false if any one of the adds fails
function storeDetails($orderNum,$nameidList,$recipList) {
  global $db_link;
  $rc=true;
  foreach ($nameidList as $nameid) {
    $recip=(array_key_exists($nameid,$recipList))?1:0;
    $sql="INSERT INTO OrderDetails VALUES (".$orderNum.",".$nameid.",".$recip.",(SELECT Status FROM People WHERE NameID=".$nameid."),(SELECT Staff FROM People WHERE NameID=".$nameid."))";
    if (!mysqli_query($db_link,$sql)) {
      report_db_error($sql);
      $rc=false;
    }
  }
  return $rc;
}

// splitName: helper function that takes a string and returns "first" name, "last" name, and "andFamily" bool (0=false or 1=true); assumes min of 2 "words" in name
function splitName($name) {
  $first = $last = "";
  $andFamily = 0;
  
  if (substr_count($name, ",") == 1) {
    $pos = strpos($name, ",");
    $andFam = stripos($name, "and family");
    $andPos = stripos($name, " & ");
    
    if ($andFam && ($andFam > $pos)) {  // format: LAST, FIRST and Family
      $names = explode(" ", substr($name,$pos+2,$andFam-$pos-3)." ".substr($name,0,$pos)." and Family");  // new format: FIRST LAST and Family
    } elseif ($andPos > 0 && $andPos < $pos) {  // format: FIRST and FIRST LAST, KIDS
      $names = explode(" ", substr($name,0,$pos));    // first names: FIRST and FIRST
      $names[count($names)-1] .= substr($name,$pos);  // last name: LAST, KIDS
    } else {  // format: LAST, FIRST
      $names = explode(" ", substr($name,$pos+2)." ".substr($name,0,$pos));  // new format: FIRST LAST
    }
  } else {
    $names = explode(" ", $name);
  }

  $len = count($names);

  if (strtolower($names[$len-1]) == "family") {
    if ($len >= 4 && (strtolower($names[$len-2]) == "and")) {  // format: FIRST LAST and Family
      $andFamily = 1;
      $first = array_concat($names, 0, $len-3);
      $last = $names[$len-3];
    } elseif ($len >= 3) {  // format: THE LAST Family
      $first = array_concat($names, 0, $len-2);
      $last = array_concat($names, $len-2, 2);
    } elseif ($len == 2) {  // format: LAST Family
      $first = $names[0];
      $last = $names[1];
    }
  } elseif ($len >= 2) {
    $first = array_concat($names, 0, $len-1);
    $last = $names[$len-1];
  } else {
    // should not get here
    $first = $last = $names[0];
  }
  
  return array("first"=>$first, "last"=>$last, "andFamily"=>$andFamily);
}

// capitalize: helper function to capitalize a name (recursive)
function capitalize($name) {
  $pos=strpos($name, " ");
  if ($pos) {
    return capitalize(substr($name,0,$pos))." ".capitalize(substr($name,$pos+1));
  } else {
    // don't bother if the first letter is already capital and the second is already lowercase
    // (to avoid messing up names such as MacDavid or LeAnn)
    if (strcmp(substr($name,0,2),strtoupper(substr($name,0,1)).strtolower(substr($name,1,1)))) {
      // obviously this part is imperfect (suffix search and special chars), but it's good enough for now
      if (strcasecmp($name,"II") && strcasecmp($name,"III") && strcasecmp($name,"IV")) {
        $name=strtoupper(substr($name,0,1)).strtolower(substr($name,1));
        foreach (array("'","-",".",",",":") as $char) {
          $pos=strpos($name, $char);
          if ($pos && $pos < strlen($name)-2) {
            $name=substr($name,0,$pos+1).strtoupper(substr($name,$pos+1,1)).substr($name,$pos+2);
          }
        }
      } else {
        $name=strtoupper($name);
      }
    }
    
    return $name;
  }
}

// storeWritein: add a single writein to the DB; returns NameID if successful or 0 if not
function storeWritein($orderNum,$nameid,$name,$street,$city,$state,$zip,$phone,$delivery,$seq) {
  global $db_link;
  $namedata = splitName($name);
  $deliver = ($delivery == "local")?1:0;
  
  if ($nameid) {
    // name was already matched to the database, so we need to update the existing entry
    $sql = "SELECT * FROM People WHERE NameID=".$nameid;
    $row = mysqli_fetch_assoc(mysqli_query($db_link,$sql));
    
    if ($row) {
      // find out if name has changed
      if (strcasecmp($namedata["first"]." ".$namedata["last"],$row["FirstNames"]." ".$row["LastName"])) {
        $last = capitalize($namedata["last"]);
        $first = capitalize($namedata["first"]);
        // move old name to official name fields
        $olast = $row["LastName"];
        $ofirst = $row["FirstNames"];
      } else {
        $last = $row["LastName"];
        $first = $row["FirstNames"];
        // keep previous entries for official name
        $olast = $row["OfficialLastName"];
        $ofirst = $row["OfficialFirstNames"];
      }
      
      // find out if address has changed
      if (strcasecmp($street,$row["StreetAddress"]) || strcasecmp($city,$row["City"])) {
        $mapsco = $route = "";
      } else {
        $mapsco = $row["Mapsco"];
        $route = $row["DeliveryRoute"];
      }

      // if phone number not supplied, use existing number
      if (!$phone) $phone = $row["PhoneNumber"];

      $sql="UPDATE People SET LastName='".addslashes($last)."',FirstNames='".addslashes($first)."',AndFamily=".$namedata["andFamily"].
        ",StreetAddress='".addslashes($street)."',City='".addslashes($city)."',State='".$state."',ZipCode='".$zip."',Mapsco='".$mapsco."',DeliveryRoute='".$route.
        "',PhoneNumber='".addslashes($phone)."',Delivery=".$deliver.",OfficialLastName='".addslashes($olast)."',OfficialFirstNames='".addslashes($ofirst).
        "' WHERE NameID=".$nameid;
    
      if (!mysqli_query($db_link,$sql)) {
        $nameid = "null";
        report_db_error($sql);
      }
    } else {  // could not find entry
      $nameid = "null";
      report_db_error($sql);
    }

  } else {  // new people entry
    // find out highest ID and add one
    $sql="SELECT NameID FROM People ORDER BY NameID DESC LIMIT 1";
    $row = mysqli_fetch_array(mysqli_query($db_link,$sql));
 
    if ($row) {
      $nameid = 1 + $row[0];
      $last = addslashes(capitalize($namedata["last"]));
      $first = addslashes(capitalize($namedata["first"]));
      
      // if phone number not supplied (for shipped basket), use blank entry (to be safe)
      if (!$phone) $phone="";

      $sql="INSERT INTO People (NameID,LastName,FirstNames,AndFamily,StreetAddress,City,State,ZipCode,Mapsco,DeliveryRoute,PhoneNumber,AltPhoneNumber,Delivery,OfficialLastName,OfficialFirstNames,Email)"
        ." VALUES (".$nameid.",'".$last."','".$first."',".$namedata["andFamily"].",'".addslashes($street)."','".addslashes($city)."','".$state."','".$zip."','','','".addslashes($phone)."','',".$deliver.",'".$last."','".$first."','')";
        
      if (!mysqli_query($db_link,$sql)) {
        $nameid = "null";
        report_db_error($sql);
      }
    } else {  // could not fetch a nameid
      $nameid = "null";
      report_db_error($sql);
    }
  }

  // store write-in entry (regardless of ability to update People table)
  $sql="INSERT INTO OrderWriteins VALUES (".$orderNum.",".$nameid.",'".addslashes($name)."','".
    addslashes($street)."','".addslashes($city)."','".$state."','".$zip."','".addslashes($phone)."','".$delivery."',".$seq.")";
  
  if (mysqli_query($db_link,$sql)) {
    return ($nameid == "null")?0:$nameid;
  } else {
    report_db_error($sql);
    return 0;
  }
}

// updatePeopleForOrder: update existing People table entry
function updatePeopleForOrder($nameid,$useOfficial,$customName,$andFamily,$phoneNumber,$email,$pickup) {
  global $db_link;

  $andFamily=($andFamily)?1:0;
  
  // determine where to place new phone number; also deal w/e-mail addresses
  $phone = $phoneNumber;
  $altPhone = "";
  $emailAddr = $email;
  $emailAddr2 = "";
  $mapsco = "";
  $city = "";
  $street = "";
  $row = mysqli_fetch_array(mysqli_query($db_link,"SELECT PhoneNumber,AltPhoneNumber,Email,Mapsco,City,StreetAddress,AndFamily,Staff,Email2 FROM People WHERE NameID=".$nameid));
 
  if ($row) {
    if ($row[0]) {
      if ($phoneNumber == "") {
        $phone = $row[0];
        if ($row[1]) $altPhone = $row[1];
      } elseif ($row[0] != $phoneNumber) {
        $altPhone = $row[0];  // shift old phone to alt phone
      } else {
        if ($row[1]) $altPhone = $row[1];    
      }
    } else {
      if ($row[1]) $altPhone = $row[1];
    }
    if (!$email && $row[2]) $emailAddr=$row[2];
    if ($row[8]) $emailAddr2=$row[8];
    if ($row[2] && strcasecmp($email,$row[2])) $emailAddr2=$row[2];   // email different from previous one on file -> shift right
    if ($row[3]) $mapsco=$row[3];
    $city = $row[4];
    $street = $row[5];
    if ($customName != "") {
      // need to decide whether to leave AndFamily alone (default behavior) or set it
      $andFamily=$row[6];
      if (stripos($customName," family") || stripos($customName," kids") || stripos($customName," children")) $andFamily=1;
    }
  }

  // build first update query based on selected name to use
  if ($useOfficial) {
    $sql="UPDATE People SET LastName=OfficialLastName,FirstNames=OfficialFirstNames,AndFamily=".$andFamily.
      ",PhoneNumber='".addslashes($phone)."',AltPhoneNumber='".addslashes($altPhone)."',Email='".$emailAddr."'";
  } else {
    $sql="UPDATE People SET AndFamily=".$andFamily.",PhoneNumber='".addslashes($phone)."',AltPhoneNumber='".addslashes($altPhone)."',Email='".$emailAddr."',Email2='".$emailAddr2."'";
  }
  $sql.=" WHERE NameID=".$nameid;

  if (!mysqli_query($db_link,$sql)) {
    report_db_error($sql);
    return false;
  }

  // build second update query if necessary for Mapsco
  if ($pickup) {
    if (strncasecmp("AT",$mapsco,2)) {
      // only update entry if not already delivering to Anshai Torah
      $sql="UPDATE People SET Delivery=1,Mapsco='AT',DeliveryRoute='AT' WHERE NameID=".$nameid;
      if (!mysqli_query($db_link,$sql)) {
        report_db_error($sql);
        return false;
      }
      
      // if existing entry has a Mapsco code, then save it for future reference
      if (strlen($mapsco) > 0) {
        $sql="INSERT INTO MapscoData VALUES ('".$city."','".$street."','".$mapsco."')";
        mysqli_query($db_link,$sql);  // no need to check for failure because entry may already exist
      }
    }
  } else {
    if (!strncasecmp("AT",$mapsco,2) && ($row[7]==0)) {
      // only clear the entry if previously set to deliver at Anshai Torah AND not a staff member
      $sql="UPDATE People SET Mapsco='',DeliveryRoute='' WHERE NameID=".$nameid;
      if (!mysqli_query($db_link,$sql)) {
        report_db_error($sql);
        return false;
      }
    }
  }

  return true;
}

// getAddress: returns full address for a person or empty string if not found
function getAddress($nameid) {
  global $db_link;

  $row = mysqli_fetch_array(mysqli_query($db_link,"SELECT StreetAddress,City,State,ZipCode FROM People WHERE NameID=".$nameid));

  if ($row) {
    return ($row[0].", ".$row[1].", ".$row[2]." ".$row[3]);
  } else {
    return "";
  }
}

// action_lname: handle AJAX request to lookup a last name
//
//   Note: This function assumes there are no member/associate/staff names in the DB containing a double quote.
//
function action_lname() {
  global $db_connect_err, $db_link;
  
  if (!connectDB()) {
    echo $db_connect_err;
    return;
  }
  
  $key = strtolower($_REQUEST["lname"]);
  
  $fnames=array();  // list of [nameid]=fname for exact matches
  $lnames=array();  // list of similar last names
  $pcts=array();    // list of percentages for similar last names
  $lastname = "";   // actual last name found
  $fuzzy = 0;       // if 0, exact last name match; otherwise fuzzy last name search
  
  $fields = "NameID,LastName,FirstNames";  // fields to fetch from DB
  $statuscheck = "(Status<>'Non-Member' OR Staff=1 OR Invited=1)";  // only members, associates, college students, and staff can place orders

  // first try an exact match on the last name (official or unofficial)
  $result = mysqli_query($db_link,"SELECT ".$fields." FROM People WHERE LastName='".addslashes($key)."' AND ".$statuscheck);

  // failing that, go ahead and pull the entire database to do an exhaustive fuzzy name match
  if (!mysqli_num_rows($result)) {
    $result = mysqli_query($db_link,"SELECT ".$fields." FROM People WHERE ".$statuscheck);
    $fuzzy = 1;
  }
  
  while ($row = mysqli_fetch_assoc($result)) {
    $lastname = $row['LastName'];
    $firstnames = $row['FirstNames'];
    $name_to_check = strtolower($lastname);

    if (!strcmp($name_to_check, $key)) {
      // found an exact match
      $nameid = $row['NameID'];
      $fnames[$nameid] = $firstnames;
    } elseif ((strlen($key) > 2) && !strncmp($name_to_check, $key, strlen($key))) {
      // see if the first part of the name matches (as long as there are at least 3 letters)
      array_push($lnames, $lastname);
      array_push($pcts, 100);  // treat as a good match
    } else {
      // if not exact match, see how close we are
      similar_text($name_to_check, $key, $percent);

      if ($percent >= 80) {  // bumped up from 60 to 80% to require more precise entries
        array_push($lnames, $lastname);
        array_push($pcts, $percent);
      }
    }
  }
  
  if (count($fnames) > 0) {
    // at least one exact match found - spit out the list of candidate first names
    echo '{"rc":"found","last":"'.$lastname.'", "names":[';
    asort($fnames);  // alphabetize the list
    reset($fnames);  // initialize iterator (may be overkill)

    $counter = 0;

    foreach ($fnames as $nameid => $name) {
      if ($counter++ > 0) echo ',';
      echo '{"id":"'.$nameid.'","name":"'.$name.'"}';
    }

    echo ']}';
    
  } elseif (count($lnames) > 0) {
    // multiple candidate last names found (no exact match)
    echo '{"rc":"choose","names":[';

    $uniq_lnames = array_unique($lnames);  // remove duplicates

    arsort($pcts);  // reverse sort percentages so that closest matches are listed first
    reset($pcts);   // initialize iterator
    
    $counter = 0;
    $names = array();

    foreach ($pcts as $index => $percent) {
      if (array_key_exists($index, $uniq_lnames)) {
        array_push($names, $uniq_lnames[$index]);
        if (++$counter >= 10) break;  // return only the top 10 matches
      }
    }

    reset($names);
    
    $counter = 0;

    foreach ($names as $name) {
      if ($counter++ > 0) echo ',';
      echo '"'.$name.'"';
    }

    echo ']}';
    
  } else {
    // none found :-(
    echo '{"rc":"none"}';
  }
}

// action_fname: handle AJAX request to select a person and gather all their personal info; return name for logging purposes
function action_fname() {
  global $db_connect_err, $db_link;
  
  if (!connectDB()) {
    echo $db_connect_err;
    return "";
  }

  $nameid = $_REQUEST["id"];

  // DMC: need to select specific fields for better security (may be difficult since e-mail is required for PIN reminder logic)
  $row = mysqli_fetch_assoc(mysqli_query($db_link,"SELECT * FROM People WHERE NameID=".$nameid));

  if ($row) {
    if (orderExists($nameid,date("Y"))) {
      echo '{"ordered":true';
    } else {
      echo '{"ordered":false';
      
      // check to see if there's an order from one of the past 2 years AND that there's an e-mail address on file
      if (orderExists($nameid,date("Y")-1) && $row['Email']) {
        echo ',"lastyear":'.(date("Y")-1);
      } elseif (orderExists($nameid,date("Y")-2) && $row['Email']) {
        echo ',"lastyear":'.(date("Y")-2);
      }
    }

    $counter = 0;

    // output all fields
    foreach ($row as $field => $value) {
      // spit out key and value, ensuring that double quotes are escaped
      echo ',"'.$field.'":"'.addcslashes($value,'"').'"';
    }

    // all done
    echo '}';
    
    return ($row['LastName'].", ".$row['FirstNames']);
  }   

  // theoretically we should never get here
  echo 'Unable to find matching name in database. Please reload and try again.';
  return "";
}

// action_lookup: handle AJAX request to find a person (usually non-member) and gather all their personal info
function action_lookup() {
  global $db_connect_err, $db_link;
  
  if (!connectDB()) {
    echo $db_connect_err;
    return;
  }

  $nameid = $_REQUEST["id"];
  
  echo '{"num":'.$_REQUEST["num"];  // echo back the reference num

  // DMC: need to select specific fields for better security (may be difficult since e-mail is required for PIN reminder logic)
  $row = mysqli_fetch_assoc(mysqli_query($db_link,"SELECT * FROM People WHERE NameID=".$nameid));

  if ($row) {
    echo ',"rc":true';  // reference number associated with this query (i.e. write-in #)
    
    // output all fields
    foreach ($row as $field => $value) {
      // spit out key and value, ensuring that double quotes are escaped
      echo ',"'.$field.'":"'.addcslashes($value,'"').'"';
    }

    // all done
    echo '}';
    
    return;
  }   

  // theoretically we should never get here
  echo ',"rc":false}';
}

// action_pin: handle AJAX request to validate PIN associated with existing order (current year); return success or failure
function action_pin() {
  global $db_connect_err, $db_link;
  
  if (!connectDB()) {
    echo $db_connect_err;
    return;
  }
  
  $nameid = $_REQUEST["id"];
  $admin = "";

  if (array_key_exists("pin", $_REQUEST)) {
    $pin = $_REQUEST["pin"];    
  } else {
    $pin = "";
    if (array_key_exists("admin", $_REQUEST)) $admin = $_REQUEST["admin"];
  }

  $year = date("Y");
  $match = 0;

  if ($admin == "") {
    $result = mysqli_query($db_link,"SELECT PIN FROM Orders WHERE NameID=".$nameid." AND Year=".$year);
  
    if ($result) {
      $row = mysqli_fetch_array($result);
      if ($row) {
        if (!strcmp($pin, $row[0])) $match = 1;
      }
    }
  } else {
    $result = mysqli_query($db_link,"SELECT Token FROM Admins WHERE Email='".$admin."'");
      
    if ($result) {
      $row = mysqli_fetch_array($result);
      if ($row) {
        if (isset($_COOKIE["admin_token"]) && !strcmp($row[0], $_COOKIE["admin_token"])) $match = 1;
      }
    }
  }

  echo '{"rc":';
  
  if ($match) {
    echo 'true';
    
    $row = mysqli_fetch_assoc(mysqli_query($db_link,"SELECT OrderNumber,AllMembers,Reciprocity,ExtraBaskets,ExtraDonation,Subtotal,PmtType,TotalPaid,Notes,CustomName,Driver,Volunteer,PhoneProvided"
      ." FROM Orders WHERE NameID=".$nameid." AND Year=".$year));

    $order = $row["OrderNumber"];
    
    // output all Order fields
    foreach ($row as $field => $value) {
      // replace newlines for Notes field only
      if ($field == "Notes") $value = preg_replace('/(\r\n?)|(\n\r?)/',' / ',$value);
      // spit out key and value, ensuring that double quotes are escaped
      echo ',"'.$field.'":"'.addcslashes($value,'"').'"';
    }

    $result = mysqli_query($db_link,"SELECT NameID,Reciprocity FROM OrderDetails WHERE OrderNumber=".$order);
    
    echo ',"names":[';
    
    if ($result) {
      $recip=array();
      $comma=0;
      while ($row = mysqli_fetch_assoc($result)) {
        if ($comma) {
          echo ',';
        } else {
          $comma=1;
        }
        echo $row["NameID"];
        array_push($recip,($row["Reciprocity"])?'true':'false');
      }
      
      echo '],"recip":[';
      
      $comma=0;
      foreach ($recip as $val) {
        if ($comma) {
          echo ','.$val;
        } else {
          echo $val;
          $comma=1;
        }
      }
    }
    
    echo '],"writeins":[';
    
    $result = mysqli_query($db_link,"SELECT * FROM OrderWriteins WHERE OrderNumber=".$order." ORDER BY Seq");
    
    if ($result) {
      $comma=0;
      while ($row = mysqli_fetch_assoc($result)) {
        if ($comma) {
          echo ',{';
        } else {
          echo '{';
          $comma=1;
        }

        $comma2=0;

        // output all Writein fields
        foreach ($row as $field => $value) {
          if ($comma2) {
            echo ',';
          } else {
            $comma2=1;
          }
          // spit out key and value, ensuring that double quotes are escaped
          echo '"'.$field.'":"'.addcslashes($value,'"').'"';
        }

        echo '}';
      }
    }
    
    echo ']}';
    return true;
    
  } else {  // no match (invalid PIN)
    echo 'false}';
    return false;
  }
}

// action_remind: handle AJAX request to e-mail PIN; return success or failure
function action_remind() {
  global $mail_from, $mail_help, $web_addr, $db_connect_err, $db_link;

  if (!connectDB()) {
    echo $db_connect_err;
    return;
  }
  
  $result = mysqli_query($db_link,"SELECT LastName,FirstNames,Email,o.OrderNumber AS Num,o.PIN AS Pin"
    ." FROM People INNER JOIN Orders AS o ON People.NameID=o.NameID WHERE o.NameID=".$_REQUEST["id"]." AND o.Year=".date("Y"));
  
  if ($result) {
    $row = mysqli_fetch_assoc($result);

    if ($row) {
      $lname = $row["LastName"];
      $fname = $row["FirstNames"];
      $full_addr = $web_addr."?open=".$_REQUEST["id"].".".$row["Pin"];

      if (array_key_exists("email", $_REQUEST)) {
        // order is being loaded by an admin
        $to = $_REQUEST["email"];
        $subject = 'Shalach Manot PIN for '.$fname.' '.$lname;
        $body = "<html><body><p>The PIN for this order is <b>".$row["Pin"]."</b>.</p>";
        $body.= "<p>Return to <a href='".$full_addr."?admin=".$_REQUEST["email"]."'>".$full_addr."</a> to view/edit order #".$row["Num"].".</p>";
      } else {
        if ($row["Email"]) {
          $to = '"'.$fname.' '.$lname.'" <'.$row["Email"].'>';
          $subject = "Shalach Manot Personal Identification Number";
          $body = "<html><body><p>Your PIN is <b>".$row["Pin"]."</b>.</p>";
          $body.= "<p>Return to <a href='".$full_addr."'>".$full_addr."</a> to view/edit your order (#".$row["Num"].").</p>";
        } else {
          echo '{"rc":false}';
          return false;
        }
      }
      $headers = "MIME-Version: 1.0\r\n";
      $headers.= "Content-type: text/html;charset=iso-8859-1\r\n";
      $headers.= "From: ".$mail_from."\r\n";
      $headers.= "Reply-To: ".$mail_help."\r\n";
      $body.= "</body></html>\r\n";

      if (send_email($to, $subject, $body, $headers)) {
        echo '{"rc":true}';
        return true;
      } else {
        echo '{"rc":false}';
        return false;
      }
    }
  }

  echo '{"rc":false}';  
  return false;
}

// action_remind: handle AJAX request to e-mail link to preload prior year's order (going 1 or 2 years back)
function action_sendlink() {
  global $mail_from, $mail_help, $web_addr, $db_connect_err, $db_link;

  if (!connectDB()) {
    echo $db_connect_err;
    return;
  }

  $result = mysqli_query($db_link,"SELECT LastName,FirstNames,Email,Email2,o.PIN AS Pin"
    ." FROM People INNER JOIN Orders AS o ON People.NameID=o.NameID WHERE o.NameID=".$_REQUEST["id"]." AND o.Year < ".date("Y")
    ." ORDER BY o.Year DESC");
  
  if ($result) {
    $row = mysqli_fetch_assoc($result);

    if ($row) {
      $lname = $row["LastName"];
      $fname = $row["FirstNames"];

      if ($row["Email"]) {
        $to = '"'.$fname.' '.$lname.'" <'.$row["Email"].'>';
        if ($row["Email2"]) {
            $to .= ', "'.$fname.' '.$lname.'" <'.$row["Email2"].'>';
        }
        $fulladdr = $web_addr."?preload=".$_REQUEST["id"].".".$row["Pin"];
        $subject = 'Shalach Manot PIN for '.$fname.' '.$lname;
        $body = "<html><body><p>The PIN for your previous order was <b>".$row["Pin"]."</b>.</p>";
        $body.= "<p>You can use the link below to access your personalized Shalach Manot order form.";
        $body.= " We will pre-populate it with information from your last order. You won't need to enter your PIN if you click the link.</p>";
        $body.= "<p><a href='".$fulladdr."' title='Your personalized Shalach Manot order form'>".$fulladdr."</a></p>";
        $headers = "MIME-Version: 1.0\r\n";
        $headers.= "Content-type: text/html;charset=iso-8859-1\r\n";
        $headers.= "From: ".$mail_from."\r\n";
        $headers.= "Reply-To: ".$mail_help."\r\n";
        $body.= "</body></html>\r\n";

        if (send_email($to, $subject, $body, $headers)) {
          echo '{"rc":true}';
          return true;
        }
      }
    }
  }

  echo '{"rc":false}';  
  return false;
}

// output_list: generate HTML for one of the 4 checklists (members, associates, college kids, or staff)
function output_list($heading, $namelist) {
  $columns = 3;
  $count = 0;

  echo "<div><h2>".$heading."</h2><div class='namelist'>";

  reset($namelist);  // initialize iterator

  foreach ($namelist as $nameid => $name) {
      if ($count % $columns == 0) {
        // start of new row
        if ($count > 0) {
          echo "</div><div>";  // row end/start
        } else {
          echo "<div>";  // row start (first row)
        }
      }

      echo "<div class='col'><div class='nam'>";
      echo "<input type='checkbox' id='namebox_".$nameid."' name='name_".$nameid."' value='".$name."' onclick='blur(this);' onchange='name_select(this);'/> ";
      echo "<label id='label4namebox_".$nameid."' for='namebox_".$nameid."' title='".$name."'>".htmlspecialchars($name)."</label></div></div>";

      $count++;
  }

  // this next part is necessary to avoid a formatting problem in Firefox (which I consider to be a bug)
  if ($count % $columns > 0) {
    while ($count++ % $columns > 0) echo "<div class='col'><br/><br/></div>";
    echo "</div></div></div>";
  } else {
    echo "</div><p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</p></div></div>";
  }
}

// action_list: handle AJAX request to return HTML-formatted checklists
function action_list() {
  if (connectDB()) {

    echo '{"output":"';
  
    $namelist = getNames("Member");
    output_list("Members", $namelist);

    $namelist = getNames("Associate");
    output_list("Associate Members", $namelist);
    
    $namelist = getNames("College");
    output_list("College Students &amp Military", $namelist);

    $namelist = getNames("Non-Member", TRUE);
    output_list("Teachers &amp Staff (non-members only)", $namelist);

    echo '"}';
  }
}

// array_concat: helper function to concatenate arrays of strings with a space between each element
function array_concat($a, $start=0, $length=0) {
  if ($length) {
    $limit = $length;
  } else {
    $limit = count($a) - $start;
  }

  for ($i=0; $i < $limit; ++$i) {
    if ($i > 0) {
      $str.=" ".$a[$start+$i];
    } else {
      $str = $a[$start];
    }
  }

  return $str;
}

// action_writein: handle AJAX request to validate/lookup a write-in name
function action_writein() {
  global $db_link;

  if (!connectDB()) return;  // not sure what else can be done

  $keys = 1;
  $key[0] = strtolower(stripslashes($_REQUEST["name"]));  // first search string is the name provided

  $names = explode(" ", $key[0]);
  $namecount = count($names);

  // if name ends with "and family" remove it
  if ($namecount > 2 && !strcmp("family", $names[$namecount-1]) && !strcmp("and", $names[$namecount-2])) {
    $names = array_slice($names, 0, $namecount-2);
    $namecount -= 2;
    $key[0] = array_concat($names);
  }

  // if name is "XYZ family" or "the XYZ family" add another search string that includes or excludes the "the"
  if (!strcmp("family", $names[$namecount-1])) {
    if (strcmp("the", $names[0])) {
      $key[$keys] = "the ".array_concat($names);
    } else {
      $key[$keys] = array_concat($names, 1);
    }
    $keys++;
  }

  $names2 = explode(", ", $key[0]);
  
  // if name is of the format "Y, X" add another search string that reverses the name to "X Y"
  if (count($names2) == 2) {
    $key[$keys] = $names2[1]." ".$names2[0];
    $names = explode(" ", $key[$keys]);
    $namecount = count($names);
    $keys++;
  }

  // if name is of the format "A & B C" add another search string for "B & A C"
  $pos = array_search("&", $names, true);
  if ($pos && $pos > 0 && $pos < $namecount-1) {
    $key[$keys] = array_concat($names, $pos+1, 1)." & ".array_concat($names, 0, $pos);
    if ($pos+2 < $namecount) $key[$keys].=" ".array_concat($names, $pos+2);
    $keys++;
  }

  // begin unconditional output
  echo '{"num":'.$_REQUEST["num"].',"name":"'.addcslashes(stripslashes($_REQUEST["name"]),'"').'"';

  // try each of the keys to see if it matches the name
  for ($i=0; $i < $keys; ++$i) {
    // revise the key to remove the following characters: . , ' &
    // also replace hyphens with spaces
    $new_key = str_replace("& ","",str_replace("'","",str_replace(",","",str_replace(".","",str_replace("-"," ",$key[$i])))));

    // try to find a match; remove/replace 5 punctuation marks to improve chances of a match
    $result = mysqli_query($db_link,"SELECT NameID,LastName,FirstNames,StreetAddress,City,State,ZipCode,PhoneNumber,AltPhoneNumber,Status,Staff,Delivery FROM People ".
      "WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CONCAT(FirstNames,' ',LastName),'-',' '),'.',''),',',''),'\'',''),'& ','')".
      "='".addslashes($new_key)."'");

    if ($result) {
      $row = mysqli_fetch_assoc($result);
      if ($row) {
        // found a match!  output all relevant data fields for the name entry
        foreach ($row as $field => $value) {
          echo ',"'.$field.'":"'.addcslashes($value,'"').'"';
        }
        echo ',"rc":"found"}';
        return;
      }
    }
  }

  // not found
  echo ',"rc":"none"}';
}

// emailRecips: send emails to anyone receiving baskets who hasn't yet ordered or been notified
//              does not include college kids or non-members
function emailRecips($order_num) {
  global $mail_from, $mail_help, $mail_bcc_list, $deadline, $web_addr, $chair_name, $chair_email, $db_link;
  
  $year = date("Y");

  $sql = "SELECT DISTINCT People.NameID,People.FirstNames,People.LastName,People.Email,People.Email2 FROM People JOIN OrderDetails ON People.NameID=OrderDetails.NameID ";
  $sql.= "JOIN Orders AS o1 ON o1.OrderNumber=OrderDetails.OrderNumber LEFT JOIN Orders AS o2 ON (o2.NameID=OrderDetails.NameID AND o2.Year=".$year.") ";
  $sql.= "JOIN People AS po ON o1.NameID=po.NameID ";
  $sql.= "LEFT JOIN EmailSent ON (EmailSent.NameID=OrderDetails.NameID AND EmailSent.Year=".$year.") ";
  $sql.= "WHERE o1.OrderNumber=".$order_num." AND EmailSent.NameID IS NULL AND o2.OrderNumber IS NULL AND ";
  $sql.= "(People.Status='Member' OR People.Status='Associate' OR People.Staff=1) AND People.Email IS NOT NULL AND People.Email<>'' AND ";
  $sql.= "(po.Status<>'Non-Member' OR po.Staff=1)";

  $subject = "A personal message from the Anshai Torah Shalach Manot program";
  $headers = "MIME-Version: 1.0\r\n";
  $headers.= "Content-type: text/html;charset=iso-8859-1\r\n";
  $headers.= "From: ".$mail_from."\r\n";
  $headers.= "Reply-To: ".$mail_help."\r\n";
  $headers.= "Bcc: ".$mail_bcc_list."\r\n";  // TODO - remove this once confident it's working correctly
  $body1   = "<html><head><title>Anshai Torah Shalach Manot</title></head>\r\n";
  $body1  .= "<body style='font-family:Georgia,Times,serif;font-size:11pt;color:black;'>\r\n";
  $body3   = "<p><i>Someone at Congregation Anshai Torah has sponsored a Purim basket for you!</i></p>\r\n";
  $body3  .= "<p>Go to <a href='".$web_addr."'><b>".$web_addr."</b></a> to sponsor baskets for your friends and family. &nbsp;";
  $body3  .= "Select the &quot;Reciprocity&quot; option to ensure you return the favor for anyone who sponsors yours. &nbsp;";
  $body3  .= "Ordering deadline is ".$deadline.". &nbsp;You'll receive your basket in time for Purim.</p>\r\n";
  $body3  .= "<p>If you have any questions about the Shalach Manot program, please contact ".$chair_name." at ";
  $body3  .= "<a href='mailto:".$chair_email."'>".$chair_email."</a>.</p>\r\n";
  $body3  .= "</body></html>";

  $result = mysqli_query($db_link,$sql);
 
  if ($result) {
    while ($row = mysqli_fetch_array($result)) {
      $to = '"'.$row[1]." ".$row[2].'" <'.$row[3].'>';
      if ($row[4]) $to .= ',"'.$row[1]." ".$row[2].'" <'.$row[4].'>';
      $body = $body1."<p>".$row[1].",</p>\r\n".$body3;
      
      if (send_email($to, $subject, $body, $headers)) {
        mysqli_query($db_link,"INSERT INTO EmailSent VALUES (".$row[0].",".$year.",CURRENT_TIMESTAMP)");
      } 
    }
  }
}

// form_submit: handle form submission - create order e-mail and confirmation
function form_submit() {
  global $price_basket, $price_nm_local, $price_nm_ship, $price_benefactor, $price_extra;
  global $mail_from, $mail_help, $mail_orders, $mail_bcc_list, $mail_benefactors, $mail_drivers, $mail_recipients, $mail_changes;
  global $web_addr, $admin_user, $admin_name, $extra_baskets, $assembly_date;
  global $db_link;
  
  $rc = connectDB();
  loadAdminData();

  $order_data = 0;  // indicates this is a new order unless set to an array
  
  if (array_key_exists("order_num", $_REQUEST) && strlen($_REQUEST["order_num"]) > 0) {
    $order_num = $_REQUEST["order_num"];
    $order_data = getOrderData($order_num);
    if ($order_data) {
      $pin = $order_data["PIN"];
    } else {
      // should not happen, but let's handle it as gracefully as possible
      $order_num = getOrderNumber();
      $pin = generatePIN(4);
    }
  } else {
    // check for a double submit (repost of form data for new order)
    if (orderExists($_REQUEST["member"],date("Y"))) {
      echo "<html><body><h2>Error</h2><p>It appears that you tried to submit the same Shalach Manot order twice. ";
      echo "If your original order was accepted, you should receive a confirmation e-mail at ".$_REQUEST["email"].".</p>";
      echo "<p><a href='".$web_addr."'>Return to Shalach Manot home page</a></p>";
      echo "</body></html>";
      return;
    }

    $order_num = getOrderNumber();  // assign an order number (sequential)
    $pin = generatePIN(4);
  }

  $fname = stripslashes($_REQUEST["firstname"]);
  $lname = stripslashes($_REQUEST["lastname"]);
  $ofname = stripslashes($_REQUEST["ofname"]);
  $olname = stripslashes($_REQUEST["olname"]);

  $appear = $_REQUEST["appear"];
  $nameformat = stripslashes($_REQUEST["nameformat"]);
  $custom_name = "";
  $recipients = 0;
  $sponsor = array_key_exists("benefactor",$_REQUEST) && ($_REQUEST["benefactor"] == 1);
  $reciprocity = false;
  $extra_count = ((array_key_exists("extra",$_REQUEST))?(int)$_REQUEST["extra"]:0);
  $nameid_list = array();

  // begin HTML confirmation (for e-mail and display)
  $cnf="<html><head><title>Shalach Manot Order Confirmation</title></head>\r\n";
  $cnf.="<body style='font-family:Georgia,Times,serif;font-size:11pt;color:black;background-color:#f0f0a0'>";
  $cnf.="<p style='font-weight:bold;font-size:15pt;margin:.75em 0;color:black;background-color:#8080c0;padding:4px'>Congregation Anshai Torah<br/>Shalach Manot Order Confirmation</p>\r\n";
  if ($order_data) {
    $cnf.="<p><i><b>Note:</b> This replaces your previous order(s) for this year.</i></p>";
  }
  $cnf.="<p>Here are the details of your order. Use <a href='".$web_addr."?open=".$_REQUEST["member"].".".$pin."'>this link</a> to view or modify your order. ";
  $cnf.="If you have any questions or concerns, please e-mail Dan Cohn at ";
  $cnf.="<a href='mailto:".$mail_help."'>".$mail_help."</a> or call me at 214-405-3044.</p>\r\n";
  
  // begin order submission e-mail body
  $msg="SHALACH MANOT ORDER\r\nsubmitted ".date(DATE_RFC822).(($admin_user!="")?(" by ".$admin_name):"")."\r\n\r\n";
  $msg.="ORDER #".$order_num.(($order_data)?" *existing*":"")."\r\n";
  $msg.="NAME:  ".$lname.", ".$fname." [".$_REQUEST["member"]."]\r\n";
  if ($_REQUEST["email"] != "") {
    $msg.="EMAIL: ".$_REQUEST["email"];
    if (strcasecmp($_REQUEST["email"], $_REQUEST["old_email"])) $msg.=" *new*";
    $msg.="\r\n";
  }
  if ($_REQUEST["phone"] != "") {
    $msg.="PHONE: ".$_REQUEST["phone"];
    if (strcasecmp($_REQUEST["phone"], $_REQUEST["old_phone"])) $msg.=" *new*";
    $msg.="\r\n";
  }

  $msg.="PIN: ".$pin."\r\n\r\nName to appear ";

  if ($appear=="name") {
    $msg.="WITHOUT and Family: ";
  } elseif ($appear=="nameAndFamily") {
    $msg.="with AND FAMILY: ";
  } elseif ($appear=="official") {
    $msg.="in OFFICIAL format: ";
  } elseif ($appear=="officialAndFamily") {
    $msg.="in OFFICIAL format with AND FAMILY: ";
  } else {
    $msg.="in CUSTOM format: ";
    $custom_name = $nameformat;
  }

  $msg.=$nameformat."\r\n\r\n";

  $cnf.="<p style='background-color:#cccccc;padding:2px'><b>Order #:</b> ".$order_num."<br/>";
  $cnf.="<b>Name to appear as:</b> ".htmlspecialchars($nameformat)."<br/>";
  $cnf.="<b>PIN:</b> ".$pin."</p>\r\n";
  
  if (!$sponsor) {
    $reciprocity = array_key_exists("reciprocity", $_REQUEST) && ($_REQUEST["reciprocity"] == 1);

    $msg.="RECIPROCITY: ".($reciprocity?"Yes":"No")."\r\n";
    $msg.="BENEFACTOR: No\r\n\r\n";
    
    if ($reciprocity) {
      $cnf.="<p><b>Reciprocity selected.</b> We will automatically sponsor baskets for anyone who has sponsored one for you. ";
      $cnf.="You will be billed for any additional baskets added for reciprocity. ";
      $cnf.="If you do not want reciprocity, please tell us to remove it or edit your order online.</p>\r\n";
    } else {
      if ($_REQUEST["invited"] != "1") {
        $cnf.="<p>No reciprocity selected.</p>\r\n";
      }
    }

    $msg.="RECIPIENTS:\r\n";

    $cnf.="<p style='font-weight:bold;font-size:12pt;margin:.83em 0'>Names selected from the membership/staff list to receive baskets:</p>\r\n<ol>";

    // search through the post data to find names selected from the checklist
    foreach ($_REQUEST as $key => $value) {
      if (!strncmp($key, "name_", 5)) {
        array_push($nameid_list, substr($key,5));
        $msg.="   ".$value." [".substr($key,5)."]\r\n";
        $cnf.="<li>".htmlspecialchars(stripslashes($value))."</li>\r\n";
        ++$recipients;
      }
    }
    
    $cnf.="</ol>\r\n";

    if ($recipients == 0) {
      $cnf.="<ul style='list-style-type:none'><li>None</li></ul>\r\n";
    }

  } else {
    $msg.="BENEFACTOR: Yes\r\n\r\n";
    $msg.="RECIPIENTS:\r\n";  // header for write-ins
    
    $cnf.="<p>Thank you for choosing to be a <b>Shalach Manot Benefactor</b>!</p>";
  }

  // look for non-member write-ins
  for ($i=1; $i <= (int)$_REQUEST["nm_count"]; ++$i) {
    if (strlen($_REQUEST["nm_name_".$i]) > 0) {  // make sure name is not blank (skip blank ones)
      if ($_REQUEST["nm_id_".$i]) {  // output NameID if one exists
        $msg.="   ".stripslashes($_REQUEST["nm_name_".$i])." [".$_REQUEST["nm_id_".$i]."]\r\n";
      } else {
        $msg.="   ".stripslashes($_REQUEST["nm_name_".$i])."\r\n";
      }

      ++$recipients;
    }
  }
  
  if ($recipients == 0) {
    $msg.="   -none-\r\n";
  }
  
  $msg.="\r\nNON-MEMBERS:\r\n";

  // initialize counters
  $recipients = $local_count = $ship_count = $total = 0;

  for ($i=1; $i <= (int)$_REQUEST["nm_count"]; ++$i) {
    if (strlen($_REQUEST["nm_name_".$i]) > 0) {
      if ($recipients == 0) $cnf .= "<p style='font-weight:bold;font-size:12pt;margin:.83em 0'>Non-member baskets ordered:</p>\r\n";

      ++$recipients;

      $local = ($_REQUEST["nm_type_".$i] == "local");

      $msg.="\r\n   ".$recipients.". ".stripslashes($_REQUEST["nm_name_".$i])."\r\n";
      $msg.="      ".stripslashes($_REQUEST["nm_addr_".$i])."\r\n      ".stripslashes($_REQUEST["nm_city_".$i]).", ".$_REQUEST["nm_state_".$i]."  ".$_REQUEST["nm_zip_".$i]."\r\n";
      if (array_key_exists("nm_phone_".$i,$_REQUEST) && strlen($_REQUEST["nm_phone_".$i])>0) $msg.="      ".$_REQUEST["nm_phone_".$i]." (only relevant for local delivery)\r\n";
//      $msg.="      DELIVER: ".($local?"Yes":"No (shipped basket)")."\r\n";
      
      $cnf.="<p>".$recipients.". ".($local?"Local delivery to ":"Deliver to ")."\"".htmlspecialchars(stripslashes($_REQUEST["nm_name_".$i]))."\" at ";
      $cnf.=htmlspecialchars(stripslashes($_REQUEST["nm_addr_".$i])).", ".stripslashes($_REQUEST["nm_city_".$i]).", ".$_REQUEST["nm_state_".$i]." ".$_REQUEST["nm_zip_".$i];
      if ($local) $cnf.=" (phone ".htmlspecialchars(stripslashes($_REQUEST["nm_phone_".$i])).")</p>\r\n";

      if ($local) {
        ++$local_count;
      } else {
        ++$ship_count;
      }
    }
  }

  if ($recipients == 0) {
    $msg.="   -none-\r\n";
  }

  if (array_key_exists("pay",$_REQUEST)) {
    $pmtType=$_REQUEST["pay"];
    $msg.="\r\nPAYMENT METHOD: ".$pmtType."\r\n\r\n";
  } else {
    $msg.="\r\n";
  }
  
  if (array_key_exists("notes",$_REQUEST) && (strlen($_REQUEST["notes"]) > 0)) {
    $notes=stripslashes($_REQUEST["notes"]);
    $cnf.="<p style='background-color:#cccccc;padding:2px'><b>Notes or special instructions:</b><br/>".htmlspecialchars($notes)."</p>\r\n";
  } else {
    $notes="";
  }

  $driver = array_key_exists("driver", $_REQUEST) && ($_REQUEST["driver"] == 1);
  $volunteer = array_key_exists("volunteer", $_REQUEST) && ($_REQUEST["volunteer"] == 1);
  $pickup = !$extra_baskets && array_key_exists("pickup", $_REQUEST) && ($_REQUEST["pickup"] == 1);
  
  if ($pickup) {
    $msg.="** SELF PICKUP **\r\n\r\n";
  }

  if ($volunteer) {
    $msg.="** ASSEMBLY VOLUNTEER **\r\n";
    if (!$driver) $msg.="\r\n";
    $cnf.="<p>Thank you for volunteering to help with basket assembly on ".$assembly_date."! Please arrive at 9 AM in the social hall.</p>\r\n";
  }

  if ($driver) {
    $msg.="** DELIVERY VOLUNTEER **\r\n\r\n";
    $cnf.="<p>Thank you for volunteering to help with deliveries! We will contact you with details.</p>\r\n";
  }

  $msg.="SUMMARY:\r\n\r\n";
  $cnf.="<p style='font-weight:bold;font-size:12pt;margin:.83em 0'>Order summary:</p>\r\n";
  $cnf.="<table border='0' style='font-size:11pt;border-spacing:2px;border:1px solid #808080;border-radius:8px;padding:2px'>\r\n";
  $cnf.="<tr><td></td><td style='padding:1px 3px;font-weight:bold'>Quantity</td><td style='padding:1px 3px;font-weight:bold'>Price</td><td style='padding:1px 3px;font-weight:bold'>Subtotal</td></tr>\r\n";
  
  if ($sponsor) {
    $msg.="   Benefactor = $".$price_benefactor.".00\r\n";
    $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Shalach Manot Benefactor</b></td><td style='text-align:right;padding:1px 3px;background-color:white'>1</td><td style='text-align:right;padding:1px 3px;background-color:white'>".$price_benefactor.".00</td>";
    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>$".$price_benefactor.".00</td></tr>\r\n";
    $total = $price_benefactor;
  } else {
    $msg.="   Names from list x ".$_REQUEST["mem_count"]." = $".sprintf("%.2f",$price_basket*$_REQUEST["mem_count"])."\r\n";
    $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Names from membership list</b></td><td style='text-align:right;padding:1px 3px;background-color:white'>".$_REQUEST["mem_count"]."</td>";
    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>".sprintf("%.2f",$price_basket)."</td>";
    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>$".sprintf("%.2f",$price_basket*$_REQUEST["mem_count"])."</td></tr>\r\n";
    $total = $price_basket * $_REQUEST["mem_count"];
  }

//  $msg.="   Non-member deliveries x ".$local_count." = $".sprintf("%.2f",$local_count*$price_nm_local)."\r\n";
  $msg.="   Non-member orders x ".$ship_count." = $".sprintf("%.2f",$ship_count*$price_nm_ship)."\r\n";
  
//  if ($local_count > 0) {
//    $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Baskets for local non-members</b></td><td style='text-align:right;padding:1px 3px;background-color:white'>".$local_count."</td>";
//    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>".sprintf("%.2f",$price_nm_local)."</td>";
//    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>$".sprintf("%.2f",$local_count*$price_nm_local)."</td></tr>";
//  }

  if ($ship_count > 0) {
    $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Baskets for non-members</b></td><td style='text-align:right;padding:1px 3px;background-color:white'>".$ship_count."</td>";
    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>".sprintf("%.2f",$price_nm_ship)."</td><td style='text-align:right;padding:1px 3px;background-color:white'>$".sprintf("%.2f",$ship_count*$price_nm_ship)."</td></tr>\r\n";
  }

  if ($extra_count > 0) {
    $msg.="   Extra baskets x ".$extra_count." = $".sprintf("%.2f",$extra_count*$price_extra)."\r\n";
    $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Extra baskets (for pickup)</b></td><td style='text-align:right;padding:1px 3px;background-color:white'>".$extra_count."</td>";
    $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>".sprintf("%.2f",$price_extra)."</td><td style='text-align:right;padding:1px 3px;background-color:white'>$".sprintf("%.2f",$extra_count*$price_extra)."</td></tr>\r\n";
  }

  $total += $local_count * $price_nm_local + $ship_count * $price_nm_ship + $extra_count * $price_extra;
  $subtotal = $total;

  if (array_key_exists("donation", $_REQUEST) && $_REQUEST["donation"] != 0) {
    $donation=$_REQUEST["donation"];
    $msg.="   SUBTOTAL = $".sprintf("%.2f",$subtotal)."\r\n";
    if ($donation > 0) {
      $msg.="   Extra donation = $".sprintf("%.2f",$donation)."\r\n";
      $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Additional donation</b></td><td>&nbsp;</td><td style='text-align:right;padding:1px 3px;background-color:white'>".sprintf("%.2f",$donation)."</td>";
      $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>$".sprintf("%.2f",$donation)."</td></tr>\r\n";
    } else {
      $msg.="   Price adjustment = -$".sprintf("%.2f",$donation)."\r\n";
      $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Price adjustment</b></td><td>&nbsp;</td><td style='text-align:right;padding:1px 3px;background-color:white'>".sprintf("%.2f",$donation)."</td>";
      $cnf.="<td style='text-align:right;padding:1px 3px;background-color:white'>-$".sprintf("%.2f",-$donation)."</td></tr>\r\n";
    }
    $total += (float)$donation;
  } else {
    $donation = 0;
  }

  $paid = 0;

  // pull total paid out of order (for pre-existing order)
  if ($order_data && $order_data["TotalPaid"] && strlen($order_data["TotalPaid"]) > 0) {
    $paid = (float)$order_data["TotalPaid"];
  }
 
  // read total paid from submission and possibly overwrite amt previously paid
  if (array_key_exists("total_paid",$_REQUEST) && strlen($_REQUEST["total_paid"]) > 0) {
    $paid = (float)$_REQUEST["total_paid"];
  }
  
  if (($paid != 0) || ($pmtType != "check")) {
    $msg.="   Already paid = $".sprintf("%.2f",$paid)."\r\n";
    $cnf.="<tr><td style='padding:1px 3px;font-family:Georgia,Times,serif'><b>Amount paid</b></td><td>&nbsp;</td><td style='text-align:right;padding:1px 3px;background-color:white'><span id='paid1'>".sprintf("%.2f",$paid)."</span></td><td style='text-align:right;padding:1px 3px;background-color:white'>-$<span id='paid2'>".sprintf("%.2f",$paid)."</span></td></tr>\r\n";
  }

  $cnf.="</table>\r\n";

  $due=$total-$paid;
  $pmt_url = $web_addr."/payment.php?method=".$pmtType."&due=".sprintf("%.2f",$due);
  $msg.="   TOTAL = $".sprintf("%.2f",$due)."\r\n\r\n";

  if ($due > 0) {
    $cnf.="<p><b>Total due: <span style='background-color:white'> $<span id='total'>".sprintf("%.2f",$due)."</span></span></b></p>\r\n";
  } elseif ($due < 0) {
    $cnf.="<p><b>Balance: <span style='background-color:white'> -$<span id='total'>".sprintf("%.2f",-$due)."</span></span></b></p>\r\n";
  } else {
    $cnf.="<p><b>Balance: <span style='background-color:white'> $<span id='total'>0.00</span></span></b></p>\r\n";
  }

  if (strlen($notes) > 0) {
    $msg.="NOTES:\r\n".$notes."\r\n";
  }

  if ($pickup) {
    $cnf.="<p>You have selected <b>self pickup</b>. We'll e-mail you details on picking up your basket at the shul before Purim.</p>\r\n";
  }

  if ($total > $paid) {
    $cnf.="<div id='payment'><p>You have chosen to pay your balance by <b>";

    if ($pmtType == "check") {
      $cnf.="check</b>.</p>\r\n<p style='background-color:#cccccc;padding:2px'><span style='color:red;font-weight:bold;font-size:14pt'>Please mail your payment today to:</span><br/>";
      $cnf.="&nbsp;&nbsp;&nbsp;Congregation Anshai Torah<br/>";
      $cnf.="&nbsp;&nbsp;&nbsp;5501 W. Parker Road<br/>";
      $cnf.="&nbsp;&nbsp;&nbsp;Plano, TX 75093</p>\r\n";
    } elseif ($pmtType == "echeck") {
      $cnf.="electronic check</b>.</p><p style='background-color:#cccccc;padding:2px'><span style='font-weight:bold;font-size:14pt'>You'll receive a separate email confirming your ShulCloud eCheck payment.</span><br/>If for some reason you're unable to pay online, please mail your payment to Congregation Anshai Torah, 5501 W. Parker Road, Plano, TX 75093.</p>\r\n";
    } else { // credit
      $cnf.="credit card</b>.</p><p style='background-color:#cccccc;padding:2px'><span style='font-weight:bold;font-size:14pt'>You'll receive a separate email confirming your ShulCloud credit card payment.</span><br/>If for some reason you're unable to pay online, please call the shul office during daytime hours at 972-473-7718 to provide your credit card information and amount to be charged.</p>\r\n";
    }
    
    if ($pmtType != "check" ) {
      // if paying online, assume that payment has been completed (from the standpoint of total amount paid)
      // only applicable when new balance is more than what was previously paid; do not decrease $paid
      $paid = $total;

      $cnf.="<p>If you haven't paid yet, click <a href='".$pmt_url."'>here</a> for the online payment page.</p>\r\n";
    }
    $cnf.="</div>\r\n";
  }
  
  if ($total > 0) {
    $cnf.="<p> </p><p style='font-size:12pt;font-weight:bold;font-style:italic;color:black;'>Thank you for your order!</p><p> </p>\r\n";
  }

  // clear PmtConfirmed flag if additional amount is due and this is an update
  $clrPmtConf = ($order_data && $due > 0);

  // save order in database
  $andFamily = ($appear=="nameAndFamily") || ($appear=="officialAndFamily");

  $rc=updatePeopleForOrder($_REQUEST["member"],(($appear=="official")||($appear=="officialAndFamily")),$custom_name,$andFamily,$_REQUEST["phone"],$_REQUEST["email"],$pickup) && $rc;
  $so=storeOrder(is_array($order_data),$order_num,date("Y"),$_REQUEST["member"],$_REQUEST["status"],$_REQUEST["staff"],$sponsor,$reciprocity,$extra_count,$donation,$subtotal,$pmtType,$paid,$notes,$custom_name,$driver,$volunteer,$_REQUEST["phone"],$pin,$clrPmtConf);

  $rc=$rc && $so;

  if ($so) {

      $recip_names = array();
      
      if ($order_data) {
        // find out if any names were previously added due to reciprocity (so we can remember it)
        $recip_names = getRecipNames($order_num);
        // clear out previous order details & writeins
        $rc = clearOrderDetails($order_num) && $rc;
      }

      for ($i=1; $i <= (int)$_REQUEST["nm_count"]; ++$i) {
        if (strlen($_REQUEST["nm_name_".$i]) > 0) {
           $writein = storeWritein($order_num,$_REQUEST["nm_id_".$i],stripslashes($_REQUEST["nm_name_".$i]),stripslashes($_REQUEST["nm_addr_".$i]),
             stripslashes($_REQUEST["nm_city_".$i]),$_REQUEST["nm_state_".$i],$_REQUEST["nm_zip_".$i],$_REQUEST["nm_phone_".$i],$_REQUEST["nm_type_".$i],$i);

           if ($writein) {
             array_push($nameid_list, $writein);
           } else {
             $rc = false;
           }
        }
      }

      $rc=storeDetails($order_num, $nameid_list, $recip_names) && $rc;
  }
  
  if (!$rc) {
    $msg.="\r\n** ERROR OCCURRED WHILE SAVING ORDER IN DATABASE **\r\n";
  }

  // if using official name, change names to use in e-mails to official name rather than previous "unofficial" name
  if ($appear=="official") {
    $fname = $ofname;
    $lname = $olname;
  }

  // e-mail order details to webmaster
  $to = $mail_orders;
  $subject = "Shalach Manot Order for ".$lname.", ".$fname;
  $headers = "MIME-Version: 1.0\r\n";
  $headers.="Content-type: text/plain;charset=iso-8859-1\r\n";
  $headers.="From: ".$mail_from;

  if (!send_email($to, $subject, $msg, $headers)) {
    // this doesn't ever seem to return false, but just in case...
    $cnf.="<script>alert('Warning: There were technical difficulties in submitting your order. Please call the office at call 972-473-7718 to confirm your order was received.');</script>";
  }

  // e-mail delivery volunteer info to delivery chair
  if ($driver && ($mail_drivers != "")) {
    $to = $mail_drivers;
    $subject = "Shalach Manot Basket Delivery Volunteer";
    // headers are unchanged from previous email
    $msg = "Please note - we've received an order from someone who has volunteered to help with deliveries. See details below.\r\n\r\n";
    $msg.= $fname." ".$lname."\r\n";
    if ($_REQUEST["email"] != "") $msg.= $_REQUEST["email"]."\r\n";
    $msg.= getAddress($_REQUEST["member"])."\r\n";
    if ($_REQUEST["phone"] != "") $msg.= $_REQUEST["phone"]."\r\n";

    send_email($to, $subject, $msg, $headers);
  }
  
  // e-mail benefactor info
  if ($sponsor && ($mail_benefactors != "")) {
    $to = $mail_benefactors;
    $subject = "Shalach Manot Benefactor";
    // headers are unchanged from previous email
    $msg = "Please note - we've received a benefactor order from ".$fname." ".$lname.".\r\n\r\n";
    $msg.= "    Name to appear as: ".$nameformat."\r\n";
    if ($_REQUEST["email"] != "") $msg .= "    Email address: ".$_REQUEST["email"]."\r\n";

    send_email($to, $subject, $msg, $headers);
  }

  // e-mail notification of change to existing order
  if ($mail_changes && $order_data) {
      $to = $mail_changes;
      $subject = "Shalach Manot Order Updated";
      // headers are unchanged from previous email
      $msg = "We've received an update to Shalach Manot Order #".$order_num." for ".$fname." ".$lname.".  Details below.\r\n\r\n";
      $msg.= "Email: ".$_REQUEST["email"]."\r\n\r\n";
      if (array_key_exists("donation", $_REQUEST) && $_REQUEST["donation"] != 0) {
        $donation=$_REQUEST["donation"];
        $msg.= "Subtotal: $".sprintf("%.2f",$subtotal)."\r\n";
        if ($donation > 0) {
          $msg.= "Donation: $".sprintf("%.2f",$donation)."\r\n";
        } else {
          $msg.= "Adjusted: -$".sprintf("%.2f",-$donation)."\r\n";
        }
      }
      $msg.= "Total:    $".sprintf("%.2f",$total)."\r\n";
      $msg.= "Amt Paid: $".sprintf("%.2f",$paid)."\r\n";
      $msg.= "Amt Due:  $".sprintf("%.2f",$due)."\r\n\r\n";

      if ($pmtType == "credit" || $pmtType == "echeck") {
        $msg.= "NOTE: ShulCloud payment pending (may or may not be included in Amt Paid)\r\n";
      } else {
        $msg.= "Pmt Type: Check (please invoice)\r\n";
      }
    
      if ($notes) $msg.= "\r\nOrder Notes: ".$notes."\r\n";

      send_email($to, $subject, $msg, $headers);
  }

  // e-mail recipients (advertising!)
  if ($mail_recipients) emailRecips($order_num);
  
  // e-mail confirmation to person who submitted order
  if ($_REQUEST["email"] == "") {
    // send to admin instead (only valid for admin orders)
    $to = '"'.$admin_name.'" <'.$admin_user.'>';
    $to_addr = $admin_user;
  } else {
    $to = '"'.$fname." ".$lname.'" <'.$_REQUEST["email"].'>';
    $to_addr = $_REQUEST["email"];
  }
  $subject = "Shalach Manot Order Confirmation";
  $headers = "MIME-Version: 1.0\r\n";
  $headers.="Content-type: text/html;charset=iso-8859-1\r\n";
  $headers.="From: ".$mail_from."\r\n";
  $headers.= "Reply-To: ".$mail_help."\r\n";

  if (($admin_user != "") && ($_REQUEST["email"] != "")) $headers.='cc: "'.$admin_name.'" <'.$admin_user.">\r\n";
  $headers.="bcc: ".$mail_bcc_list;

  if (!send_email($to, $subject, $cnf."</body></html>\r\n", $headers)) {
    $cnf.="<script>alert('Note: We were unable to send a confirmation e-mail to the address you provided (";
    $cnf.=htmlspecialchars($_REQUEST["email"]).").');</script>";
  } else {
    $cnf.="<p style='margin-top:6pt;color:#606060;'>This confirmation has been e-mailed to you at ".(($admin_user == "")?$to_addr:$admin_user).".";
    if (($admin_user != "") && ($_REQUEST["email"] != "")) $cnf.="<br/>Confirmation also sent to ".$to_addr.".";
    $cnf.="</p>";
  }

  $cnf.="</body></html>";

  if ($pmtType == "check" || $due <= 0) {
      echo $cnf;  // spit out the confirmation page (identical to e-mail)
  } else {
      // setup redirection for payment
      header("Location: ".$pmt_url);
      echo '<html><body><p>Redirecting for online payment...</p></body></html>';
  }
}

// checkForReload: return NameID for current year's order if this is a valid reload
//                 return 0 if NameID cannot be found (PIN will be checked later)
//                 also sets $reload_pin
function checkForReload() {
  global $reload_pin, $db_link;
  
  if (array_key_exists("open",$_REQUEST)) {
    $tokens = explode(".",$_REQUEST["open"],2);
    if (count($tokens) == 2) {
      $row = mysqli_fetch_assoc(mysqli_query($db_link,"SELECT LastName,FirstNames FROM People WHERE NameID=".$tokens[0]));
      
      if ($row) {
        $reload_pin = $tokens[1];
        return $tokens[0];
      }
    }
  }
  
  return 0;
}

// checkForPreload: return order # for prior year's order if this is a valid preload (for last year or prior year)
//                  return 0 if not a preload attempt
//                  return -1 if unable to preload (i.e. bad ID and/or PIN)
//                  return -2 if order already exists from this year; also set globals for nameid and pin
function checkForPreload() {
  global $reload_nameid, $reload_pin, $db_link;

  if (array_key_exists("preload",$_REQUEST)) {
    $tokens = explode(".",$_REQUEST["preload"],2);
    if (count($tokens) == 2) {
      $override = 0;
      if (array_key_exists("admin",$_REQUEST)) {
          // request made by admin user; check if legit and then override PIN
          $row = mysqli_fetch_array(mysqli_query($db_link,"SELECT Token FROM Admins WHERE Email='".$_REQUEST["admin"]."'"));
          if ($row && isset($_COOKIE["admin_token"]) && !strcmp($row[0],$_COOKIE["admin_token"])) $override = 1;
      }
      // search up to 2 years back for a matching PIN
      // if this is for an admin user, then take the first order found
      for ($delta = 1; $delta <= 2; $delta++) {
        $row = mysqli_fetch_assoc(mysqli_query($db_link,"SELECT OrderNumber,PIN FROM Orders WHERE NameID=".$tokens[0]." AND Year=".(date("Y")-$delta)));
        if ($row) {
          if ($override || $row["PIN"] == $tokens[1]) {
            // check for a current year order
            $reload_pin = orderExistsAndFetchPin($tokens[0],date("Y"));
            if ($reload_pin >= 0) {
              $reload_nameid = $tokens[0];
              return -2;
            } else {
              return $row["OrderNumber"];
            }
          }
        }
      }
    }
    return -1;
  }
  return 0;
}

// preloadData: fetch various lists of data from prior year order
function preloadData($order_num) {
  global $preload_person, $preload_checklist, $preload_checklist_r, $preload_writeins, $preload_candidates, $preload_extras, $preload_order_text, $preload_pmt_type, $self_pickup, $admin_user;
  global $db_link;
  
  // first we'll get the People data
  $preload_person = mysqli_fetch_assoc(mysqli_query($db_link,"SELECT * FROM People INNER JOIN Orders AS o ON People.NameID=o.NameID WHERE o.OrderNumber=".$order_num));

  // ensure name fields are populated
  if (!array_key_exists("OfficialFirstNames",$preload_person) || !$preload_person["OfficialFirstNames"]) $preload_person["OfficialFirstNames"]="";
  if (!array_key_exists("OfficialLastName",$preload_person) || !$preload_person["OfficialLastName"]) $preload_person["OfficialLastName"]="";
  if (!array_key_exists("AndFamily",$preload_person) || !$preload_person["AndFamily"]) $preload_person["AndFamily"]="0";
  
  // now get the phone number & custom name (if applicable) that was provided last time
  $row = mysqli_fetch_row(mysqli_query($db_link,"SELECT PhoneProvided,Reciprocity,CustomName,ExtraBaskets,Driver,Year,PmtType FROM Orders WHERE OrderNumber=".$order_num));
  
  if ($row) {
    if ($row[0]) $preload_person["PhoneNumber"] = $row[0];
    $preload_person["Reciprocity"] = $row[1];
    if ($row[2]) $preload_person["CustomName"] = $row[2];
    if ($self_pickup) {
      $preload_extras = $row[3];
    } else { 
      $preload_extras = 0;
    }
    $preload_person["Driver"] = $row[4];
    $preload_order_text = (($row[5] == date("Y")-1)?"last year's order":(($admin_user=="")?"your ":"their ").$row[5]." order");
    $preload_pmt_type = $row[6];
  }
  
  // list #1: names from prior year's checklist who are still on this year's checklist
  $result = mysqli_query($db_link,"SELECT od.NameID,od.Reciprocity FROM OrderDetails AS od INNER JOIN People AS p ON od.NameID=p.NameID WHERE od.OrderNumber=".$order_num." AND (p.Status<>'Non-Member' OR p.Staff=1)");
  
  $preload_checklist = array();
  $preload_checklist_r = array();  // 0 if normal, 1 if reciprocal order
  
  while ($row = mysqli_fetch_row($result)) {
    array_push($preload_checklist,$row[0]);
    array_push($preload_checklist_r,$row[1]);
  }
  
  // list #2: writeins from prior year that are still non-members
  $result = mysqli_query($db_link,"SELECT ow.NameID FROM OrderWriteins AS ow INNER JOIN People AS p ON ow.NameID=p.NameID WHERE ow.OrderNumber=".$order_num." AND p.Status='Non-Member' AND p.Staff=0 ORDER BY Seq");
  
  $preload_writeins = array();
  
  while ($row = mysqli_fetch_row($result)) array_push($preload_writeins,$row[0]);
  
  // list #3: candidate writeins for this year - i.e. those who are no longer on the membership checklist, excluding staff members (identified by no zipcode)
  $sql = "SELECT od.NameID FROM OrderDetails AS od JOIN People AS p ON od.NameID=p.NameID LEFT JOIN OrderWriteins AS ow ON (ow.OrderNumber=od.OrderNumber AND ow.NameID=od.NameID)"
    ." WHERE od.OrderNumber=".$order_num." AND p.Status='Non-Member' AND p.Staff=0 AND ow.NameID IS NULL AND p.ZipCode IS NOT NULL AND p.ZipCode<>''";
  $result = mysqli_query($db_link,$sql);
  
  $preload_candidates = array();
  
  while ($row = mysqli_fetch_row($result)) array_push($preload_candidates,$row[0]);
}

// main
if (array_key_exists("action", $_REQUEST)) {
  $act = $_REQUEST["action"];

  if (strcmp($act, "submit")) header("Content-type: application/json");  // all output except confirmation is JSON (AJAX return results)

  switch($act) {

  case 'lname':
    action_lname();
    break;

  case 'fname':
    $name = action_fname();

    // log start of form completion
    $logdata = date(DATE_RFC822)." START";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"];
    $logdata.=" for ".$_REQUEST["id"]." (".$name.")\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);

    break;

  case 'lookup':
    // no logging necessary
    action_lookup();
    break;

  case 'pin':
    $success = action_pin();
    // log PIN entry
    $logdata = date(DATE_RFC822)." PIN RCVD";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"];
    $logdata.=" for ".$_REQUEST["id"].(($success)?" (valid)":" (invalid)")."\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    break;

  case 'remind':
    $success = action_remind();
    // log PIN reminder
    $logdata = date(DATE_RFC822)." REMINDER";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"];
    $logdata.=" for ".$_REQUEST["id"].(($success)?" (success)":" (failure)")."\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    break;

  case 'sendlink':
    $success = action_sendlink();
    // log send link action
    $logdata = date(DATE_RFC822)." SEND PRELOAD LINK";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"];
    $logdata.=" for ".$_REQUEST["id"].(($success)?" (success)":" (failure)")."\r\n";
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    break;

  case 'list':
    action_list();
    break;

  case 'writein':
    action_writein();
    break;

  case 'error':
    js_error_report($_REQUEST["message"]);
    echo "{}";  // empty return result
    break;

  case 'submit':
    // check for false submissions
    if ($_REQUEST["magic_key"] != "shalom42136") {
      $logdata = date(DATE_RFC822)." SUBMIT_ATTEMPT_IGNORED";
      $logdata.=" from ".$_SERVER["REMOTE_ADDR"]."\r\n";
      file_put_contents($usage_log_name, $logdata, FILE_APPEND);
      echo "Submission error! Please reload this page and try again.";
    } else {
      // log submission
      $logdata = date(DATE_RFC822)." SUBMIT";
      $logdata.=" from ".$_SERVER["REMOTE_ADDR"];
      $logdata.=" for ".$_REQUEST["member"]." (".$_REQUEST["lastname"].", ".$_REQUEST["firstname"].")\r\n";
      file_put_contents($usage_log_name, $logdata, FILE_APPEND);

      header("Content-type:text/html;charset=iso-8859-1");

      form_submit();
    }

    break;
  }

  // very important - do not output rest of this file
  exit();

} else {  // initial page loading
  if (connectDB()) {
    loadAdminData();  // check if order placed by admin

    $reload_nameid = checkForReload();
    
    if ($reload_nameid) {
      $preload_order = 0;
    } else {
      $preload_order = checkForPreload();
    }
    
    if ($preload_order == 0) {
      $logdata = date(DATE_RFC822)." LOAD";
    } elseif ($preload_order > 0) {
      $logdata = date(DATE_RFC822)." PRELOAD of ".$preload_order;
      preloadData($preload_order);
      $logdata.= " for ".$preload_person["FirstNames"]." ".$preload_person["LastName"]." (".count($preload_checklist).",".count($preload_writeins).",".count($preload_candidates).")";
    } else {
      header("Content-type:text/html;charset=iso-8859-1");
      echo "<html><head><title>Message from Anshai Torah Shalach Manot website</title></head><body style='font-family:Georgia,Times,serif;font-size:12pt;background-color:#f0f0a0;'>";
      echo "<h2 style='color:#4040c0'>Congregation Anshai Torah - Shalach Manot Project</h2><p>";

      if ($preload_order == -1) {
        $logdata = date(DATE_RFC822)." PRELOAD_FAILURE on ".$_REQUEST["preload"];
        echo "Sorry, we are unable to process your request. You may have used an invalid PIN or URL. ";
        echo "Please return to <a href='".$web_addr."'>".$web_addr."</a> and try again.";
      } else {
        $logdata = date(DATE_RFC822)." PRELOAD_EXISTING on ".$_REQUEST["preload"];
        echo "Welcome back!<br/><br/><b>Please note:</b><br/>You already submitted an order for this year. ";
        echo "<a href='".$web_addr."?open=".$reload_nameid.".".$reload_pin."'>Click/tap here</a> to view or edit your order.";
      }
      
      echo "</p></body></html>";
    }

    $logdata.=" from ".$_SERVER["REMOTE_ADDR"]." on ".(array_key_exists("HTTP_USER_AGENT",$_SERVER)?$_SERVER["HTTP_USER_AGENT"]:"<unknown>");
    if ($admin_user == "") {
      $logdata.="\r\n";
    } else {
      $logdata.=" (by ".$admin_name.")\r\n";
    }  
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
    
    if ($preload_order < 0) exit();

  } else {
    // serious problem
    echo "Sorry, but we are experiencing technical difficulties. Please come back later.";

    // log it
    $logdata = date(DATE_RFC822)." FAILURE TO LOAD";
    $logdata.=" from ".$_SERVER["REMOTE_ADDR"]." on ".(array_key_exists("HTTP_USER_AGENT",$_SERVER)?$_SERVER["HTTP_USER_AGENT"]:"<unknown>");
    if ($admin_user == "") {
      $logdata.="\r\n";
    } else {
      $logdata.=" (by ".$admin_name.")\r\n";
    }  
    file_put_contents($usage_log_name, $logdata, FILE_APPEND);
 
    exit();
  }
}

// -------------------------------- end of PHP script - HTML page starts here
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head><title>Anshai Torah Shalach Manot Order Form</title>
<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />
<meta name="author" content="Dan Cohn" />
<meta name="description" content="Purim Shalach Manot order form for Congregation Anshai Torah, Plano, TX" />
<link rel="shortcut icon" href="./anshai.ico" />

<style type="text/css"><!--
body {font-family:Georgia,Times,serif;font-size:12pt;background-color:#f0f0a0;}
button,input[type="button"],input[type="submit"] {font-family:Georgia,Times,serif;font-size:12pt;border:solid 2px #808080;background-color:#f0f0f0;margin:0px;padding:2px 6px;cursor:pointer;}
h1 {font-family:Tahoma,Geneva,sans-serif;text-align:center;font-size:15pt;background-color:#8080c0;padding:6pt;min-width:600px;border-width:2px;border-style:solid none;border-color:#4040c0;}
h2 {font-size:14pt;margin-top:7pt;}
h3 {font-size:13pt;font-weight:bold;margin-top:0pt;margin-bottom:6pt;}
ul {margin-top:6pt;}
ul.pmt {list-style-type:none;}
li.pmt {margin-top:4pt;}
#summary {position:absolute;top:0px;right:0px;z-index:10;}
table.summary {border-style:none;font-family:Tahoma,Geneva,sans-serif;font-size:9pt;text-align:right;}
table.nonmem {border-style:none;border-width:none;font-family:Georgia,Times,serif;font-size:10pt;margin:4pt 0pt;}
tr.nonmem {margin-top:2pt;margin-bottom:2pt;}
tr.nonmem td,tr.nonmem div td {padding-right:6px;font-family:Georgia,Times,serif;font-size:10pt;}
div.nonmem_msg {margin-left:2em;margin-bottom:6pt;color:#f00000;font-size:10pt;}
input.clearbut {font-size:8pt;margin-left:12px;}
.summary caption {font-size:10pt;font-weight:bold;border-width:1px;border-style:solid none;border-color:black;}
.summary th,.summary td {padding:2px 5px;}
#title_row {background-color:#80f080;cursor:pointer;text-align:center;}
#mem_row {background-color:#c0c0f0;cursor:pointer;}
#local_row {background-color:#f0c0f0;cursor:pointer;}
#ship_row {background-color:#f0c0f0;cursor:pointer;}
#extra_row {background-color:#c0f0f0;cursor:pointer;}
#total_row {background-color:#f0c0c0;font-weight:bold;cursor:pointer;}
address {text-align:center;font-size:10pt;font-style:normal;background-color:#8080c0;padding-top:3pt;padding-bottom:3pt;border-width:1px;border-style:solid none;border-color:#4040c0;}
a:link,a:visited {color:#0000f0;}
a.addr:link,a.addr:visited {color:black;text-decoration:none;}
a.addr:hover {color:black;text-decoration:underline;}
a.addr:active {color:white;text-decoration:none;}
a.file:link,a.file:visited {text-decoration:none;border-width:1pt;border-style:dotted;border-color:#0000f0;font-size:10pt;padding:1pt 2pt;}
a.file:hover {background-color:white;}
a.file:active {border-style:solid;}
a.small:link,a.small:visited {font-size:10pt;text-decoration:none;color:#606060;}
a.small:hover {font-size:10pt;text-decoration:underline;color:#606060;}
a.small:active {font-size:10pt;text-decoration:underline;color:black;}
#top {width:100%;height:auto;display:block;position:relative;min-width:600px;overflow:hidden;}
#mid {width:100%;margin-top:20px;display:block;min-width:600px;}
#bot {width:100%;margin-top:34px;overflow:auto;display:block;min-width:600px;}
#banner {width:40%;}
#tabs {float:left;vertical-align:top;margin-right:20px;width:150px;}
div.arrow {font-family:Tahoma,Geneva,sans-serif;font-size:9pt;font-weight:bold;text-align:center;padding:4px;cursor:pointer;margin-top:22px;margin-right:4px;float:left;}
div.tab {font-family:Tahoma,Geneva,sans-serif;font-size:12pt;font-weight:bold;border-width:1px;border-color:#d0d0d0;border-top-style:solid;margin-top:0px;margin-bottom:0px;text-align:left;width:100%;padding:4px 4px 4px 2px;cursor:pointer;}
div.tablast {border-bottom-style:solid;}
div.page {background-color:#d0d0d0;border-width:3px;border-style:solid;border-radius:8px;overflow:auto;height:400px;padding:7px;display:none;min-width:400px;}
div.namelist {width:100%;margin-bottom:6pt;}
div.nowrap {display:inline;white-space:nowrap;}
div.col {width:33%;float:left;}
div.nam {overflow:hidden;white-space:nowrap;width:97%;}
#container {height:400px;}
#page1 {border-color:#00f000;}
#page2 {border-color:#0000f0;}
#page3 {border-color:#f000f0;}
.page4 {border-color:#00f0f0;}
.page5 {border-color:#f00000;}
.center {text-align:center;line-height:175%;}
.help {font-size:90%;color:#404040;vertical-align:middle;}
.nospace {margin-top:0pt;}
.selected {color:#0000f0;background-color:#f0f080;}
.spaceabove {margin-top:14pt;}
.spaceaboveplus {margin-top:20pt;}
.stdbox {height:12pt;}
.submit {font-family:Georgia,Times,serif;font-size:12pt;font-weight:bold;padding:2px;vertical-align:middle;}
.textinput {font-family:Georgia,Times,serif;font-size:11pt;border:2px inset #606060;border-radius:5px;padding:2px;}
.textfocus {border-color:#f00000;}
.warning {color:#f00000;font-weight:bold;font-style:italic;font-size:11pt;}
.grayout {color:#808080;}
.logo {margin-top:40px;text-align:center;height:76px;}
#reset_list {float:right;margin-left:20px;}
#scrim {position:fixed;top:0px;left:0px;width:100%;height:100%;min-height:100%;text-align:center;vertical-align:middle;background-color:white;opacity:0.6;filter:alpha(opacity=60);z-index:2000;}
#dialog {position:absolute;top:20%;left:25%;width:50%;background-color:lightgray;box-shadow:6px 6px 14px gray;padding:10px;z-index:2001;}
#dialog p {text-align:justify;}
#extra_count {font-family:Georgia,Times,serif;font-size:12pt;}
#listhelp2 {color:#0000f0;}
#notes {margin-top:2px;}
--></style>

<script type="text/javascript" src="json2.js"></script>
<script type="text/javascript" src="ezajax.js"></script>
<script type="text/javascript" src="common.js"></script>

<script type="text/javascript">
// Globals
var warnOnUnload=false,listLoaded=false,last_recip=true,prevOrder=null,needToCheckWriteins=false,previousBrowserHeight=0,previousBrowserWidth=0;
var nameid="0",reload_pin="",writein_count=0,totalsTimeout=0,nameChoices=[],donation_amt=0,total_paid=0.0,tabShown=0,benefactorSuggestLevel=0.70;
var invited_user=false;
<?php
// transfer relevant PHP globals to JavaScript
echo "var price_basket=".$price_basket.",price_nm_local=".$price_nm_local.",price_nm_ship=".$price_nm_ship.",price_benefactor=".$price_benefactor.",price_extra=".$price_extra.";\n";
echo "var membership=".$membership.";\n";
echo "var cats=".$cats.";\n";
echo "var self_pickup=".($self_pickup ? "true" : "false").";\n";
echo "var numPages=".($self_pickup ? "5" : "4").";\n";
echo "var benefactor_extra=\"".(isset($benefactor_extra) ? $benefactor_extra : "")."\";\n";
if ($admin_user != "") {
  echo "var admin_user='".$admin_user."',admin_name='".$admin_name."';\n";
} else {
  echo "var admin_user='';\n";
}
if ($preload_order > 0) {
  echo "var preload_order=true,preload_checklist=".php_to_js_array($preload_checklist).",preload_checklist_r=".php_to_js_array($preload_checklist_r).",preload_writeins=".php_to_js_array($preload_writeins)
    .",preload_candidates=".php_to_js_array($preload_candidates).",preload_extras=".$preload_extras.",preload_order_text=\"".$preload_order_text."\";\n";
} else {
  echo "var preload_order=false;\n";
  if ($site_online && ($reload_nameid > 0)) {
    echo "nameid='".$reload_nameid."';\n";
    echo "reload_pin='".$reload_pin."';\n";
  } 
}
?>

// Functions

function onTab(n) {
  if (numPages===4) {
    var shading=["#c0f0c0","#c0c0f0","#f0c0f0","#f0c0c0"];
  } else {
    var shading=["#c0f0c0","#c0c0f0","#f0c0f0","#c0f0f0","#f0c0c0"];
  }  
  if (tabShown!==n) gebi("tab"+n).style.backgroundColor=shading[n-1];
}

function offTab(n) {
  if (tabShown!==n) gebi("tab"+n).style.backgroundColor="transparent";
}

function onArrow(e) {
  if (numPages===4) {
    var shading=["#c0f0c0","#c0c0f0","#f0c0f0","#f0c0c0"];
  } else {
    var shading=["#c0f0c0","#c0c0f0","#f0c0f0","#c0f0f0","#f0c0c0"];
  }
  if (e.id==="prev_tab" && tabShown>1) e.style.backgroundColor=shading[tabShown-2];
  if (e.id==="next_tab" && tabShown<numPages) e.style.backgroundColor=shading[tabShown];
}

function offArrow(e) {
  e.style.backgroundColor="transparent";
}

function showTab(n) {
  var i;
  if (numPages===4) {
    shading=["#70f070","#7070f0","#f070f0","#f07070"];
  } else {
    shading=["#70f070","#7070f0","#f070f0","#70f0f0","#f07070"];
  }
  for (i=1;i<=numPages;i+=1) {
    gebi("tab"+i).style.backgroundColor=(i===n)?shading[n-1]:"transparent";
    gebi("page"+i).style.display=(i===n)?"block":"none";
  }
  gebi("prev_tab").style.color=(n===1)?"#d0d0d0":"black";
  gebi("next_tab").style.color=(n===numPages)?"#d0d0d0":"black";
  tabShown=n;
  
  if (needToCheckWriteins && (n===3)) needToCheckWriteins=false;
  if (n===numPages) retotal();
}

function showPrevTab() {
  if (tabShown>1) showTab(tabShown-1);
}

function showNextTab() {
  if (tabShown===1) {
    if (validatePage1()) showTab(2);
  } else if (tabShown===2) {
    if (validatePage2()) showTab(3);
  } else if (tabShown===3) {
    if (validatePage3()) showTab(4);
  } else if ((tabShown===4) && (numPages>4)) {
    showTab(5);
  }
}

function isTouchBrowser() {
  var pf="",ua="";
  if (navigator.platform) pf=navigator.platform.toLowerCase();
  if (navigator.userAgent) ua=navigator.userAgent.toLowerCase();
  return (pf=="ipad" || pf=="iphone" || pf=="android" || (ua.indexOf("android")>=0) || pf=="ipod" || pf=="palm" || pf=="linux armv7l" || pf=="webos");
}

function getBrowserHeight() {
  try {
    if (window.innerHeight) return window.innerHeight;
    if (document.documentElement.offsetHeight) return document.documentElement.offsetHeight;
    if (document.body.offsetHeight) return document.body.offsetHeight;
  }
  catch (e) {}
  return 0;
}

function getBrowserWidth() {
  try {
    if (window.innerWidth) return window.innerWidth;
    if (document.documentElement.offsetWidth) return document.documentElement.offsetWidth;
    if (document.body.offsetWidth) return document.body.offsetWidth;
  }
  catch (e) {}
  return 0;
}

function resizePages(force) {
  var i,height,width,usedHeight,padding=136,minHeight=200,touch=isTouchBrowser();
  // quit if no change in height (to avoid IE from looping)
  if (!touch && !force) {
    height=getBrowserHeight();
    width=getBrowserWidth();
    if (height==previousBrowserHeight && width==previousBrowserWidth) return;
    previousBrowserHeight=height;
    previousBrowserWidth=width;
  }
  // deal with top section
  gebi("top").style.height="auto";
  height=Math.max(5+gebi("summary").offsetHeight,0+gebi("top").offsetHeight);
  if (height > 5) gebi("top").style.height=height+"px";
  if (touch) {
    // maximize everything
    gebi("container").style.height="auto";
    for (i=1;i<=numPages;i+=1) gebi("page"+i).style.height="auto";
  } else {
    height=getBrowserHeight();
    if (height > 0) {
      try {
        usedHeight=gebi("top").offsetHeight+gebi("bot").offsetHeight;
      }
      catch (e) {}
      if (usedHeight) {
        height=height-usedHeight-padding;
        if (height<minHeight) height=minHeight;
        gebi("container").style.height=height+"px";
        for (i=1;i<=numPages;i+=1) gebi("page"+i).style.height=height+"px";
      }
    }
  }
}

function check_for_local_wi(num) {
    var city=gebi("nm_city_"+num).value;
    var state=gebi("nm_state_"+num).value;
    var local=false;
    if (city.length>0 && state.length==2 && isDeliverable(city,state)) local=true;
    showHidePhone(num,local);
}

function add_writein() {
  var html,new_div,x=writein_count+=1;
  html ="<div>";
  html+="<input type='radio' id='local_"+x+"' name='nm_type_"+x+"' value='local' onclick='showHidePhone("+x+",true);retotal();' tabindex=-1 onkeydown='return false;' style='display:none'/> ";
  html+="<label for='local_"+x+"' onmousedown='return false;' style='display:none'>Deliver Locally</label>";
  html+="<input type='radio' id='ship_"+x+"' name='nm_type_"+x+"' value='ship' checked=true onclick='showHidePhone("+x+",false);retotal();' tabindex=-1 onkeydown='return false;' style='display:none'/> ";
  html+="<label for='ship_"+x+"' onmousedown='return false;' style='display:none'>Ship</label>";
  html+="</div>";
  html+="<table class='nonmem' cellspacing='0'>";
  html+="<tr class='nonmem'><td rowspan='3' style='font-size:120%;vertical-align:top;padding-right:1em;'><b>("+x+")</b></td>";
  html+="<td><label for='nm_name_"+x+"'>Name</label></td>";
  html+="<td><input type='text' class='textinput' id='nm_name_"+x+"' name='nm_name_"+x+"' size='60' maxlength='60' onkeydown='return noBS(event);' onfocus='inputF(this);' onblur='inputB(this);'";
  html+=" onchange='nm_name_change("+x+",this);'/><input class='clearbut' type='button' value='Clear entry' onclick='clear_writein("+x+")' tabindex='-1' /></td></tr>";
  html+="<tr class='nonmem'><td><label for='nm_addr_"+x+"'>Street</label></td>";
  html+="<td><input type='text' class='textinput' id='nm_addr_"+x+"' name='nm_addr_"+x+"' size='60' maxlength='100' onkeydown='return noBS(event);' onchange='trim_contents(this);clr_wi_msg("+x+");' onfocus='inputF(this);' onblur='inputB(this);'/></td></tr>";
  html+="<tr class='nonmem'><td><label for='nm_city_"+x+"'>City</label></td>";
  html+="<td><input type='text' class='textinput' id='nm_city_"+x+"' name='nm_city_"+x+"' size='30' maxlength='30' onkeydown='return noBS(event);' onchange='trim_contents(this);check_for_local_wi("+x+");' onfocus='inputF(this);' onblur='inputB(this);'/>&nbsp;&nbsp;";
  html+="<div class='nowrap'><label for='nm_state_"+x+"'>State</label> ";
  html+="<input type='text' class='textinput' id='nm_state_"+x+"' name='nm_state_"+x+"' size='3' maxlength='2' onchange='capitalize(this);check_for_local_wi("+x+");' onfocus='inputF(this);' onblur='inputB(this);'/>&nbsp;&nbsp;";
  html+="<label for='nm_zip_"+x+"'>Zip</label> ";
  html+="<input type='text' class='textinput' id='nm_zip_"+x+"' name='nm_zip_"+x+"' size='10' maxlength='10' onkeydown='return noBS(event);' onchange='trim_contents(this);' onfocus='inputF(this);' onblur='inputB(this);'/>&nbsp;&nbsp;";
  html+="<label id='nm_phlab_"+x+"' for='nm_phone_"+x+"' style='display:none'>Phone</label> ";
  html+="<input type='text' class='textinput' id='nm_phone_"+x+"' name='nm_phone_"+x+"' size='15' maxlength='32' onkeydown='return noBS(event);' onchange='clean_phone_num(this);clr_wi_msg("+x+");' onfocus='inputF(this);' onblur='inputB(this);' style='display:none'/>";
  html+="<input type='hidden' id='nm_id_"+x+"' name='nm_id_"+x+"' value=''/></div></td></tr></table>";
  html+="<div id='nm_msg_"+x+"' class='nonmem_msg' style='display:none'> </div>";
  new_div=document.createElement('div');
  new_div.id="writein_"+x;
  new_div.style.marginBottom="15pt";
  new_div.innerHTML=html;
  gebi("writeins").appendChild(new_div);
  if (x===2) gebi("del_one").disabled=false;
  if (x>1 && tabShown===3) {
    gebi("add_one").focus();  // scroll down
    gebi("nm_name_"+x).focus();
  }
}

function fill_writein(nameid,msg) {
  add_writein();
  gebi("nm_id_"+writein_count).value=nameid;
  gebi("nm_msg_"+writein_count).innerHTML=msg;
  gebi("nm_msg_"+writein_count).style.display="block";
  ezAjax.initiate("lookup", {"num":writein_count,"id":nameid}, lookup_callback);
}

function del_writein() {
  var name,last=gebi("writein_"+writein_count);
  if (writein_count > 1) {
    name=gebi("nm_name_"+writein_count).value;
    if (name) {
      if (!confirm("Do you want to delete "+name+" (write-in #"+writein_count+")?")) return;
    }
    gebi("writeins").removeChild(last);
    writein_count-=1;
    if (writein_count <= 1) gebi("del_one").disabled=true;
  }
}

function clear_writein(num) {
  var tags=["name","addr","city","state","zip","phone","id"];
  var name=gebi("nm_name_"+num).value;
  if (name) {
    if (!confirm("Are you sure you want to erase "+name+" (write-in entry #"+num+")?")) return;
  }
  for (var i=0; i<tags.length; ++i) gebi("nm_"+tags[i]+"_"+num).value="";
  showHidePhone(num,false);
  gebi("nm_msg_"+num).style.display="none";
  retotal();
}

function clr_wi_msg(num) {
   gebi("nm_msg_"+num).style.display="none";
}

function showHidePhone(num,show) {
  var disp=(show?"inline":"none");
  gebi("nm_phlab_"+num).style.display=disp;
  gebi("nm_phone_"+num).style.display=disp;
}

function otherName_change() {
  var checked=gebi("appear4").checked;
  gebi("appear_name").disabled=!checked;
  gebi("appear4_help").style.visibility=(checked?"visible":"hidden");
  if (checked) gebi("appear_name").focus();
}

function bene_change() {
  if (gebi("bene_checkbox").checked) {
    gebi("reciprocity").style.display="none";
    last_recip=gebi("recip_checkbox").checked;
    gebi("recip_checkbox").checked=false;
    gebi("checklist").style.display="none";
    gebi("reset_list").style.display="none";
    gebi("listhelp").style.display="none";
    gebi("listhelp2").style.display="none";
    if (admin_user) gebi("nolist").innerHTML="You've selected the Benefactor option, so there's no need to select individual names.";
    gebi("nolist").style.display="block";
    gebi("goto_page2").style.display="none";
    gebi("goto_page3").style.display="block";
    gebi("appear4_help").innerHTML="&nbsp;Your name and/or business name exactly as you want it to appear (up to 60 characters).";
  } else {
    if (!invited_user) {
      gebi("recip_checkbox").checked=last_recip;
      gebi("reciprocity").style.display="block";
    }
    gebi("checklist").style.display="block";
    gebi("reset_list").style.display="inline";
    gebi("listhelp").style.display="block";
    gebi("nolist").style.display="none";
    gebi("goto_page3").style.display="none";
    gebi("goto_page2").style.display="block";
    gebi("appear4_help").innerHTML="&nbsp;Enter name exactly as you want it to appear.";
  }
}

function pickup_change() {
  var txt=gebi("extra_baskets"),ec=gebi("extra_count");
  if (gebi("pickup_checkbox").checked) {
    txt.className="";
    ec.disabled=false;
    if (ec.value==0) {
      ec.value="1";
      retotal();
    }
  } else {
    txt.className="grayout";
    ec.value="0";
    ec.disabled=true;
    retotal();
  }
}

function donation_change(e) {
  if (admin_user) {
    convert_to_dollars(e,true);
  } else {
    convert_to_positive_dollars(e,true);
  }
  retotal();
}
    
function payment_change(e) {
  convert_to_positive_dollars(e,false);
  if (admin_user) total_paid=parseFloat(gebi("total_paid").value);
  retotal();
}

function toggle_name(id,checked) {
  var labelElem=gebi("label4"+id);
  if (checked) {
    labelElem.className="selected";
  } else {
    labelElem.className="";
  }
}

function name_select(e) {
  window.setTimeout("toggle_name('"+e.id+"',"+e.checked+")", 100);
  retotal();
}

function findLastName() {
  var lname,lname_box=gebi("lname"),but=gebi("lookup"),menu=gebi("lname_select");

  if (menu.style.display !== "none") {
    lname=menu.options[menu.selectedIndex].text;
    lname_box.value=lname;
    lname_box.disabled=false;
    menu.style.display="none";
    gebi("start_over").style.display="none";
  } else {
    trim_contents(lname_box);
    lname=lname_box.value;
  }

  if (lname) {
    gebi("top").style.cursor="wait";
    but.blur();
    lname_box.blur();
    but.value="Searching...";
    but.disabled=true;
    ezAjax.initiate("lname", {"lname":lname}, lname_callback);
  } else {
    lname_box.focus();
  }
}

function nm_name_change(i,e) {
  trim_contents(e);
  and_to_amp(e);
  clr_wi_msg(i);
  retotal();
  if (e.value.length) {
    gebi("page3").style.cursor="progress";
    ezAjax.initiate("writein", {"num":i,"name":e.value}, writein_callback);
  }
}

function lname_callback(result) {
  var rc=result.rc,but=gebi("lookup"),menu,opt,namelist;

  gebi("top").style.cursor="auto";
  switch (rc) {
  case 'found':
    but.style.display="none";
    gebi("lname_prompt").innerHTML="Last name:";
    gebi("lname").value=result.last;
    gebi("lname").readOnly=true;
    menu=gebi("fname_select");
    while (menu.length > 0) menu.remove(0);
    opt=document.createElement("option");
    opt.text="";
    try {menu.add(opt,null);}
    catch (e) {menu.add(opt);}
    namelist=result.names;
    for (i=0;i<namelist.length;i+=1) {
      opt=document.createElement("option");
      opt.text=namelist[i].name;
      opt.value=namelist[i].id;
      try {menu.add(opt,null);}
      catch (e) {menu.add(opt);}
    }
    if (admin_user) gebi("fname_prompt").innerHTML="Select first name(s) from the following list:";
    gebi("fname").style.visibility="visible";
    menu.focus();
    break;
  case 'choose':
    but.value="Select";
    but.disabled=false;
    gebi("lname").disabled=true;
    menu=gebi("lname_select");
    while (menu.length > 0) menu.remove(0);
    namelist=result.names;
    for (i=0;i<namelist.length;i+=1) {
      opt=document.createElement("option");
      opt.text=namelist[i];
      try {menu.add(opt,null);}
      catch (e) {menu.add(opt);}
    }
    menu.style.display="inline";
    gebi("lname_prompt").innerHTML="Choose the correct name from the dropdown menu and press Select:";
    gebi("start_over").style.display="inline";
    menu.focus();
    break;
  case 'none':
    if (admin_user) {
      alert("Sorry, unable to find name in the membership database.");
    } else {
      alert("Sorry, unable to find your name in our database. Please try again or call the Anshai Torah office for assistance.");
    }
    but.value="Lookup";
    but.disabled=false;
    gebi("lname").value="";
    gebi("lname").focus();
    break;
  }
}

function findFullName() {
  var nameid,menu=gebi("fname_select");

  if (menu.selectedIndex === 0) return;  // first entry is blank

  gebi("top").style.cursor="wait";
  nameid=menu.options[menu.selectedIndex].value;
  menu.disabled=true;
  ezAjax.initiate("fname", {"id":nameid}, fname_callback);
}

function populateNameFields(first,last,ofirst,olast,andFam,customName) {
  var radtext,i;
  if (!ofirst) ofirst=first;
  if (!olast) olast=last;
  gebi("lname").value=last;
  gebi("firstname").value=first;
  gebi("olname").value=olast;
  gebi("ofname").value=ofirst;
  nameChoices[0]=first+" "+last; // global array
  nameChoices[1]=first+" "+last+" and Family";
  if ((ofirst.toLowerCase() !== first.toLowerCase()) || (olast.toLowerCase() !== last.toLowerCase())) {
    nameChoices[2]=ofirst+" "+olast;
    nameChoices[3]=ofirst+" "+olast+" and Family";
  }
  for (i=0;i<nameChoices.length;i+=1) {
    radtext=gebi("appear"+i+"_text");
    radtext.innerHTML=htmlspecialchars(nameChoices[i])+"<br/>";
  }
  if (nameChoices.length < 3) {
    gebi("appear2").style.display="none";
    gebi("appear3").style.display="none";
    gebi("appear2_text").style.display="none";
    gebi("appear3_text").style.display="none";
  } else {
    gebi("appear2").style.display="inline";
    gebi("appear3").style.display="inline";
    gebi("appear2_text").style.display="inline";
    gebi("appear3_text").style.display="inline";
  }
  if (last.toLowerCase().indexOf("family") >= 0) {
    gebi("appear1").style.display="none";
    gebi("appear1_text").style.display="none";
    andFam=0;
  }
  if (olast.toLowerCase().indexOf("family") >= 0) {
    gebi("appear3").style.display="none";
    gebi("appear3_text").style.display="none";
  }
  if (customName) {
    gebi("appear4").checked=true;
    gebi("appear_name").disabled=false;
    gebi("appear_name").value=customName;
    gebi("appear4_help").style.visibility="visible";
  } else {
    gebi("appear"+andFam).checked="true";
  }
  return htmlspecialchars(first+" "+last);
}

function fname_callback(result) {
  if (!result.rc) startOver();
  var welcome=populateNameFields(result['FirstNames'], result['LastName'], result['OfficialFirstNames'] || '', result['OfficialLastName'] || '', result['AndFamily'] || '0', null);
  var date=new Date();
  nameid=result['NameID']; // global
  gebi("memberid").value=nameid;
  gebi("memberstatus").value=result['Status'];
  gebi("staffmember").value=result['Staff'];
  gebi("old_phone").value=result['PhoneNumber'] || result['AltPhoneNumber'] || '';
  gebi("old_email").value=result['Email'] || '';
  gebi("findname").style.display="none";
  gebi("top").style.cursor="auto";

  if (self_pickup && result['Mapsco'].substr(0,2).toUpperCase() == "AT") {
    gebi("pickup_checkbox").checked=true;
    pickup_change();
  }

  if (result['Status']=="Non-Member" && result['Invited']==1) {  // invited to participate as a non-member
    gebi("volunteer1").style.display="none";
    gebi("volunteer2").style.display="none";
    invited_user=true;
    gebi("invited").value="1";
  }

  if (result['ordered']) {  // already ordered
    if (admin_user) {
      gebi("member_on_file").innerHTML=welcome+" already "+((welcome.indexOf(" &amp; ")>0)?"have":"has")+" an order on file for this year.";
      gebi("welcome2").innerHTML="Order for: "+welcome;
      gebi("pin_forgot").style.display="none";
      gebi("pin_label").style.display="none";
      gebi("pin").style.display="none";
    } else {
      gebi("welcome1").innerHTML="Shalom, "+welcome+"!";
      gebi("welcome2").innerHTML="Welcome back, "+welcome+"!";
      if (result['Email']) {
        gebi("pin_forgot").disabled=false;
      } else {
        gebi("pin_forgot").style.display="none";
      }
    }
    gebi("email").value=result['Email'];
    gebi("enterpin").style.display="block";
    gebi("pin_submit").disabled=false;
    if (reload_pin.length > 0) {
      // page loading with supplied nameid/pin
      gebi("pin").value=reload_pin;
      gebi("pin_so").style.display="none";
      validatePin();
    } else {
      gebi("pin").focus();
    }
  } else if (result['lastyear']) {  // preloading possible
    gebi("preload").style.display="block";
    if (admin_user) {
      gebi("welcome2").innerHTML="Order for: "+welcome;
      gebi("lastyear").innerHTML=welcome+" placed an order in "+result['lastyear']+".<br/>Would you like to pre-populate the order form with information from their previous order?";
      gebi("preload_but_1").style.display="none";
      gebi("preload_but_2").style.display="inline";
    } else {
      gebi("welcome1").innerHTML="Shalom, "+welcome+"!";
      gebi("welcome2").innerHTML="Welcome, "+welcome+"!";
      if (result['lastyear'] == (date.getFullYear()-1)) {
        gebi("lastyear").innerHTML="Thank you for participating in last year's fundraiser!<br/>Are you interested in pre-populating your order form with information from last year's order?";
      } else {
        gebi("lastyear").innerHTML="Thank you for participating in our "+result['lastyear']+" fundraiser!<br/>Are you interested in pre-populating your order form with information from your previous order?";
      }
      gebi("sendlink_but").disabled=false;
    }
    gebi("top").style.height="auto";
  } else {  // new order
    if (admin_user) {
      gebi("welcome2").innerHTML="Order for: "+welcome;
      gebi("phone").value=result['PhoneNumber'];
      gebi("email").value=result['Email'];
    } else {
      gebi("welcome2").innerHTML="Welcome, "+welcome+"!";
    }
    activateForm();
  }
}

function sendPin() {
  gebi("pin_forgot").disabled=true;
  gebi("pin_forgot").value="Sending PIN...";
  gebi("top").style.cursor="wait";
  if (admin_user) {
    ezAjax.initiate("remind",{"id":nameid,"email":admin_user},remind_callback);
  } else {
    ezAjax.initiate("remind",{"id":nameid},remind_callback);
  }
}

function remind_callback() {
  var msg="<b>We've e-mailed ";
  if (admin_user) {
    msg+="the PIN to you at "+admin_user+".";
  } else {
    msg+="your PIN to the address you provided with your order.";
  }
  msg+="</b>&nbsp; The e-mail may take some time to arrive."
  msg+="<br/>Please enter your Personal Identification Number to view/edit "+((admin_user)?"the":"your")+" order.";
  gebi("pin_forgot").value="PIN Sent";
  gebi("pin_prompt").innerHTML=msg;
  gebi("pin").value="";
  gebi("top").style.cursor="auto";
  gebi("pin").focus();
}

function validatePin() {
  var but=gebi("pin_submit"),pin=gebi("pin").value;
  if (pin || admin_user) {
    but.disabled=true;
    but.value="Loading data...";
    gebi("top").style.cursor="wait";
    if (admin_user) {
      ezAjax.initiate("pin",{"id":nameid,"admin":admin_user},pin_callback);
    } else {
      ezAjax.initiate("pin",{"id":nameid,"pin":pin},pin_callback);
    }
  }
}

function pin_callback(result) {
  var i,x,wi,tp;
  if (result['rc']) {
    prevOrder=result;
    gebi("order_num").value=result['OrderNumber'];
    if (result['CustomName']) {
      gebi("appear_name").disabled=false;
      gebi("appear4_help").style.visibility="visible";
      gebi("appear_name").value=result['CustomName'];
      gebi("appear4").checked=true;
    }
    if (result['AllMembers']=="1") {
      gebi("bene_checkbox").checked=true;
      bene_change();
    } else if (result['Reciprocity']=="1") {
      gebi("recip_checkbox").checked=true;
    } else {
      gebi("recip_checkbox").checked=false;
    }
    if (result['ExtraDonation']) {
      gebi("donation").value=result['ExtraDonation'];
      if (result['ExtraDonation'] < 0) {
        if (!admin_user) gebi("donation").readOnly=true;
        gebi("donation_label").innerHTML="Price Adjustment:";
        if (!admin_user) gebi("donation_help").style.display="none";
      }
    }
    tp=result['TotalPaid'];
    if (tp) {
      if (admin_user) {
        gebi("total_paid").value=tp;
      } else {
        if (parseFloat(tp)>0) gebi("paid").innerHTML="&nbsp;Our records show that you've already paid <b>"+parseFloat(tp).dollars2()+"</b>.";
      }
      total_paid=parseFloat(tp);
    }
    if (result['PmtType']=="check") {
      gebi("paybycheck").checked=true;
    } else if (result['PmtType']=="echeck") {
      gebi("paybyecheck").checked=true;
    } else {
      gebi("paybycredit").checked=true;
    }
    if (result['Notes']) gebi("notes").value=result['Notes'];
    if (result['Driver']=="1") gebi("driver_checkbox").checked=true;
    if (result['Volunteer']=="1") gebi("volunteer_checkbox").checked=true;
    gebi("phone").value=result['PhoneProvided'];
    for (i=0; i<result['writeins'].length; ++i) {
      add_writein();
      x=writein_count;
      wi=result['writeins'][i];
      gebi("nm_id_"+x).value=wi['NameID'];
      gebi("nm_name_"+x).value=wi['Name'];
      gebi("nm_addr_"+x).value=wi['StreetAddress'];
      gebi("nm_city_"+x).value=wi['City'];
      gebi("nm_state_"+x).value=wi['State'];
      gebi("nm_zip_"+x).value=wi['ZipCode'];
      gebi("nm_phone_"+x).value=wi['PhoneNumber'];
      showHidePhone(x, isDeliverable(wi['City'],wi['State']));
/*      if (wi['Delivery']=="ship") {
        gebi("ship_"+x).click();
        showHidePhone(x,false);
      }
*/
    }
    if (result['ExtraBaskets'] > 0) {
      gebi("pickup_checkbox").checked=true;
      pickup_change();
      gebi("extra_count").value=result['ExtraBaskets'];
    }
    gebi("wrong").style.display="none";
    gebi("reorder_note").innerHTML="Note: If you make changes and submit, it will replace "+((admin_user)?"the":"your")+" previous order (#"+result['OrderNumber']+").";
    gebi("reorder_note").style.display="inline";
    gebi("submit_help").innerHTML="&lt;&lt; Press here when ready. This will replace "+((admin_user)?"the":"your")+" previous order.</b>";
    gebi("enterpin").style.display="none";
    gebi("top").style.cursor="auto";
    activateForm();
  } else {
    gebi("pin_prompt").innerHTML="<font color='red'><b>Invalid PIN!</b></font>";
    gebi("pin").style.display="none";
    gebi("pin_label").style.display="none";
    gebi("pin_submit").style.display="none";
    gebi("pin_forgot").style.display="none";
    gebi("top").style.cursor="auto";
  }
}

function preloadPrep() {
  gebi("preload").style.display="none";
  gebi("preload_2").style.display="block";
  gebi("preload_pin").focus();
}

function preloadWithPin() {
  if (admin_user) {
    window.location.assign("?preload="+nameid+".0000&admin="+encodeURIComponent(admin_user));
  } else {
    var pin=gebi("preload_pin").value;
    if (pin) window.location.assign("?preload="+nameid+"."+pin);
  }
}

function sendLink() {
  gebi("sendlink_but").disabled=true;
  gebi("sendlink_but").value="Emailing...";
  gebi("top").style.cursor="wait";
  ezAjax.initiate("sendlink",{"id":nameid},sendlink_callback);
}

function sendlink_callback(result) {
  gebi("sendlink_but").value="PIN Sent";
  if (result['rc']) {
    gebi("preload_instrux").style.display="none";
    gebi("preload_s").style.display="block";
  } else {
    gebi("preload_2").style.display="none";
    gebi("preload_f").style.display="block";
  }
  gebi("top").style.cursor="auto";
  gebi("top").style.height="auto";
  gebi("preload_pin").focus();
}

function activateForm() {
  gebi("welcome1").style.display="none";
  gebi("banner").style.display="block";
  gebi("mid").style.display="block";
  gebi("summary").style.display="block";
  if (!self_pickup) gebi("extra_row").style.display="none";
  updateTotals();
  resizePages(true);
  showTab(1);
  setTimeout(getChecklist,200);  // may no longer need to be delayed, but it's OK
  setTimeout("while (writein_count < 3) add_writein();",100);
  warnOnUnload=true;
}

function getChecklist() {
  ezAjax.initiate("list", null, list_callback);
}

function reenableRetry() {
  var rb=gebi("retry_but");
  if (rb) rb.disabled=false;
}

function retryChecklist() {
  gebi("retry_but").disabled=true;
  getChecklist();
  window.setTimeout("reenableRetry();",10000);
}

function resetChecklist() {
  var i,names;
  if (confirm("Do you want to clear your name selections?")) {
    names=gebi("checklist").getElementsByTagName("input");
    for (i=0;i<names.length;i+=1) {
      if (names[i].checked) {
        names[i].checked=false;
        gebi("label4"+names[i].id).className="";
      }
    }
    retotal();
  }
}

function list_callback(result) {
  var i,e;
  if (!listLoaded) {
    if (result.rc && result.output) {
      gebi("checklist").innerHTML=result.output;
      if (prevOrder) {
        var recip=false;
        for (i=0; i<prevOrder['names'].length; ++i) {
          e=gebi("namebox_"+prevOrder['names'][i]);
          if (e) {
            var nameLabel=gebi("label4namebox_"+prevOrder['names'][i]);
            e.checked=true;
            nameLabel.className="selected";
            if (prevOrder['recip'][i]=="1") {
                recip=true;
                nameLabel.innerHTML+=" *";
                nameLabel.title+=" (* selected due to reciprocity)";
            }
          }
        }
        if (recip) gebi("listhelp2").style.display="block";
        updateTotals();
      } else if (preload_order) {
        for (i=0; i<preload_checklist.length; ++i) {
          e=gebi("namebox_"+preload_checklist[i]);
          if (e) {
            var nameLabel=gebi("label4namebox_"+preload_checklist[i]);
            e.checked=true;
            nameLabel.className="selected";
            if (preload_checklist_r[i]=="1") {
                nameLabel.innerHTML+=" *";
                nameLabel.title+=" (* reciprocal order last time)";
            }
          }
        }
        updateTotals();
      }
      var mybox=gebi("namebox_"+nameid);
      if (mybox) {  // may be a non-member placing the order
        mybox.disabled=true;
        gebi("label4namebox_"+nameid).style.color="#808080";
      }
      gebi("page2").style.cursor="auto";
      if (!gebi("bene_checkbox").checked) gebi("reset_list").style.display="inline";
      listLoaded=true;
    }
  }
}

function writein_callback(result) {
  var x=result.num, name_e=gebi("nm_name_"+x), id_e=gebi("nm_id_"+x);
  var status, address, plural;

  gebi("page3").style.cursor="auto";

  if (!name_e || name_e.value!==result.name) {
    // writein was removed or changed since request
    return;
  }
  if (result.rc==="found") {
    plural=(result.name.indexOf(" & ") > 0) || (result.name.indexOf(" and ") > 0);
    if (result['Status']==="Non-Member" && result['Staff']==="0") {
      id_e.value=result['NameID'];
      if (gebi("nm_addr_"+x).value!==result['StreetAddress'] || gebi("nm_zip_"+x).value!==result['ZipCode']) {
        address=result['StreetAddress']+", "+result['City']+", "+result['State'];
        if (confirm((plural?"Are ":"Is ")+result.name+" (write-in #"+x+") still living at "+address+"?  If so, press OK to use this address.  If the address has changed or you don't recognize this address, press Cancel.")) {
          showTab(3);
          gebi("nm_addr_"+x).value=result['StreetAddress'];
          gebi("nm_city_"+x).value=result['City'];
          gebi("nm_state_"+x).value=result['State'];
          gebi("nm_zip_"+x).value=result['ZipCode'] || "";
          gebi("nm_phone_"+x).value=result['PhoneNumber'] || result['AltPhoneNumber'] || "";
          showHidePhone(x, isDeliverable(result['City'],result['State']));
/*
          if (result['Delivery']==="1") {
            gebi("local_"+x).click();
            showHidePhone(x,true);
          } else {
            gebi("ship_"+x).click();
            showHidePhone(x,false);
          }
*/
          retotal(); // due to possible delivery option change
          gebi("nm_phone_"+x).focus();
        }
      }
    } else {
      id_e.value="invalid";
      if (result['Status']==="Member") {
        status=plural?"members":"a member";
      } else if (result['Status']==="Associate") {
        status=plural?"associate members":"an associate member";
      } else if (result['Status']==="College") {
        status=plural?"college student members":"college student member";
      } else {
        status=plural?"staff members":"a staff member";
      }
      alert(result.name+" (write-in #"+x+") "+(plural?"are ":"is ")+status+" of Anshai Torah.  Please remove this write-in and select \""+result['LastName']+", "+result['FirstNames']+"\" from the member checklist.");
      showTab(3);
      name_e.focus();
    }
  } else {
    id_e.value="";
  }
}

function lookup_callback(result) {
  var x=result.num;

  if (result.rc) {
    gebi("nm_name_"+x).value=result['FirstNames']+" "+result['LastName']+((result['AndFamily']==="1")?" and Family":"");
    gebi("nm_addr_"+x).value=result['StreetAddress'];
    gebi("nm_city_"+x).value=result['City'];
    gebi("nm_state_"+x).value=result['State'];
    gebi("nm_zip_"+x).value=result['ZipCode'] || "";
    gebi("nm_phone_"+x).value=result['PhoneNumber'] || result['AltPhoneNumber'] || "";
    showHidePhone(x, isDeliverable(result['City'],result['State']));
/*
    if (result['Delivery']==="1") {
      gebi("local_"+x).click();
      showHidePhone(x,true);
    } else {
      gebi("ship_"+x).click();
      showHidePhone(x,false);
    }
*/
  } else {
    gebi("nm_id_"+x).value="";
  }
}

function calculate() {
  var bene=gebi("bene_checkbox").checked?price_benefactor:0;
  var mem=0,nm_local=0,nm_ship=0;
  var names=gebi("checklist").getElementsByTagName("input");
  var extra=parseInt(gebi("extra_count").value);
  var i,elem;
  if (!bene && names) {
    for (i=0;i<names.length;i+=1) {
      if (names[i].checked) mem+=1;
    }
  }
  for (i=1;i<=writein_count;i+=1) {
    elem=gebi("nm_name_"+i);
    if (elem && elem.value.length) {
      if (gebi("ship_"+i).checked) {
        nm_ship+=1;
      } else {
        nm_local+=1;
      }
    }
  }
  return {
    "bene_price":bene,
    "mem_count":mem,
    "mem_price":mem*price_basket,
    "local_count":nm_local, "local_price":nm_local*price_nm_local,
    "ship_count":nm_ship, "ship_price":nm_ship*price_nm_ship,
    "extra_count":extra, "extra_price":extra*price_extra,
    "total_mem":names.length
  };
}

function updateTotals() {
  totalsTimeout=0;
  var suggest,subtotal=0,totals=calculate(),bal=0,don=gebi("donation");
  if (totals.bene_price) {
    gebi("row1_label").innerHTML="Shalach Manot Benefactor";
    gebi("mem_qty").innerHTML="1";
    gebi("mem_price").innerHTML=price_benefactor.dollars2();
    gebi("mem_st").innerHTML=price_benefactor.dollars2();
  } else {
    gebi("row1_label").innerHTML="Names from Membership List";
    gebi("mem_qty").innerHTML=totals.mem_count;
    gebi("mem_price").innerHTML=price_basket.dollars2();
    gebi("mem_st").innerHTML=totals.mem_price.dollars2();
  }
//  gebi("local_qty").innerHTML=totals.local_count;
//  gebi("local_price").innerHTML=price_nm_local.dollars2();
//  gebi("local_st").innerHTML=totals.local_price.dollars2();
  gebi("ship_qty").innerHTML=totals.ship_count;
  gebi("ship_price").innerHTML=price_nm_ship.dollars2();
  gebi("ship_st").innerHTML=totals.ship_price.dollars2();
  if (self_pickup) {
    gebi("extra_qty").innerHTML=totals.extra_count;
    gebi("extra_price").innerHTML=price_extra.dollars2();
    gebi("extra_st").innerHTML=totals.extra_price.dollars2();
  }
  subtotal=totals.bene_price+totals.mem_price+totals.local_price+totals.ship_price+totals.extra_price;
  gebi("total").innerHTML=subtotal.dollars2();
  if (don.value) {
    bal=subtotal+parseFloat(don.value)-total_paid;
  } else {
    bal=subtotal-total_paid;
  }
  gebi("amt_due").innerHTML=bal.dollars2();
  if (bal > 0) {
    gebi("amt_due").style.color="#f00000";
  } else if (bal < 0) {
    gebi("amt_due").style.color="#008000";
  } else {
    gebi("amt_due").style.color="inherit";
  }
  if (totals.mem_price>=benefactorSuggestLevel*price_benefactor && !totals.bene_price && listLoaded) {
    suggest="Have you considered becoming a Shalach Manot Benefactor? You've already selected "+totals.mem_count;
    suggest+=" names (for "+totals.mem_price.dollars()+"). ";
    suggest+="For "+price_benefactor.dollars()+" you can send baskets to all "+totals.total_mem+" members and staff on the list.";
    if (benefactorSuggestLevel<1.0 && totals.mem_price<price_benefactor) {
      benefactorSuggestLevel=1.0;
    } else {
      benefactorSuggestLevel=999;
    }
    if (confirm(suggest)) {
      showTab(1);
      gebi("bene_checkbox").focus();
    }
  }
}

// was named recalc but "recalc" apparently is a reserved word for IE - doh!
function retotal() {
  if (totalsTimeout) clearTimeout(totalsTimeout);
  totalsTimeout=setTimeout("updateTotals()",400);
}

function chkAjax(msg) {
  if (ezAjax.isBusy()) {
    msg.errStr="Still busy checking your last entry.  Please wait a few seconds and try again.";
    return false;
  } else {
    return true;
  }
}

function chkName(msg) {
  if (gebi("appear4").checked) {
    if (!gebi("appear_name").value.length) {
      if (admin_user) {
        msg.errStr="Please fill in how you'd like your name to appear or select one of the other options provided.";
      } else {
        msg.errStr="Please fill in the 'Other' name field.";
      }
      showTab(1);
      msg.e=gebi("appear_name");
      return false;
    }
  }
  return true;
}

function chkEmail(msg) {
  var email=gebi("email").value;
  // regexp lifted from http://fightingforalostcause.net/misc/2006/compare-email-regex.php
  var pattern=/^[-a-z0-9~!$%^&*_=+}{\'?]+(\.[-a-z0-9~!$%^&*_=+}{\'?]+)*@([a-z0-9_][-a-z0-9_]*(\.[-a-z0-9_]+)*\.([a-z][a-z]+)|([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}))(:[0-9]{1,5})?$/i;
  if (email.length) {
    if (email.match(pattern)) {
      return true;
    } else {
      msg.errStr="E-mail address \""+email+"\" is not valid.  Please enter a single valid address.";
      showTab(1);
      msg.e=gebi("email");
      return false;
    }
  } else {
    if (!admin_user) {
      msg.errStr="Please provide your e-mail address.";
      showTab(1);
      msg.e=gebi("email");
      return false;
    } else {
      return true;
    }
  }
}

function chkPhone(msg) {
  if (gebi("phone").value || admin_user) {
    return true;
  } else {
    msg.errStr="Please provide your phone number so we can call you in case there are questions about your order.";
    showTab(1);
    msg.e=gebi("phone");
    return false;
  }
}

function chkRecip(msg, nameCount) {

/* Commented out for now - may add back later
 *
 if (gebi("recip_checkbox").checked) {
    if ((nameCount < 5) && !admin_user) {
      msg.errStr="You must select at least 5 names from the list in order to be eligible for Reciprocity.  Please select ";
      if (nameCount == 4) {
        msg.errStr+="one more name";
      } else {
        msg.errStr+=(5-nameCount)+" more names";
      }
      msg.errStr+=" or uncheck Reciprocity on page 1.";
      showTab(2);
      return false;
    }
  }
*/
  return true;
}

// technically no longer needed (one is selected by default)
function chkPmt(msg,items) {
  var donation = parseFloat(gebi("donation").value);
  if (items===0 && !donation) return true; // nothing to pay
  if (gebi("paybycheck").checked || gebi("paybycredit").checked || gebi("paybyecheck").checked) return true;
  msg.errStr="Please select a payment option.";
  showTab(5);
  msg.e=gebi("paybycheck");
  return false;
}

function isDeliverable(city, state) {
  var cities=/^(dallas|plano|addison|richardson|carr?oll?ton|frisco|mckinney|allen|the colony)$/i;
  return (state=="TX" && city.match(cities));   
}
    
function chkWriteins(msg) {
  var rc=true, e, i, name, name_e, addr, addr_e, city, city_e, state, state_e, zip, zip_e, phone, phone_e;
  var states=/^(A[LKSZRAEP]|C[AOT]|D[EC]|F[LM]|G[AU]|HI|I[ADLN]|K[SY]|LA|M[ADEHINOPST]|N[CDEHJMVY]|O[HKR]|P[ARW]|RI|S[CD]|T[NX]|UT|V[AIT]|W[AIVY])$/;
  var zips=/^(?!0{5})(\d{5})(?![ -]?0{4})([ -]?\d{4})?$/;
  for (i=1; i<=writein_count && rc; i+=1) {
    name=(name_e=gebi("nm_name_"+i)).value;
    addr=(addr_e=gebi("nm_addr_"+i)).value;
    city=(city_e=gebi("nm_city_"+i)).value;
    state=(state_e=gebi("nm_state_"+i)).value;
    zip=(zip_e=gebi("nm_zip_"+i)).value;
    phone=(phone_e=gebi("nm_phone_"+i)).value;
    if (name.length) {
      if (name.indexOf(" ") < 0) {
        msg.errStr="Please include a first & last name for write-in #"+i+" ("+name+"). We cannot accept a single name."; e=name_e; rc=false;
      } else {
        if (!addr.length) {
          msg.errStr="Please include a street address for "+name+" (write-in #"+i+")."; e=addr_e; rc=false;
        } else if (!city.length) {
          msg.errStr="Please include a city for "+name+" (write-in #"+i+")."; e=city_e; rc=false;
        } else if (!state.length) {
          msg.errStr="Please include a state abbreviation for "+name+" (write-in #"+i+")."; e=state_e; rc=false;
        } else if (!zip.length) {
          msg.errStr="Please include a zip code for "+name+" (write-in #"+i+")."; e=zip_e; rc=false;
        } else if (!state.match(states)) {
          msg.errStr="Please enter a valid state abbreviation for "+name+". "+state+" is not valid."; e=state_e; rc=false;
        } else if (!zip.match(zips)) {
          msg.errStr="Please enter a valid zip code for "+name+" (write-in #"+i+")."; e=zip_e; rc=false;
        } else if (gebi("nm_id_"+i).value==="invalid") {
          msg.errStr=name+" (write-in #"+i+") matches someone on the Anshai Torah membership or staff list.  Write-ins must be non-members only.  Please clear this entry or change the name.";
          e=name_e; rc=false;
        } else if (gebi("local_"+i).checked) {
          if (!isDeliverable(city, state)) {
            msg.errStr="Baskets can only be delivered to local addresses. The address you entered for "+name+" (write-in #"+i+") is outside our delivery area.";
            msg.errStr+="  Please select the Ship option or correct the address.";
            e=gebi("ship_"+i); rc=false;
          } else if (!phone.length) {
            msg.errStr="Please include a phone number for "+name+" (write-in #"+i+") to increase odds of a successful delivery.  ";
            msg.errStr+="If you do not have the number, you can fill in \"unknown.\""; e=phone_e; rc=false;
          }
        } else if (!phone.length && isDeliverable(city, state)) {
            msg.errStr="Please provide a phone number for "+name+" (write-in #"+i+") in case we decide to hand-deliver to this local address.  ";
            msg.errStr+="If you don't know the number, simply enter \"unknown.\""; e=phone_e; rc=false;
        }
        for (j=1; j<i && rc; j+=1) {
          name2=gebi("nm_name_"+j).value;
          id=gebi("nm_id_"+i).value;
          id2=gebi("nm_id_"+j).value;
          if (name.toLowerCase()===name2.toLowerCase()) {
            msg.errStr="You listed the same name for write-ins "+j+" and "+i+" ("+name+")."; e=name_e; rc=false;
          } else if (id && id2 && id==id2) {
            msg.errStr="Write-in #"+j+" ("+name2+") and write-in #"+i+" ("+name+") reference the same person or family in our database. Please change one of the entries.";
            e=name_e; rc=false;
          }
        }
      }
    } else {
      if (addr && city && state && zip) {
        msg.errStr="Please fill in a name for write-in #"+i+"."; e=name_e; rc=false;
      }
    }
  }
  if (needToCheckWriteins) {
    msg.errStr="Please confirm the pre-populated non-member addresses on page 3.";
    e=gebi("writein_1");
    rc=false;
  }
  if (!rc) {
    showTab(3);
    msg.e=e;
  }
  return rc;
}

function chkTotal(msg,items) {
  // modified in 2015 to allow for $0 orders as long as reciprocity is selected
  if (!gebi("recip_checkbox").checked) {
    var donation = parseFloat(gebi("donation").value);
    var driver = gebi("driver_checkbox").checked;
    var volunteer = gebi("volunteer_checkbox").checked;
    var spb = (self_pickup? " or self-pickup basket" : "");
    if (items==0 && !donation && !driver && !volunteer && !prevOrder) {
      msg.errStr="Your order total is $0. Please select at least one name, include at least one write-in"+spb+", enter a donation amount, or check the box for reciprocity.";
      showTab(2);
      return false;
    }
  }

  return true;
}

function chkNotes(msg) {
  if (gebi("notes").value.length>500) {
    msg.errStr="The notes/instructions you entered exceed the limit of 500 characters. Please delete some of the text.";
    msg.e=gebi("notes");
    showTab(5);
    return false;
  }
  return true;
}

function validatePage1 () {
  var msg={errStr:"",e:null};
  if (chkName(msg)&&chkPhone(msg)&&chkEmail(msg)) {
    return true;
  } else {
    alert(msg.errStr);
    if (msg.e) msg.e.focus();
    return false;
  }
}

function validatePage2 () {
  var msg={errStr:"",e:null},totals=calculate();
  if (chkRecip(msg,totals.mem_count)) {
    return true;
  } else {
    alert(msg.errStr);
    if (msg.e) msg.e.focus();
    return false;
  }
}

function validatePage3 () {
  var msg={errStr:"",e:null};
  if (chkAjax(msg)&&chkWriteins(msg)) {
    return true;
  } else {
    alert(msg.errStr);
    if (msg.e) msg.e.focus();
    return false;
  }
}

function validateAndSubmit(but) {
  var msg={errStr:"",e:null},totals=calculate(),i;
  var items=totals.mem_count+totals.local_count+totals.ship_count+totals.extra_count+((totals.bene_price>0)?1:0);
  but.blur();
  if (chkAjax(msg)&&chkName(msg)&&chkPhone(msg)&&chkEmail(msg)&&chkRecip(msg,totals.mem_count)&&chkPmt(msg,items)&&chkWriteins(msg)&&chkTotal(msg,items)&&chkNotes(msg)) {
    gebi("mem_count").value=totals.mem_count;
    gebi("writein_count").value=writein_count;
    gebi("nameformat").value=gebi("appear_name").value;
    for (i=0; i<4; i+=1) {
      if (gebi("appear"+i).checked) {
        gebi("nameformat").value=nameChoices[i];
        break;
      }
    }
    warnOnUnload=false;
    but.disabled=true;
    document.body.style.cursor="wait";
    gebi("magic_key").value="shalom"+(4*10000+2136);
    window.setTimeout("reenableSubmit('"+but.id+"')",15000); // allow retry after 15 secs (useful if accidentally canceled)
    gebi("main_form").submit();
  } else {
    alert(msg.errStr);
    for (i=1; i<=4; i+=1) gebi("submit_button_"+i).style.display="inline";
    if (msg.e) msg.e.focus();
  }
}

function reenableSubmit(but_id) {
  var but=gebi(but_id);
  if (but) but.disabled=false;
  document.body.style.cursor="auto";
}

function buildPreloadText() {
  var bene=gebi("bene_checkbox").checked,html="",pronoun="your",Pronoun="Your";
  var d=new Date();
  if (admin_user) {
    html="<b><i>Please note...</i></b>";
    pronoun="the";
    Pronoun="The";
  } else {
    html="<b><i>Thank you for participating again this year!</i></b>";
  }
  html+="<p>We've prefilled "+pronoun+" order form with information taken from "+preload_order_text+". ";
  if ((preload_checklist.length+preload_writeins.length+preload_candidates.length > 0) || bene) {
    html+="This includes the following:</p><ul style='list-style-type:square;'>";
    if (bene) {
      html+="<li>Benefactor order for everyone on the membership list</li>";
    } else {
      var recip=0;
      for (var i=0;i<=preload_checklist_r.length;++i) if (preload_checklist_r[i]=="1") ++recip;
      if (preload_checklist.length > 1) html+="<li><b>"+preload_checklist.length+"</b> names from the Member List (on page 2)";
      if (preload_checklist.length === 1) html+="<li><b>1</b> name from the Member List (on page 2)";
      if (recip > 0) {
        html+="<ul style='list-style-type:none;margin-bottom:8px;'>"
        if (recip === 1) html+="<li>&mdash;including 1 name which was added for reciprocity (indicated with a * on the checklist)</li></ul>";
        if (recip > 1) html+="<li>&mdash;including "+recip+" names which were added for reciprocity (on "+preload_order_text+"), each indicated with a * on the checklist</li></ul>";
      }
      html+="</li>";
    }
    if (preload_writeins.length > 1) html+="<li><b>"+preload_writeins.length+"</b> Non-Member write-ins (on page 3)</li>";
    if (preload_writeins.length === 1) html+="<li><b>1</b> Non-Member write-in (on page 3)</li>";
    if (preload_candidates.length > 1) html+="<li><b>"+preload_candidates.length+"</b> write-ins for people/families who are no longer members of Anshai Torah*</li>";
    if (preload_candidates.length === 1) html+="<li><b>1</b> write-in for a person/family who is no longer a member of Anshai Torah*</li>";
    if (preload_extras === 1) html+="<li><b>1</b> extra basket for self pickup (on page 4)</li>";
    if (preload_extras > 1) html+="<li><b>"+preload_extras+"</b> extra baskets for self pickup (on page 4)</li>";
    html+="</ul>";

    if (preload_candidates.length > 0) {
      html+="<p style='color:#f00000'><b>Note:</b> "+Pronoun+" previous order included one or more people who are no longer affiliated with Anshai Torah (or are no longer part of our CATS in College/Military program). These are now listed with the Non-Members on page 3. ";
      html+="If you do not wish to pay extra to ship a basket to one of these individuals or families, simply clear the entry from the list.</p>";
    }
  } else {
    html+="</p>";
  }
  return html;
}

<?php
if ($preload_order > 0) {
  echo "function preloadOrder() {\n";
  echo "  var i,welcome=populateNameFields('".$preload_person["FirstNames"]."','".$preload_person["LastName"]."','".$preload_person["OfficialFirstNames"]."','".$preload_person["OfficialLastName"];
  echo "',".$preload_person["AndFamily"].",'".(array_key_exists("CustomName",$preload_person)?$preload_person["CustomName"]:"")."');\n";
  echo "  nameid=".$preload_person["NameID"].";\n";
  echo "  gebi('memberid').value=nameid;\n";
  echo "  gebi('memberstatus').value='".$preload_person["Status"]."';\n";
  echo "  gebi('staffmember').value=".$preload_person["Staff"].";\n";
  echo "  gebi('phone').value='".$preload_person["PhoneNumber"]."';\n";
  echo "  gebi('email').value='".$preload_person["Email"]."';\n";
  echo "  gebi('old_phone').value='".$preload_person["PhoneNumber"]."';\n";
  echo "  gebi('old_email').value='".$preload_person["Email"]."';\n";

  if ($preload_person["AllMembers"]) {
    echo "  gebi('bene_checkbox').checked=true;\n";
    echo "  bene_change();\n";
  } elseif ($preload_person["Reciprocity"]) {
    echo "  gebi('recip_checkbox').checked=true;\n";
  } else {
    echo "  gebi('recip_checkbox').checked=false;\n";
  }

  if ($preload_pmt_type=="check") {
    echo "  gebi('paybycheck').checked=true;\n";
  } else if ($preload_pmt_type=="echeck") {
    echo "  gebi('paybyecheck').checked=true;\n";
  } else {
    echo "  gebi('paybycredit').checked=true;\n";
  }

  if ($admin_user != "") {
    echo "  gebi('welcome2').innerHTML='Order for: '+welcome;\n";
  } else {
    echo "  gebi('welcome2').innerHTML='Welcome, '+welcome+'!';\n";
  }

  echo "  gebi('wrong').style.display='none';\n";
  echo "  gebi('reorder_note').innerHTML=\"Note: This form has been preloaded with data from ".$preload_order_text.".\";\n";
  echo "  gebi('reorder_note').style.display='inline';\n";

  echo "  for (i=0;i<preload_writeins.length;++i) fill_writein(preload_writeins[i],'Note: Imported from previous order. Please confirm the information is still correct.');\n";
  echo "  for (i=0;i<preload_candidates.length;++i) fill_writein(preload_candidates[i],'<b>Note: Imported from previous year but no longer a member of Anshai Torah (or our CATS in College program). Address may be outdated &ndash; please confirm!</b>');\n";
  echo "  if (preload_writeins.length+preload_candidates.length > 0) needToCheckWriteins=true;\n";

  if ($self_pickup && (($preload_extras > 0) || ($preload_person["Mapsco"] == "AT"))) {
    echo "  gebi('pickup_checkbox').checked=true;\n";
    echo "  pickup_change();\n";
    echo "  gebi('extra_count').value=".$preload_extras.";\n";
  }

  echo "  activateForm();\n";

  echo "  gebi('scrim').style.display='block';\n";
  echo "  gebi('dialog').style.display='block';\n";
  echo "  gebi('dialog_text').innerHTML=buildPreloadText();\n";

  echo "}\n";
}
?>

function close_dialog() {
  gebi("dialog").style.display="none";
  gebi("scrim").style.display="none";
}

function init() {
  var lname=gebi("lname"),inputs;
  lname.disabled=false;
  gebi("main_form").reset();
  gebi("lookup").disabled=false;
  gebi("fname_select").disabled=false;
  gebi("appear_name").disabled=true;
  gebi("lname_select").style.display="none";
  gebi("start_over").style.display="none";
  gebi("order_num").value="";
<?php
  if ($site_online) {
    if ($preload_order > 0) {
      echo "  preloadOrder();\n";
    } else {
      if ($admin_user != "") {
        echo "gebi('lname_prompt').innerHTML='Please enter the member\'s last name:';\n";
        echo "gebi('overview_link').style.display='none';\n";
      }
      if ($reload_nameid > 0) {
        echo "gebi('top').style.cursor='wait';\n";
        echo "gebi('wrong').style.visibility='hidden';\n";
        echo "ezAjax.initiate('fname', {'id':nameid}, fname_callback);\n";
      } else {
        echo "gebi('findname').style.display='block';\n";
        echo "lname.focus();\n";
      }
    }
  }
?>
}

if (!isTouchBrowser()) {
  window.onbeforeunload=function() {
    if (warnOnUnload) return "You will lose any information you have entered on the order form.";
  }
}

function startOver() {
  warnOnUnload=false; // for some reason this var change is ineffective on IE; not sure why
  window.location.reload();
}

</script>
</head>

<body onload="init();" onresize="resizePages(false);">
<form id="main_form" action="" method="post" onkeydown="return ignoreEnter(event);">
<input type="hidden" name="action" value="submit"/>
<input type="hidden" id="magic_key" name="magic_key" value="nice_try"/>
<input type="hidden" id="order_num" name="order_num" value=""/>
<input type="hidden" id="memberid" name="member" value=""/>
<input type="hidden" id="memberstatus" name="status" value=""/>
<input type="hidden" id="staffmember" name="staff" value="0"/>
<input type="hidden" id="mem_count" name="mem_count" value=""/>
<input type="hidden" id="old_phone" name="old_phone" value=""/>
<input type="hidden" id="old_email" name="old_email" value=""/>
<input type="hidden" id="writein_count" name="nm_count" value=""/>
<input type="hidden" id="invited" name="invited" value=""/>
<h1><?= $page_title ?></h1>
<div id="scrim" style="display:none"></div>
<div id="dialog" style="display:none"><div id="dialog_text"></div>
<div style="margin-top:12px;text-align:center;"><input type="button" value=" OK " onclick="close_dialog();" /></div>
</div>
<div id="top">
<div id="summary" style="display:none;">
<table class="summary">
<caption><?php
if ($admin_user == "") {
  echo "Your Order Summary";
} else {
  echo "Order Summary";
}
?>
</caption>
<tr id="title_row" onclick="showTab(1);"><th></th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr>
<tr id="mem_row" onclick="showTab(2);"><td id="row1_label">Names or Benefactor</td><td id="mem_qty"></td><td id="mem_price"></td><td id="mem_st"></td></tr>
<!--
<tr id="local_row" onclick="showTab(3);"><td>Local Non-Member Orders</td><td id="local_qty"></td><td id="local_price"></td><td id="local_st"></td></tr>
-->
<tr id="ship_row" onclick="showTab(3);"><td>Baskets for Non-Members</td><td id="ship_qty"></td><td id="ship_price"></td><td id="ship_st"></td></tr>
<tr id="extra_row" onclick="showTab(4);"><td>Extra Baskets (self pickup)</td><td id="extra_qty"></td><td id="extra_price"></td><td id="extra_st"></td></tr>
<tr id="total_row" onclick="showTab(numPages); retotal();"><td>Total</td><td></td><td></td><td id="total">$0.00</td></tr>
</table>
</div>
<h3 id="welcome1" style="text-align:center;margin-top:15pt;color:#4040c0;font-size:14pt">
<?php
if ($site_online) {
  if ($admin_user != "") {
    echo "Welcome, ".$admin_name."!";
  } else {
    echo "Welcome!";
  }
} else {
  echo $offline_msg;
}
?>
</h3>
<div id="banner" style="display:none;"><h2 id="welcome2"></h2>
<a id="wrong" class="small" href="javascript:startOver()">(Wrong person? Click here to start over.)</a>
<span id="reorder_note" class="warning" style="display:none;"> </span>
<p id="overview_link" style="font-size:11pt;margin-top:20pt;">
If you're not familiar with Shalach Manot, click <a href="Shalach_Manot.htm" target="about">here</a> for an overview.
</p>
</div>
<noscript><h3 style="color:#f00000;text-align:center;margin:12px;">
This form requires Javascript. You must use a browser that supports Javascript and has scripting enabled.
</h3></noscript>
<div id="findname" style="display:none;">
<p class="center"><label for="lname"><span id="lname_prompt">To get started, please enter your last name:</span></label><br/>
<input id="lname" type="text" class="textinput" name="lastname" size="25" maxlength="25" style="text-align:center" onkeydown="return keydown(event,findLastName);" onfocus="inputF(this);" onblur="inputB(this);"/>
<input id="firstname" type="hidden" name="firstname" value=""/>
<input id="olname" type="hidden" name="olname" value=""/>
<input id="ofname" type="hidden" name="ofname" value=""/>
<select id="lname_select" class="textinput" style="display:none;" onfocus="inputF(this);" onblur="inputB(this);"></select>
<input id="lookup" type="button" value="Lookup" onclick="findLastName();"/>
<input id="start_over" type="button" value="Start Over" style="display:none;" onclick="startOver();"/>
</p>
<p id="fname" class="center" style="visibility:hidden;"><label for="fname_select">
<span id="fname_prompt" >Select your first name(s) from the following list:</span></label><br/>
<select id="fname_select" class="textinput" onchange="findFullName();" onkeydown="return ignore(event);" onfocus="inputF(this);" onblur="inputB(this);"></select>
&nbsp;<input type="button" value="Start Over" onclick="startOver();"/>
</p>
<p class="logo"><img src="logo.png" alt="Anshai Torah - Welcome Home!" width="72" height="76"></p>
</div>
<div id="enterpin" style="display:none;">
<div class="center"><p id="pin_prompt">
<?php
if ($admin_user == "") {
  echo "You've already submitted an order for this year.<br/>Please enter your Personal Identification Number to view/edit your order.";
} else {
  echo "<span id='member_on_file'></span><br/>Click the Load Order button to view/edit the existing order.";
}
?>
</p>
<label id="pin_label" for="pin">PIN:</label>
<input id="pin" type="password" class="textinput" name="pin" size="4" maxlength="4" onkeydown="return keydown(event,validatePin);" onfocus="inputF(this);" onblur="inputB(this);"/>
<input id="pin_submit" type="button" value="Load Order" onclick="validatePin();"/>
<input id="pin_forgot" type="button" value="Lost PIN" onclick="sendPin();"/>
<input id="pin_so" type="button" value="Start Over" onclick="startOver();"/>
</div>
</div>
<div id="preload" class="center" style="display:none;">
<p id="lastyear"> </p>
<input id="preload_but_1" type="button" value="Yes, please tell me how" onclick="preloadPrep();"/>
<input id="preload_but_2" type="button" value="Yes" onclick="preloadWithPin();" style="display:none;"/>
<input type="button" value="No, I'd like to start with a clean slate" onclick="gebi('preload').style.display='none';activateForm();"/>
</div>
<div id="preload_2" class="center" style="display:none;">
<div id="preload_instrux">
<p>In order to grab information from your previous order, you'll need to provide a Personal Identification Number (PIN).</p>
<p>If you already have your PIN, please enter it below. Otherwise press the "Email Me" button.</p>
</div>
<div id="preload_s" style="display:none;">
<p>We've sent you an e-mail with your PIN and a link to access your personalized order form.</p>
<p>The message was sent to the e-mail address you provided with your last order.</p>
<p>If for some reason you can't access the e-mail, you can always start fresh with a blank order form.
Click/tap <a href="javascript:gebi('preload_2').style.display='none';activateForm();" title="open order form">here</a> to do so.</p>
</div>
<label id="preload_pin_label" for="preload_pin">PIN:</label>
<input id="preload_pin" type="password" class="textinput" name="pin" size="4" maxlength="4" onkeydown="return keydown(event,preloadWithPin);" onfocus="inputF(this);" onblur="inputB(this);"/>
<input type="button" value="Open Order Form" onclick="preloadWithPin();"/>
<input id="sendlink_but" type="button" value="Email Me" onclick="sendLink();"/>
<input type="button" value="Start Over" onclick="startOver();"/>
</div>
<div id="preload_f" class="center" style="display:none;">
<p>We're sorry, but something has gone wrong.<br/>
We were unable to send you an e-mail with a link to your personalized order form.</p>
<p>You may have to start fresh with a blank order form (but check your e-mail first).<br/>
Click/tap <a href="javascript:gebi('preload_f').style.display='none';activateForm();" title="open order form">here</a> to do so.</p>
</div>
</div>

<div id="mid" style="display:none">
<div id="tabs">
<div id="tab1" class="tab" onmouseover="onTab(1)" onmouseout="offTab(1)" onmousedown="showTab(1);return ignore(event);" onmouseup="return ignore(event);">1. Start</div>
<div id="tab2" class="tab" onmouseover="onTab(2)" onmouseout="offTab(2)" onmousedown="showTab(2);return ignore(event);" onmouseup="return ignore(event);">2. Member List</div>
<div id="tab3" class="tab" onmouseover="onTab(3)" onmouseout="offTab(3)" onmousedown="showTab(3);return ignore(event);" onmouseup="return ignore(event);">3. Non-Members</div>
<?php
if ($self_pickup) {
  echo '<div id="tab4" class="tab" onmouseover="onTab(4)" onmouseout="offTab(4)" onmousedown="showTab(4);return ignore(event);" onmouseup="return ignore(event);">4. ';
  if ($extra_baskets) {
    echo 'Extra Baskets</div>';
  } else {
    echo 'Self Pickup</div>';
  }
  echo "\n";
  echo '<div id="tab5" class="tab tablast" onmouseover="onTab(5)" onmouseout="offTab(5)" onmousedown="showTab(5);return ignore(event);" onmouseup="return ignore(event);">5. Finish</div>';
} else {
  echo '<div id="tab4" class="tab tablast" onmouseover="onTab(4)" onmouseout="offTab(4)" onmousedown="showTab(4);return ignore(event);" onmouseup="return ignore(event);">4. Finish</div>';
}
echo "\n";
?>
<div style="height:36px; margin-bottom:4px;">
<div id="prev_tab" class="arrow" onmouseover="onArrow(this)" onmouseout="offArrow(this)" onmousedown="showPrevTab();return ignore(event);" onmouseup="return ignore(event);" style="color:#d0d0d0;">&lt; prev</div>
<div class="arrow" style="border-style:none;font-weight:normal;cursor:auto;">page</div>
<div id="next_tab" class="arrow" onmouseover="onArrow(this)" onmouseout="offArrow(this)" onmousedown="showNextTab();return ignore(event);" onmouseup="return ignore(event);">next &gt;</div>
</div>
<div class="logo"><img src="logo.png" alt="Anshai Torah - Welcome Home!" width="72" height="76"></div>
<div style="height:20px;"></div>
</div>

<div id="container">

<div id="page1" class="page">
<div id="appearance">
<h3 class="nospace">
<?php
if ($admin_user == "") {
  echo "How would you like your name to appear on the cards? &nbsp;Select one of the options below:";
} else {
  echo "How should the member's name appear on the cards? &nbsp;Select one of the options below:";
}
?>
</h3>
<input id="appear0" type="radio" name="appear" value="name" onclick="blur(this);" onchange="otherName_change();"/>
<label id="appear0_text" for="appear0"></label>
<input id="appear1" type="radio" name="appear" value="nameAndFamily" onclick="blur(this);" onchange="otherName_change();"/>
<label id="appear1_text" for="appear1"></label>
<input id="appear2" type="radio" name="appear" value="official" onclick="blur(this);" onchange="otherName_change();"/>
<label id="appear2_text" for="appear2"></label>
<input id="appear3" type="radio" name="appear" value="officialAndFamily" onclick="blur(this);" onchange="otherName_change();"/>
<label id="appear3_text" for="appear3"></label>
<div style="margin-top:6pt;margin-bottom:0pt;"><input id="appear4" type="radio" name="appear" value="other" onclick="blur(this);" onchange="otherName_change();"/>
<label for="appear4">Other:</label>
<input type="text" id="appear_name" name="appear_name" class="textinput" size="60" maxlength="60" disabled="disabled" onkeydown="return noBS(event);" onchange="trim_contents(this);" onfocus="inputF(this);" onblur="inputB(this);"/>
<span class="help" id="appear4_help" style="visibility:hidden">&nbsp;Enter name exactly as you want it to appear.</span>
<input type="hidden" id="nameformat" name="nameformat" value=""/>
</div>
</div>
<div class="spaceabove" id="contact_info">
<h3>
<?php
if ($admin_user == "") {
  echo "Please provide a phone number and e-mail address for questions about your order.";
} else {
  echo "Enter the phone number and/or e-mail address associated with this order (if provided).";
}
?>
</h3>
<div class="nowrap"><label for="phone">Phone #:</label>
<input type="text" id="phone" class="textinput" name="phone" title="Enter 10-digit phone number: XXX-XXX-XXXX" size="15" maxlength="32" onkeydown="return noBS(event);" onfocus="inputF(this);" onblur="inputB(this);clean_phone_num(this);"/>
&nbsp;&nbsp;</div>
<div class="nowrap"><label for="email">E-mail address:</label>
<input type="text" id="email" class="textinput" name="email" title="Enter e-mail address: user@domain" size="30" maxlength="64" onkeydown="return noBS(event);" onfocus="inputF(this);" onblur="inputB(this);trim_contents(this);"/>
</div></div>
<div class="spaceabove" id="benefactor">
<label for="bene_checkbox">
<script type="text/javascript">
if (admin_user) {
  document.write("Check here for <b>Shalach Manot Benefactor</b>:");
} else {
  document.write("Check here to become a <b>Shalach Manot Benefactor</b> for "+price_benefactor.dollars()+":");
}
</script>
</label>
<input id="bene_checkbox" class="stdbox" type="checkbox" name="benefactor" value="1" onclick="blur(this);" onchange="bene_change();retotal();"/>
<script type="text/javascript">
if (!admin_user) document.write("<ul><li>Shalach Manot Benefactors sponsor baskets for the entire synagogue (over "+membership+
  " families), including members, associate members, and synagogue staff.</li><li>Your name <i>or the name of your business</i> will appear on every card, except those sent to non-members.</li><li>Benefactors support our CATs in College program by sponsoring additional baskets for around "+cats+" college students (for the same $500).</li>"+
  (benefactor_extra ? "<li><strong><i>"+benefactor_extra+"</i></strong></li>" : "")+
  (benefactor_extra ? "" : "<li><i>This is a great way to support our shul!</i></li>")+"</ul>");
</script>
</div>
<div class="spaceabove" id="reciprocity">
<label class="spaceabove" for="recip_checkbox">Check here for <b>Reciprocity</b>:</label>
<input id="recip_checkbox" class="stdbox" type="checkbox" name="reciprocity" value="1" onclick="blur(this);" checked="1"/>
<script type="text/javascript">
if (!admin_user) document.write("<ul><li>This option automatically sponsors baskets for any member who has sponsored one for you, "+
  "in the event you did not already choose their name from the list.</li>"+
  "<li>Uncheck the box above if you do NOT want reciprocity applied to your order.</li><li>The cost is "+price_basket.dollars()+
  " per additional basket.&nbsp; Anshai Torah will bill you for the extra amount.&nbsp; You will receive a confirmation call if the amount is "+
  (10*price_basket).dollars()+" or greater (10 additional names).</li><li>You will not automatically reciprocate for families who sponsor baskets for the entire congregation.</li>");
//  "<li><i><b>Note:</b> You must select at least 5 names from the membership list to be eligible for reciprocity.</i></li></ul>");
</script>
</div>
<p class="spaceabove" id="goto_page2">Proceed to &nbsp;<button type="button" onclick="if (validatePage1()) showTab(2);">Page 2</button>&nbsp;
to select names from the membership list.</p>
<p class="spaceabove" id="goto_page3" style="display:none">Proceed to &nbsp;<button type="button" onclick="if (validatePage1()) showTab(3);">Page 3</button>&nbsp;
to send baskets to non-members.&nbsp;
Or else proceed to &nbsp;<button type="button" onclick="if (validatePage1()) showTab(4);">Page 4</button>.</p>
<p id="submit_button_1" style="display:none;">
<input id="s_b_1" class="submit" type="button" value="Submit" onclick="validateAndSubmit(this);"/>&nbsp;
<span class="help">&lt;&lt; Press this button when ready to submit the order.</span></p>
</div>

<div id="page2" class="page" style="cursor:progress;">
<input type="button" id="reset_list" value="Deselect All" title="Click to deselect all names in checklist" onclick="resetChecklist()" style="display:none;"/>
<p id="listhelp" class="nospace">Select names from the 4 lists below to sponsor Shalach Manot baskets for individuals or families.&nbsp; The price for each basket is
<script type="text/javascript">document.write(price_basket.dollars());</script>.</p>
<p id="listhelp2" style="display:none;">Names marked with a star (*) were automatically selected due to reciprocity.</p>
<div id="checklist"><h2><i>Checklist is loading...please wait.</i></h2><br/>
<button id="retry_but" type="button" onclick="retryChecklist();">If list does not load after a few seconds, click/tap here to retry.</button>
</div>
<div id="nolist" style="display:none">Since you have chosen to become a <a href="javascript:showTab(1)">Benefactor</a>,
there is no need to select individual names from the membership list.&nbsp;
Your name will automatically appear on the baskets for all members, associate members, teachers, and synagogue staff.</div>
<p class="spaceabove">Proceed to &nbsp;<button type="button" onclick="if (validatePage2()) showTab(3);">Page 3</button>&nbsp;
to send baskets to non-members.&nbsp;
Or else proceed to &nbsp;<button type="button" onclick="if (validatePage2()) showTab(4);">Page 4</button>.</p>
<p id="submit_button_2" style="display:none;">
<input id="s_b_2" class="submit" type="button" value="Submit" onclick="validateAndSubmit(this);"/>&nbsp;
<span class="help">&lt;&lt; Press this button when ready to submit the order.</span></p>
</div>

<div id="page3" class="page">
<h2 class="nospace">Baskets for Non-Members</h2>
<p>Fill out the spaces below to send to non-members.&nbsp; Press "+" to add more entries.&nbsp; Press "-" to remove the last entry.</p>
<p><b>Note:</b> The price is <script type="text/javascript">document.write(price_nm_ship.dollars());</script> for each non-member.&nbsp;
<?php
if ($self_pickup) echo 'If you want baskets to deliver yourself, go to <a href="javascript:showTab(4)">next page</a> to order self-pickup baskets for $'.sprintf("%.2f",$price_extra).' apiece.';
?>
</p>
<div id="writeins">
</div>
<input id="del_one" type="button" value="-" title="Remove last entry" style="font-weight:bold;width:2em;" disabled="disabled" onclick="del_writein();retotal();blur(this);"/>
<input id="add_one" type="button" value="+" title="Add one more entry" style="font-weight:bold;width:2em;" onclick="add_writein();blur(this);"/>
<p class="spaceabove">When you're done, proceed to &nbsp;<button type="button" onclick="if (validatePage3()) showTab(4);">Page 4</button></p>
<p id="submit_button_3" style="display:none;">
<input id="s_b_3" class="submit" type="button" value="Submit" onclick="validateAndSubmit(this);"/>&nbsp;
<span class="help">&lt;&lt; Press this button when ready to submit the order.</span></p>
</div>

<?php
if ($self_pickup) {
  echo "<div id='page4' class='page page4'>\n";
} else {
  echo "<div class='page'>\n";
}
?>
<h2 class="nospace"><?php
if ($extra_baskets) {
    echo "Extra Baskets";
} else {
    echo "Self Pickup";
}?></h2>
<p><?= $self_pickup_text ?></p>
<input id="pickup_checkbox" class="stdbox" type="checkbox" name="pickup" value="1" onclick="blur(this);" onchange="pickup_change();"/>
<label class="spaceabove" for="pickup_checkbox">
<?php
if ($extra_baskets) {
    if ($admin_user == "") {
      echo 'I would like to purchase one or more extra baskets.';
    } else {
      echo 'Member would like to purchase one or more extra baskets.';
    }    
} else {
    if ($admin_user == "") {
      echo 'I will pickup my own basket from the shul.';
    } else {
      echo 'Member will pickup their own basket from the shul.';
    }
}
?>
</label>
<p id="extra_baskets" class="grayout">How many <b>additional baskets</b>
<?php
if ($admin_user == "") echo 'would you like to pickup ';
?>
(<script type="text/javascript">document.write(price_extra.dollars());</script> each)?&nbsp;
<select id="extra_count" name="extra" onchange="blur(this);retotal();" disabled="disabled">
<option value="0">0</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
<option value="8">8</option>
</select>
</p>
<p class="spaceabove">When you're ready, proceed to &nbsp;<button type="button" onclick="showTab(5)">Page 5</button></p>
<p id="submit_button_4" style="display:none;">
<input id="s_b_4" class="submit" type="button" value="Submit" onclick="validateAndSubmit(this);"/>&nbsp;
<span class="help">&lt;&lt; Press this button when ready to submit the order.</span></p>
</div>

<?php
if ($self_pickup) {
  echo "<div id='page5' class='page page5'>\n";
} else {
  // class is "page5" to get the border color right
  echo "<div id='page4' class='page page5'>\n";
}
?>
<div id="payment">
<h2 class="nospace">Payment Options</h2>
<div><label for="donation"><span id="donation_label">Additional donation:</span> $</label>
<input type="text" id="donation" class="textinput" name="donation" size="8" maxlength="8" onchange="donation_change(this);" onfocus="inputF(this);" onblur="inputB(this);"/>&nbsp;

<?php
if ($admin_user == "") {
  echo '<span id="donation_help" class="help">This amount will be added to your total.</span></div></div><p class="spaceabove">Your balance due is: <b><span id="amt_due"></span></b></p>';
  echo '<p>Please choose one of the options below to pay your balance.';
} else {
  echo '</div></div><div class="spaceabove"><label for="total_paid">Enter <b>total amount paid</b>: $</label> ';
  echo '<input type="text" id="total_paid" class="textinput" name="total_paid" size="7" maxlength="8" onchange="payment_change(this);" onfocus="inputF(this);" onblur="inputB(this);"/>';
  echo '</div><p class="spaceabove">Balance Due: <b><span id="amt_due"></span></b></p><p>Choose one of the payment options below:';
}
?>

<span id="paid"> </span>
<?php
  echo '</p>'
?>
<ul class="pmt"><li class="pmt"><input type="radio" id="paybycheck" name="pay" value="check" checked="1" onclick="blur(this);"/>
<label for="paybycheck">
<?php
if (($admin_user) == "") {
  echo '<b>Pay by check</b> &ndash; I will mail a check payable to Congregation Anshai Torah';
} else {
  echo 'Paid or to be paid by <b>check</b>';
}
?>
</label></li>
<li class="pmt"><input type="radio" id="paybycredit" name="pay" value="credit" onclick="blur(this);"/>
<label for="paybycredit">
<?php
if (($admin_user) == "") {
  echo '<b>Pay by credit card</b> &ndash; I will pay online right now via ShulCloud (2.5% fee applies)';
} else {
  echo 'Paid or to be paid by <b>credit card</b>';
}
?>
</label></li>
<li class="pmt"><input type="radio" id="paybyecheck" name="pay" value="echeck" onclick="blur(this);"/>
<label for="paybyecheck">
<?php
if (($admin_user) == "") {
  echo '<b>Pay by eCheck</b> &ndash; I will pay online right now via ShulCloud (1% fee applies)';
} else {
  echo 'Paid or to be paid by <b>e-check</b>';
}
?>
</label></li>
</ul>
<div id="volunteer1">
<?php
if ($admin_user == "") {
  echo '<p class="spaceaboveplus"><b>Help deliver to your neighbors!</b> &nbsp;';
  if (($preload_order > 0) && $preload_person["Driver"]) {
    echo 'Thank you for volunteering to deliver baskets in the past! &nbsp;Please indicate below if you can help us again. &nbsp;'.$delivery_msg.'</p>';
  } else {
    echo 'This year we will hand-deliver approximately '.$deliveries.
         ' Shalach Manot baskets. &nbsp;This task requires a large number of volunteers. &nbsp;'.
         'The more we have, the fewer stops each person has to make. '.$delivery_msg.'</p>';
  }
}
?>
<input id="driver_checkbox" class="stdbox" type="checkbox" name="driver" value="1" onclick="blur(this);"/>
<label class="spaceabove" for="driver_checkbox"><b>
<?php
if ($admin_user == "") {
  echo 'I am interested in helping out with deliveries.';
} else {
  echo 'Delivery volunteer';
}
?>
</b></label></div>
<div id="volunteer2">
<input id="volunteer_checkbox" class="stdbox spaceabove" type="checkbox" name="volunteer" value="1" onclick="blur(this);"/>
<label for="volunteer_checkbox"><b>
<?php
if ($admin_user == "") {
  echo 'I am interested in helping assemble baskets on '.$assembly_date.' from 9 to 11 AM.';
} else {
  echo 'Assembly volunteer';
}
?>
</b></label></div>
<div class="spaceaboveplus" id="instrux">
<label for="notes">
<?php
if ($admin_user == "") {
  echo 'Finally, do you have any <b>special instructions</b> regarding your order?<br/>(Note that all baskets are the same and cannot be customized.)';
} else {
  echo '<b>Notes or special instructions</b>:';
}
?>
</label><br/>
<textarea id="notes" class="textinput" name="notes" rows="1" cols="85" onkeydown="return noBS(event);" onfocus="inputF(this);" onblur="inputB(this);"></textarea>
<p></p>
<p id="submit_button_5" class="spaceaboveplus">
<input id="s_b_5" class="submit" type="button" value="Submit" onclick="validateAndSubmit(this);"/>&nbsp;
<span id="submit_help" class="help">&lt;&lt;
<?php
echo 'Press this button when ready to submit '.(($admin_user=="")?'your':'the').' order.';
?>
</span>
</p>
</div>
</div>
</div>
</div>

<div id="bot">
<address><a class="addr" href="https://anshaitorah.org" title="www.anshaitorah.org" target="_blank">Congregation Anshai Torah</a>
&nbsp;&bull;&nbsp; 5501 W. Parker Road, Plano, Texas 75093 &nbsp;&bull;&nbsp; 972-473-7718</address>
</div>

</form>
</body>
</html>
