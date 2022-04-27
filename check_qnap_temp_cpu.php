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
define("SCALE", 'C'); // Sets the scale to measure CPU temperatures in, 'C' or 'F'. Defaults to 'C'.

// Check if all arguments are supplied
if(count($argv) < 4)
  DisplayMessage(0, "Incomplete statement.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY WARNING CRITICAL\r\n");

//Assign supplied arguments
list(,$host, $community, $warning, $critical,) = $argv;
$warning=(float)$warning;
$critical=(float)$critical;

//Check If CRITICAL less than WARNING, give usage example and exit.
if($critical < $warning)
  DisplayMessage(0, "The CRITICAL value cannot be lower than the WARNING value.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY WARNING CRITICAL\r\n");
//Check if HOST and COMMUNITY values are supplied.
elseif( empty($host) || empty($community) )
  DisplayMessage(0, "Error, host and/or community is empty.\r\nUSAGE: check_qnap_temp_cpu HOST COMMUNITY WARNING CRITICAL\r\n");

// Test connection, SNMP availability, and valid Community.
GetSnmpObjValue($host, $community, OID_DEFAULT);

// Get CPU temperature in C
$cpuTemp = GetSnmpObjValue($host, $community, OID_CPUTEMP);
echo "temp:".$cpuTemp."\r\n"; // DEBUG:

$cpuTemp = GetSnmpObjValueTemperature($cpuTemp);
//*********************  We got the temp value, now need to test for warn/crit *************************
DisplayMessage(0, $cpuTemp);    // DEBUG


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
  $ret = @snmpget($host, $community, $oid);
  if( $ret === false )
    DisplayMessage(2, 'Cannot reach host: '.$host.', community: '.$community.', OID: '.$oid.'. Possibly offline, SNMP is not enabled, COMMUNITY string is invalid, or wrong OID for this device.');
  return $ret;
} // GetSnmpObjValue()


// Check if returned SNMP object value is a temperature, strip 'STRING: ' obj, select C or F based on $scale, and return value.
function GetSnmpObjValueTemperature($SnmpObjValue) {
  $ret = explode("/",explode("\"",$SnmpObjValue)[1]);

  if( $ret === false)
    DisplayMessage(0, "Unexpected value: $ret :: Possibly wrong OID for this device.");

  switch(SCALE) {
    case 'C':
      $ret = explode(" ",$ret[0])[0];
      return $ret;
    case 'F':
      $ret = explode(" ",$ret[1])[0];
      return $ret;
    default:
      DisplayMessage(0, "Unexpected value for SCALE: ".SCALE." :: SCALE must be set to 'C' for celcius or 'F' for farenheit.");
  }
} // GetSnmpObjectValueTemperature

?>
