# Active Directory Password Expiration Notifcations
This php script can be used to send users daily email notifications warning them
when their password is about to expire.  

### Setup
Modify the variables in the beginning of the script.
*scriptPath: Full path to the PHP script parent directory.
*ldapupn: AD userprincipal name used to bind to AD.
*ldappass: AD userprincipal name password.
*ldaphost: AD domain controller.
*warndays: Number of days to start warning the user.
*useremailheader: Email header information for end-user notifications.
*adminemailto: Admin email address to receive summary of notifications.
*adminemailheader: Email header information for admin notifications.



Edit .tpl files to adjust email format for notifications.

### Running
Execute php script with the -o flag to specifiying which OU to search for users 
with expiring passwords:



Example: /path/to/script/check_expire.php -o "CN=Users, DC=domain, DC=com"

