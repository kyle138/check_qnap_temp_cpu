#!/usr/bin/php
<?php
/* check_qnap_temp_cpu.php
* Check CPU temperature of QNAP storage devices.
*
* v0.1
*
* The following OIDs are used:
* CPU Temperature: iso.3.6.1.4.1.24681.1.2.5.0
* Returns: 57 C/134 F
*
* USAGE: check_qnap_temp_cpu HOST COMMUNITY WARNING CRITICAL
* HOST=IP or FQDN of the target QNAP device
* COMMUNITY=SNMP community name
* WARNING=Temperature value to trigger warning in Celcius
* CRITICAL=Temperature value to trigger critical in Celcius
* EXAMPLE: check_qnap_temp_cpu 192.168.1.1 public 60 70
*
*/

// Constants
define("OID_DEFAULT", "iso.3.6.1.2.1.1.1.0");
define("OID_CPUTEMP", "iso.3.6.1.4.1.24681.1.2.5.0");
define("OID_SYSTEMP", "iso.3.6.1.4.1.24681.1.2.6.0");
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
elseif( empty($check) || ($check!='CPU' && $check!='SYS'))
  DisplayMessage(0, "Error, CHECK type not specified or invalid. Acceptable values are 'CPU' or 'SYS'.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY CHECK WARNING CRITICAL\r\n");
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
function GetSnmpObjValue($host, $community, $oid) {
  $ret = @snmpget($host, $community, $oid);         // Returns 'STRING: "60 C/140 F"' for CPU and SYS temps.
  if( $ret === false )
    DisplayMessage(2, 'Cannot reach host: '.$host.', community: '.$community.', OID: '.$oid.'. Possibly offline, SNMP is not enabled, COMMUNITY string is invalid, or wrong OID for this device.');

  if( strpos($ret,"\"") )
    // Strip STRING: and just return the values.
    $ret = explode("\"",$ret);

  return $ret[1];
} // GetSnmpObjValue()


// Check if returned SNMP object value is a temperature, strip 'STRING: ' obj, select C or F based on $scale, and return value.
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

// Check if value is Ok, Warn, or Crit
function RetStatus($value, $check, $warning, $critical) {
  $tempValue = GetSnmpObjValueTemperature($value);
  if($tempValue >= $critical)
    DisplayMessage(2, "Critical - $check Temp - $value");
  elseif($tempValue >= $warning)
    DisplayMessage(1, "Warning - $check Temp - $value");
  else {
    DisplayMessage(0, "OK - $check Temp - $value");
  }
} // GetSnmpObjStatus

?>
