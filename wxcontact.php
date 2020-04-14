<?php
# wxcontact.php for Saratoga templates
# Setting: select captcha method to use:
$useGoogle = true; // =true; use Google reCaptcha V2, =false; use hCaptcha
# end Setting

/*
Version  3.00 - 14-Apr-2020 rewritten to use Google reCaptcha V2.0 - Ken True - Saratoga-weather.org
*/
  $doStandalone = false;
	if($useGoogle) { 
	  include_once("contact-inc.php");
	} else {
		include_once("contactH-inc.php");
	}
# end wxcontact.php
?>