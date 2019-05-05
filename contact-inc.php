<?php
# contact-inc.php for standalone and Saratoga templates
/*
PHP script originally by Mike Challis, www.642weather.com/weather
Version 1.00 - 28-May-2008
Version 1.01 - 29-May-2008 fixed session_start warning "headers already sent"
Version 1.02 - 30-May-2008 added Lat/Lon fields, fixed "TRIM here" comment syntax
Version 1.03 - 07-Jun-2008 added config setting for printing $thank_you message after form is sent
Version 1.04 - 07-Jun-2008 added setting for captch library [path]/[folder]

Version  2.00 - 07-Apr-2018 rewritten to use Google reCaptcha V2.0 - Ken True - Saratoga-weather.org
Version  2.01 - 09-Aug-2018 removed each() for PHP7 compatibility

You are free to use and modify the code
PHP version 5.5 or greater is recommended

*/
############################################################################
# begin settings
############################################################################
# always configure these options before use
# always test your contact form after making changes
#
# This script REQUIRES a google reCaptcha key pair.  Use
# https://www.google.com/recaptcha/admin to acquire a key pair
#  insert your Site Key and Site Secret Key in the two variables below

 $recaptchaSiteKey =   '-google-site-key-';   // your reCaptcha site key
 $recaptchaSecretKey = '-google-secret-key-';   // your reCaptcha site secret key

# Optional log file.  use '' as the name if a log file is not desired.
 $logFile = './contact-log.txt'; // optional text log of messages.  use '' to disable.

 # email address to receive messages from this form
 $mailto = 'somebody@somesite.com';

 # Site Name / Title
 $sitename = 'My Sitename';

############################################################################
# end settings
############################################################################
if(!isset($doStandalone)) {$doStandalone = true; }
############################################################################
if(!$doStandalone) {
  require_once("Settings.php");
  require_once("common.php");
  ############################################################################
  $TITLE= $SITE['organ'] . " - Contact";
  $showGizmo = false;  // set to false to exclude the gizmo
  include("top.php");
  ############################################################################
?>
  <script type="text/javascript" src="https://www.google.com/recaptcha/api.js"></script>
  </head>
  <body>
<?php
  ############################################################################
  include("header.php");
  ############################################################################
  include("menubar.php");
  ############################################################################
?>

<div id="main-copy">
<?php
} // end !doStandalone
# Shim function if run outside of AJAX/PHP template set
# these must be before the missing function is called in the source
if(!function_exists('langtransstr')) {
	function langtransstr($item) {
		return($item);
	}
}
if(!function_exists('langtrans')) {
	function langtrans($item) {
		print $item;
		return;
	}
}
print "<!-- contact-inc.php V2.01 - 09-Aug-2018 -->\n";
?>

<?php
############################################################################
# Do not alter any code below this point in the script or it may not run properly.
############################################################################
$config_errors = '';
if(strpos($recaptchaSiteKey,'site-key-') > 0 or
   strpos($recaptchaSecretKey,'secret-key-') > 0) {
	 $config_errors .= "<p>Note: variables \$recaptchaSiteKey and \$recaptchaSecretKey must have ";
	 $config_errors .= "valid Google reCaptcha keys set in this script to operate properly.</p>\n";
	 $config_errors .= "<p>Visit <a href=\"https://www.google.com/recaptcha/admin\" target=\"_blank\">";
	 $config_errors .= "https://www.google.com/recaptcha/admin</a> to acquire a Site and Secret reCaptcha key pair.</p>\n";
}
if(strpos($mailto,'somesite') > 0) {
	$config_errors .= "<p>\$mailto address not customized.</p>\n";
}
if(strpos($sitename,'Sitename') > 0) {
	$config_errors .= "<p>\$sitename is not customized.</p>\n";
}

if(strlen($config_errors) > 0) {
	print "<br/>\n";
	print "<div class=\"warningBox\" style=\"text-align: left; padding-left: 5px;\">\n";
	print "<h3>Configuration error(s)</h3>\n";
	print $config_errors;
	print "</div>\n";
}

if ($SITE['lang'] <> 'en' and file_exists("wxcontact-".$SITE['lang'].'.html')) { 
# handle included files for other language wxcontact-XX.html 
	 include_once("wxcontact-".$SITE['lang'].'.html');
 } elseif (file_exists("wxcontact-en.html") ) {
   include_once("wxcontact-en.html"); // default english text for welcome, thankyou
 } else {
	 list($main_top_text,$welcome_intro,$thank_you) = gen_boilerplate();
 }
print $main_top_text;

