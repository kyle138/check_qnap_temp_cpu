# check_qnap_temps
Nagios plugin for checking CPU temperature on QNAP devices via SNMP 

## V0.2
* Updated to check HDD temps.

## The following OIDs are used:
* Default system info: iso.3.6.1.2.1.1.1.0
* CPU Temperature: iso.3.6.1.4.1.24681.1.2.5.0
* SYS Temperature: iso.3.6.1.4.1.24681.1.2.6.0
* Number of available HDD slots in the device: iso.3.6.1.4.1.24681.1.2.10.0
* Root OID for HDD models installed: iso.3.6.1.4.1.24681.1.2.11.1.5
* Root OID for HDD temperatures: iso.3.6.1.4.1.24681.1.2.11.1.3

## Usage:
**COMMAND:** check_qnap_temps.php HOST COMMUNITY CHECK WARNING CRITICAL
**HOST:** IP or FQDN of the target QNAP device
**COMMUNITY:** SNMP community name
**CHECK:** Type of check to run: CPU, SYS, HDDS (all harddrives), or HDD# (specific drive, eg: HDD42)
**WARNING:** Temperature value to trigger warning
**CRITICAL:** Temperature value to trigger critical

## Examples:
* check_qnap_temps 192.168.1.1 public CPU 80 100
* check_qnap_temps 192.168.1.1 public SYS 50 60
* check_qnap_temps 192.168.1.1 public HDDS 60 70
* check_qnap_temps 192.168.1.1 public HDD5 60 70

## Return Example:
OK - CPU Temp - 58 C/136 F 

## Notes:
If you want to use the farenheit scale instead of celcius, define the SCALE constant as 'F' below.
