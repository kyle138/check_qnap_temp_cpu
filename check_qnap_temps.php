#!/usr/bin/php
<?php
/* check_qnap_temps.php
* Check CPU and SYS temperature of QNAP storage devices.
*
* v0.1
*
* The following OIDs are used:
* CPU Temperature: iso.3.6.1.4.1.24681.1.2.5.0
* SYS Temperature: iso.3.6.1.4.1.24681.1.2.6.0
* Return Example: 57 C/134 F
*
* USAGE: check_qnap_temps.php HOST COMMUNITY WARNING CRITICAL
* HOST=IP or FQDN of the target QNAP device
* COMMUNITY=SNMP community name
* WARNING=Temperature value to trigger warning
* CRITICAL=Temperature value to trigger critical
* EXAMPLE: check_qnap_temps.php 192.168.1.1 public CPU 80 100
*
*/

// Constants
define("OID_DEFAULT", "iso.3.6.1.2.1.1.1.0");
define("OID_CPUTEMP", "iso.3.6.1.4.1.24681.1.2.5.0");
define("OID_SYSTEMP", "iso.3.6.1.4.1.24681.1.2.6.0");
define("OID_HDDSLOTS", "iso.3.6.1.4.1.24681.1.2.10.0");
define("OID_HDDMODELS", "iso.3.6.1.4.1.24681.1.2.11.1.5");
define("OID_HDDTEMPS", "iso.3.6.1.4.1.24681.1.2.11.1.3");
define("SCALE", 'C'); // Sets the scale to measure CPU temperatures in, 'C' or 'F'. Defaults to 'C'. WARNING and CRITICAL arguments must be supplied in the same scale this is set to.

// Check if all arguments are supplied
if(count($argv) < 6)
  DisplayMessage(0, "Incomplete statement.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY CHECK WARNING CRITICAL\r\n");

//Assign supplied arguments
list(,$host, $community, $check, $warning, $critical,) = $argv;
$check=strtoupper($check);
$warning=(float)$warning;
$critical=(float)$critical;

//Check If CRITICAL less than WARNING, give usage example and exit.
if($critical < $warning)
  DisplayMessage(0, "The CRITICAL value cannot be lower than the WARNING value.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY CHECK WARNING CRITICAL\r\n");
//Check if CHECK is either 'CPU' or 'SYS'
elseif( empty($check) )
  DisplayMessage(0, "Error, CHECK type not specified. Acceptable values are CPU, SYS, HDDS, or HDD#.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY CHECK WARNING CRITICAL\r\n");
//Check if CHECK is either 'CPU' or 'SYS'
elseif( $check!='CPU' && $check!='SYS' && $check!='HDDS' && !preg_match("/^HDD\d+$/",$check))
  DisplayMessage(0, "Error, CHECK type invalid. Acceptable values are CPU, SYS, HDDS, or HDD#.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY CHECK WARNING CRITICAL\r\n");
//Check if HOST and COMMUNITY values are supplied.
elseif( empty($host) || empty($community) )
  DisplayMessage(0, "Error, host and/or community is empty.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY CHECK WARNING CRITICAL\r\n");

// Test connection, SNMP availability, and valid Community.
GetSnmpObjValue($host, $community, OID_DEFAULT);

switch($check) {
  case 'CPU':
    // Get CPU temperature
    $temperature = GetSnmpObjValue($host, $community, OID_CPUTEMP);
    break;
  case 'SYS':
    // Get SYS temperature
    $temperature = GetSnmpObjValue($host, $community, OID_SYSTEMP);
    break;
  case 'HDDS':
    // Get temperatures of all HDDs by setting DriveNum to 0
    $temperature = CheckHDD($host, $community, 0, OID_HDDTEMPS);
    break;
  case (preg_match("/^HDD\d+$/",$check) ? true : false):
    // Get temperature of specified HDD by DriveNum
    $drivenum = explode("HDD",$check);
    $temperature = CheckHDD($host, $community, $drivenum[1], OID_HDDTEMPS);
    break;
  default:
    // This should never happen due to the previous check, but just in case.
    DisplayMessage(0, "Invalid CHECK value: $check. Acceptable values are 'CPU' and 'SYS'.");
}

// Send value, check, warn, and crit and return the status.
RetStatus($temperature, $check, $warning, $critical);


//******************
// Funcs:
//******************