$error = 0;
$error_print = '';
$error_message = array();
$message_sent = 0;
if(isset($_POST['name'])) { $name=$_POST['name']; }
  elseif(!isset($name))  {$name = '';}
if(isset($_POST['email'])) { $email=$_POST['email']; }
 elseif (!isset($email)) {$email = '';}
if(isset($_POST['email2'])) { $email2=$_POST['email2']; }
 elseif (!isset($email2)) {$email2 = '';}
if(isset($_POST['subject'])) { $subject=$_POST['subject']; }
 elseif (!isset($subject)) {$subject = '';}
if(isset($_POST['text'])) { $text=$_POST['text']; }
 elseif(!isset($text))  {$text = '';}
// print "<p class=\"advisoryBox\" style=\"text-align: left\">POST \n".print_r($_POST,true) . "</p>\n";
//  if (isset($_GET['action']) && ($_GET['action'] == 'send')) {
if( isset($_POST['g-recaptcha-response']) ) {
		//get verify response data
		$verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' .
		    $recaptchaSecretKey.'&response='.$_POST['g-recaptcha-response']);
//		print "<p class=\"advisoryBox\" style=\"text-align: left\">verifyResponse=".print_r($verifyResponse,true)."</p>\n";
		$responseData = json_decode($verifyResponse);
//		print "<p class=\"advisoryBox\" style=\"text-align: left\">responseData =".print_r($responseData,true)."</p>\n";
		if($responseData->success) {


		$name        = name_case(db_prepare_input(strip_tags($_POST['name'])));
		$email       = strtolower(db_prepare_input($_POST['email']));

		$text        = db_prepare_input(strip_tags($_POST['text']));

		forbidifnewlines($name);  // fights a spammer tactic
		forbidifnewlines($email); // fights a spammer tactic
		forbidifnewlines($email2); // fights a spammer tactic
		forbidifnewlines($subject); // fights a spammer tactic

		# check posted input for email injection attempts
		$forbidden = 0;
		$forbidden = spamcheckpost(); // fights a spammer tactic

		if ($forbidden) {
			 echo "<H1>".langtransstr("Input Forbidden")." $forbidden</H1>";
			 exit;
		}


	 if (!preg_match("/[a-z]/", $text)) $text = name_case($text); # CAPS Decapitator

	 if (!validate_email($email)) {
			 $error = 1;
			 $error_message[0] = langtransstr('A proper email address is required.');
	 }
	 if (!validate_email($email2)) {
			 $error = 1;
			 $error_message[0] = langtransstr('A proper email address is required.');
	 }
	 if(strcmp($email,$email2) !== 0) {
		   $error = 1;
			 $error_message[2] = langtransstr('The email addresses are not the same.');
	 }
	 if(empty($name)) {
			 $error = 1;
			 $error_message[3] = langtransstr('Your name is required.');
	 }
	 if(empty($subject)) {
			 $error = 1;
			 $error_message[4] = langtransstr('A subject is required.');
	 }

	 if (!$error) {
			# make the email
			$subj = "$sitename contact: $subject\n";

	$msg =  "Sent from ".$_SERVER['SERVER_NAME']. " " . $_SERVER['PHP_SELF'] . " form

Name: $name
Email: $email

Message:\n\n" . wordwrap($text) . "

----------------------------------------------------
remove the following before replying to this message

";


	$userdomain = '';
	$userdomain = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	$user_info_string  = "Sent from (ip address): ".$_SERVER['REMOTE_ADDR']." ($userdomain)\n";
	$user_info_string .= "Coming from (referer): ".$_SERVER['HTTP_REFERER']."\n";
	$user_info_string .= "Using (user agent): ".$_SERVER['HTTP_USER_AGENT']."\n\n";
	$msg .= $user_info_string;
  $msg .= "Recaptcha response: ".$verifyResponse."\n\n";
	$name = str_replace(',','',$name); // remove comma from name
	$name = str_replace(';','',$name); // remove semicolon from name

		# send the email
	$subj = trim($subj);
 	mail($mailto,$subj,$msg,"From: $name <$email>");

	$message_sent =1;
	if ($logFile <> '') { // make a log also if needed
		$log = fopen($logFile,'a');
		if ($log) {
			 $todayis = date("l, F j, Y, g:i a T") ;

		 $t = "-------------------------------------------------------------------------------------\n\n" .
			"Date: $todayis \n" . $msg . "\n";
		 
		 fwrite($log,$t);
		 fclose($log);
		} else {
		 print "<!-- unable to open/write log -->\n";
		}
	} // end if $logFile
	} // end if !error
}  // end if $_POST['g-recaptcha-response'] abd got g-recaptcha-response
   else {
	 $error =1;
	 $error_message[] = langtransstr('Please complete reCaptcha before submit.');
	 }
}

