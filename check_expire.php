<?php

// Provided AS IS under the GNU license located at: http://www.gnu.org/licenses/gpl.txt

// To use the script.  
// Set the variables below, then execute the script with the users OU as an argument.
// Example: /path/to/scriptdir/check_expire.php -ou "CN=People, DC=Domain, DC=org"

// After setting the options below, be sure to update the two email templates included
// to include your company logo and instructions for the end-user.

// Some variables will need to be set first.

// Full path to this script.
$scriptPath 	= "/usr/local/pwdexpire/";
// A regular user to bind to AD.  Use the upn format.
$ldapupn		= "ldap_bind@sub.domain.tld";
// That users password
$ldappass 		= "password";
// To make a connection to the domain controller over SSL use ldaps:// instead of ldap://
$ldaphost 		= "ldap://dc.sub.domain.tld/";
//  How many days out to start warning the user.
$warndays		= "15";
// From email header  on end-user notifications.  
$useremailheader	= "	From: IT Support <support@sub.domain.tld>\r\n
						Priority: Urgent\r\nMIME-Version: 1.0\r\n
						Content-Type: text/html; charset=ISO-8859-1\r\n";
						
// Email alias for administrators.  This email will get a listing of the users that are expiring.
$adminemailto 		= "admin@sub.domain.tld";
// From email header  on admin notifications.  
$adminemailheader	= "	From: IT Support <support@sub.domain.tld>\r\n
						Content-Type: text/html; charset=ISO-8859-1\r\n";

// Debugging Options
// 1 is Enabled, 0 is Disabled
// When debug is enabled, no emails will be sent to the users.
$debug			= "0";


// End Options - Begin Workflow

// Default variables
$listforadmin 	= "";
$filter			= "(&(objectCategory=Person)(objectClass=User))";
$attrib			= array("sn", "givenname", "displayName", "sAMAccountName", "msDS-UserPasswordExpiryTimeComputed", "mail");

//Check that the proper command line arguments have been passed to the script.
$argumentOU = getopt("o:");

if ($argumentOU) {
    echo("Checking for expired passwords in OU: {$argumentOU['o']}\n");
	$dn = $argumentOU['o'];
}else{ 
	echo("You must specify an LDAP OU in the arguments passed to this script.  Example: /path/to/scriptdir/scriptname.php -o \"CN=Users, DC=Domain, DC=org\" ");
    exit;
}

// Get current time
$now	= time();
$currentdatehuman = date("m-d-Y", "$now");

/*
AD date values.  Offset is approximate 10millionths of a second from 
1/1/1601 to 1/1/1970 (Epoch).  MS stores the time as number of 100 nanoseconds
since 1/1/1601.  Since we get epoch from now(), we need to add the difference.
*/
$offset		= 116444736000000000;
$oneday		= 864000000000;
$daystowarn	= $oneday * $warndays;

//Set current date in large int as AD does
$dateasadint	= ($now * 10000000) + $offset;

// Set search value for todays date plus warning time.
$warndatethresh	= $dateasadint + $daystowarn;

echo "Current Date: $currentdatehuman\n";
echo "Now in Epoch: $now \n";
echo "Using number days to warn: $warndays\n";

// Connect to LDAP
echo "Beginning LDAP search...\n";
$ldapconn = ldap_connect($ldaphost)
         or die("Could not connect to {$ldaphost}.\n");

if ($ldapconn) {
   
   echo "LDAP connected, attempting bind.\n";
 
   // Bind to LDAP.
   $ldapbind = ldap_bind($ldapconn, $ldapupn, $ldappass);

   // Verify LDAP connected.
   if ($ldapbind) {
       echo "LDAP bind successful.\n";
   } else {
       echo "LDAP bind failed.\n";
   }

}

// Search LDAP using filter, get the entries, and set count.
$search = ldap_search($ldapconn, $dn, $filter, $attrib, 0, 0)
or die ("Could not search LDAP server.\n");

$dsarray = ldap_get_entries($ldapconn, $search);
$count = $dsarray["count"];

echo "$count Entries found.\n";

for($i = 0; $i < $count; $i++) {
	// Converts large int from AD to epoch then to human readable format
	$timeepoch = ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] - 116444736000000000) / 10000000;
	$timetemp = split( "[.]" ,$timeepoch, 2);
	$timehuman = date("m-d-Y H:i:s", "$timetemp[0]");
	echo "Name: {$dsarray[$i]['displayname'][0]} \t\t Date: $timehuman \t{$dsarray[$i]['dn']}\n";
	
			// Check to see if password expiration is within our warning time limit.
			if ($dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] <= $warndatethresh && $dsarray[$i]['msds-userpasswordexpirytimecomputed'][0] >= $dateasadint) {
			
			$listforadmin .= "{$dsarray[$i]['samaccountname'][0]} expires at $timehuman\r\n";
		
				print "WARNING! Password will expire.\n";
				echo "Sending email to {$dsarray[$i]['displayname'][0]} at address {$dsarray[$i]['mail'][0]} \n";
				
				//If debug is enabled, then send all emails to admin
				if($debug=="0") {
				
					//If mail is defined in LDAP use mail, if not send to admin email.
					if($dsarray[$i]['mail'][0]) {
						$userto = "{$dsarray[$i]['mail'][0]}";
					} else { 
						$userto = $adminemailto;
					}
					
					$usersubject = "Password for {$dsarray[$i]['samaccountname'][0]} will expire soon!";
					
					// Warning Email
					// Get the email from a template in the same directory as this script.
					if(file_exists($scriptPath . "user_email.tpl")) {
						$userbody = file_get_contents($scriptPath . "user_email.tpl");
						$userbody = str_replace("__DISPLAYNAME__", $dsarray[$i]['displayname'][0], $userbody);
						$userbody = str_replace("__SAMACCOUNTNAME__", $dsarray[$i]['samaccountname'][0], $userbody);
						$userbody = str_replace("__EXPIRETIME__", $timehuman, $userbody);
					}
			
						// Send the email to the user.
							if (mail($userto, $usersubject, $userbody, $useremailheader)) {
								echo("User email successfully sent.\n");
							} else {
								echo("User email delivery failed.\n");
							}
					//End If Debug
					}
			//End check for expiration within warning time limit.
			}
	
	//Unset some variables before continuing the loop.
	unset($timeepoch);
	unset($timetemp);
	unset($timehuman);
	unset($userto);
	unset($usersubject);
	unset($userbody);
	
//End for loop for each entry in LDAP.
}

//Send email of users to admin.
if ($listforadmin) {
	$adminsubject = "List of Expired Passwords";
	if(file_exists($scriptPath . "admin_email.tpl")) {
				$adminbody = file_get_contents($scriptPath . "admin_email.tpl");
				$adminbody = str_replace("__CURRENTDATE__", $currentdatehuman, $adminbody);
				$adminbody = str_replace("__USERLIST__", $listforadmin, $adminbody);
				$adminbody =  str_replace("__USEROU__", $argumentOU['o'], $adminbody);
			}

		if (mail($adminemailto, $adminsubject, $adminbody, $adminemailheader)) {
                       echo("Admin email successfully sent.\n");
                       } else {
                       echo("Admin email delivery failed.\n");
                 }
}

//  Unbind and Disconnect from Server
$unbind = ldap_unbind($ldapconn);

if ($unbind) {
	echo "LDAP successfully unbound.\n";
} else {
	echo "LDAP not unbound.\n";
}

?>