// Display message and exit with proper integer to trigger Nagios OK, Critical, Warning.
function DisplayMessage($exitInt, $exitMsg) {
  echo $exitMsg;
  exit($exitInt);
} // DisplayMessage()


// Connect and return object value.
// If the host doesn't respond to simple SNMP query, exit.
// If value begins with STRING: return temperature values within the quotes.
function GetSnmpObjValue($host, $community, $oid) {
  $ret = @snmpget($host, $community, $oid);         // Returns 'STRING: "60 C/140 F"' for CPU and SYS temps.
  if( $ret === false )
    DisplayMessage(2, 'Cannot reach host: '.$host.', community: '.$community.', OID: '.$oid.'. Possibly offline, SNMP is not enabled, COMMUNITY string is invalid, or wrong OID for this device.');

  switch($ret) {
    case(preg_match("/^STRING: /",$ret) ? true : false):
      // Strip STRING: and just return the values.
      $ret = explode("\"",$ret);
      $ret = $ret[1];
      break;
    case(preg_match("/^INTEGER: /",$ret) ? true : false):
      // Strip INTEGER: and just return the value.
      $ret = explode(" ",$ret);
      $ret = $ret[1];
      break;
    default:
      DisplayMessage(0, "Unhandled type: $ret");
  }

  return $ret;
} // GetSnmpObjValue()


// Check if returned SNMP object value is a temperature, obj, select C or F based on $scale, and return value.
function GetSnmpObjValueTemperature($SnmpObjValue) {
  $ret = explode("/",$SnmpObjValue);

  if( $ret === false)
    DisplayMessage(0, "Unexpected value: $ret :: Possibly wrong OID for this device.");

  switch(SCALE) {
    case 'C':
      $ret = explode(" ",$ret[0]);
      return $ret[0];
    case 'F':
      $ret = explode(" ",$ret[1]);
      return $ret[0];
    default:
      DisplayMessage(0, "Unexpected value for SCALE: ".SCALE." :: SCALE must be set to 'C' for celcius or 'F' for farenheit.");
  }
} // GetSnmpObjectValueTemperature


// CheckHDD($host, $community, 0, OID_HDDSLOTS, OID_HDDTEMPS);
// Check all HDD temperatures or just a single HDD temperature
function CheckHDD($host, $community, $drivenum, $oid) {
  // Get the number of HDD slots available in the device.
  $slots = GetSnmpObjValue($host, $community, OID_HDDSLOTS);
  // If drivenum is supplied check the temperature of the drive specified.
  if($drivenum > 0) {
    // Check if drivenum is valid
    if($drivenum > $slots)
      DisplayMessage(0, "Invavlid drive specified. Drive Number ($drivenum) cannot be higher than $slots.");
    // Check if drive specified is installed
    $driveoid = OID_HDDMODELS.".$drivenum";
    $model = GetSnmpObjValue($host, $community, $driveoid);
    if($model === "--")
      DisplayMessage(0, "Invalid drive specified. The HDD slot $drivenum is not populated.");
    // Drive is installed, retrieve the temperature value.
    $driveoid = OID_HDDTEMPS.".$drivenum";
    $ret = GetSnmpObjValue($host, $community, $driveoid);

    return $ret;
  } else {
    // No drive specified, check all drives and return maximum value.
    $ret = "-- C/-- F";
    $maxtemp =  0;
    for($i = 0; $i <= $slots; $i++) {
      $driveoid = OID_HDDTEMPS.".$i";
      $val = GetSnmpObjValue($host, $community, $driveoid);
      if($val !== "-- C/-- F" ) {
        $temp = GetSnmpObjValueTemperature($val);
        if($temp > $maxtemp) {
          $maxtemp = $temp;
          $ret = $val;
        }
      }
    }
    return $ret;
  }
} // CheckHDD


// Check if value is Ok, Warn, or Crit, return value to Nagios and shut down.
function RetStatus($value, $check, $warning, $critical) {
  $tempValue = GetSnmpObjValueTemperature($value);
  if($tempValue >= $critical)
    DisplayMessage(2, "Critical - $check Temp - $value | Temperature=$tempValue".SCALE.";$warning;$critical;0;140");
  elseif($tempValue >= $warning)
    DisplayMessage(1, "Warning - $check Temp - $value | Temperature=$tempValue".SCALE.";$warning;$critical;0;140");
  else {
    DisplayMessage(0, "OK - $check Temp - $value | Temperature=$tempValue".SCALE.";$warning;$critical;0;140");
  }
} // RetStatus

?>