if ($error) {
	foreach($error_message as $key => $value) {
	  $error_print .= "<p style=\"color: maroon\">$value</p>";
	}
	$error_print .= '<p style="color: maroon">'. langtransstr('Please make any necessary corrections and try again.'). '</p>';

}

if($message_sent) {

   # thank you mesage is printed here
    echo $thank_you;

} else {
   if (!$error) {
    # welcome intro is printed here
    echo $welcome_intro;

   }
?>

<?php echo $error_print ?>

<form action="#" method="post">

<table border="0" width="98%" cellspacing="1" cellpadding="2">
  <tr>
    <td>
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><?php langtrans('Full Name:'); ?>
        </td>

      </tr>
      <tr>
        <td class="small"><input type="text" name="name" value="<?php echo $name ?>" size="93" />
        <br />
        <?php 
				/* langtrans('Please enter your name and correct e-mail address here.'); ?><br/>
<?php langtrans('A few people mistype their e-mail addresses, making it impossible for us to respond.'); ?><br/>
<?php langtrans('Please double-check carefully.'); 
        */ ?>
        </td>
      </tr>
      <tr>
        <td><?php langtrans('E-Mail Address:'); ?></td>
      </tr>
      <tr>
        <td><input type="text" name="email" value="<?php echo $email ?>" size="93" /></td>
      </tr>
      <tr>
        <td><?php langtrans('Enter E-Mail Address again:'); ?></td>
      </tr>
      <tr>
        <td><input type="text" name="email2" value="<?php echo $email2 ?>" size="93" /></td>
      </tr>

      <tr>
        <td><?php langtrans('Subject:'); ?></td>
      </tr>
      <tr>
        <td><input type="text" name="subject" value="<?php echo $subject ?>" size="93" /></td>
      </tr>
      <tr>
        <td><?php langtrans('Message:'); ?></td>
      </tr>
      <tr>
       <td class="small"><textarea name="text" cols="70" rows="15"><?php echo $text ?></textarea><br />
      <br />
      <?php langtrans('Thanks for taking the time to submit your feedback.'); ?>


<br />
<br />
<div class="g-recaptcha" data-sitekey="<?php echo $recaptchaSiteKey; ?>"></div>
<p>
<input type="submit" value="<?php langtrans('Submit'); ?>" />
</p>
      </td>
    </tr>
  </table>
 </td>
 </tr>
 </table>
</form>
<p style="text-align:center"><small>Contact script by <a href="https://saratoga-weather.org/scripts-contact.php">Saratoga-Weather.org</a></small></p>
<?php
}
?>
<?php if(!$doStandalone) { ?>
</div><!-- end main-copy -->

<?php
  ############################################################################
  include("footer.php");
}
############################################################################
# End of Page
############################################################################
# Support functions
#
function not_null($value) {
	if (is_array($value)) {
		if (sizeof($value) > 0) {
			return true;
		} else {
			return false;
		}
	} else {
		if (($value != '') && (strtolower($value) != 'null') && (strlen(trim($value)) > 0)) {
			return true;
		} else {
			return false;
		}
	}
}
function db_input($string) {
	return addslashes($string);
}
function db_output($string) {
	return htmlspecialchars($string);
}
function db_prepare_input($string) {
	if (is_string($string)) {
		return trim(sanitize_string(stripslashes($string)));
	} elseif (is_array($string)) {
		reset($string);
		foreach ($string as $key => $value) {
			$string[$key] = db_prepare_input($value);
		}
		return $string;
	} else {
		return $string;
	}
}
// Parse the data used in the html tags to ensure the tags will not break
function parse_input_field_data($data, $parse) {
	return strtr(trim($data), $parse);
}
function output_string($string, $translate = false, $protected = false) {
	if ($protected == true) {
				return htmlspecialchars($string);
	} else {
		if ($translate == false) {
			return parse_input_field_data($string, array('"' => '&quot;'));
		} else {
			return parse_input_field_data($string, $translate);
		}
	}
}
function output_string_protected($string) {
	return output_string($string, false, true);
}
function sanitize_string($string) {
	$string = preg_replace('| +|', ' ', trim($string));
	return preg_replace("/[<>]/", '_', $string);
}
// Decode string encoded with htmlspecialchars()
function decode_specialchars($string){
	$string=str_replace('&gt;', '>', $string);
	$string=str_replace('&lt;', '<', $string);
	$string=str_replace('&#039;', "'", $string);
	$string=str_replace('&quot;', "\"", $string);
	$string=str_replace('&amp;', '&', $string);
	return $string;
}
//# A function knowing about name case (i.e. caps on McDonald etc)
# $name = name_case($name);
function name_case($name) {
 $break = 0;
 $newname = strtoupper($name[0]);
 for ($i=1; $i < strlen($name); $i++) {
	 $subed = substr($name, $i, 1);
	 if (((ord($subed) > 64) && (ord($subed) < 123)) ||
			 ((ord($subed) > 48) && (ord($subed) < 58))) {
			 $word_check = substr($name, $i - 2, 2);
			 if (!strcasecmp($word_check, 'Mc') || !strcasecmp($word_check, "O'")) {
					 $newname .= strtoupper($subed);
			 } elseif ($break){
					 $newname .= strtoupper($subed);
			 } else {
					 $newname .= strtolower($subed);
			 }
				 $break=0;
	 }else{
		 // not a letter - a boundary
		 $newname .= $subed;
		 $break=1;
	 }
 }
 return $newname;
}
function validate_email($email) {
   // Create the syntactical validation regular expression
   $regexp = "^([_a-z0-9-]+)(\.[_a-z0-9-]+)*@([a-z0-9-]+)(\.[a-z0-9-]+)*(\.[a-z]{2,4})$";
   // Presume that the email is invalid
   $valid = 0;
   //check for all the non-printable codes in the standard ASCII set,
   //including null bytes and newlines, and exit immediately if any are found.
   if (preg_match("/[\\000-\\037]/",$email)) {
    return 0;
   }
   // Validate the syntax
   if (preg_match('|'.$regexp.'|i', $email)) {
      list($username,$domaintld) = explode("@",$email);
      // Validate the domain
      if (getmxrr($domaintld,$mxrecords)) {
         $valid = 1;
      }
   } else {
      $valid = 0;
   }
   return $valid;
}

function forbidifnewlines($input) {
 if (preg_match("|\r|is", $input) ||
		 preg_match("|\n|is", $input) ||
		 preg_match("|\%0a|is", $input) ||
		 preg_match("|\%0d|is", $input)) {
		 echo "<H1>" . langtransstr('Input Forbidden NL')."</H1>";
		 exit;
 }
}

function spamcheckpost() {
 
 if(!isset($_SERVER['HTTP_USER_AGENT'])){
     return 1;
  }

  // Make sure the form was indeed POST'ed:
  //  (requires your html form to use: action="post")
 if(!$_SERVER['REQUEST_METHOD'] == "POST"){
    return 2;
 }
 
	# check posted input for email injection attempts
	$badStrings = array('content-type','mime-version','content-transfer-encoding','to:','bcc:','cc:');
	# Loop through each POST'ed value and test if it contains one of the $badStrings:
	foreach($_POST as $k => $v){
		foreach($badStrings as $v2){
				$v = strtolower($v);
			 if(strpos($v, $v2) !== false){
					return 4;
			 }
		}
	}
	// Made it past spammer test, free up some memory
	unset($k, $v, $v2, $badStrings, $fromArray, $wwwUsed);
	return 0;
}


function gen_boilerplate () {
# generate default text when no wxcontact-LL.html files are available
# local language customization for wxcontact.php 
# note that specific fields are translated in language-LL-local.txt
# and this file just contains the 'bulk' text items for the page
#

 # The $main_top_text is what gets printed when the form is first presented.

$main_top_text = <<<EOT

  <h1>Contact Us!</h1>
  <p>Please use the form below to contact us.  We do appreciate all feedback.<br />
  </p>
  <p>Thanks in advance!</p>

EOT;
// do not remove the above EOT line

 # The $welcome_intro is what gets printed when the form is first presented.
 # It is not printed when there is an input error and not printed after the form is completed
 $welcome_intro = <<<EOT

<p>
This is just a hobby site, we are not weather experts.<br/>
If you want to make a site like this, look at our links page or do a web search to find the answers.<br/>
If your question was answered and was helpful, it is always nice to know.
</p>

EOT;
// do not remove the above EOT line

 # The $thank_you is what gets printed after the form is sent.
 $thank_you = <<<EOT

 <h1>Message Sent</h1>

 <p align="left">
    Your message has been sent, thank you.
 </p>

EOT;
// do not remove the above EOT line
return array($main_top_text,$welcome_intro,$thank_you);
}
# end contact-inc.php
