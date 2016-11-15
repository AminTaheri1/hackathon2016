<?php

/*
 * ##############################################################################
 *
 * WHMCS eNom New TLDs Addon
 *
 * Created: September 17th, 2012
 * Last Modified: October 30th, 2012
 * Version: 1.0
 * Author:  Amin Taheri
 *
 * 
 * This code should be a fully functional example of how a customer could utilize 
 * the New TLD Portal from eNom on their WHMCS site.  The flow is fairly simple:
 * 
 * - Enable and configure the access via WHMCS
 * - Configure the plugin based on the output from enomnewtlds_output() 
 *      This calls our API to see if the user has a portal already, and if not 
 *      creates a portal but if they do have one then it reactivates it (status=1) 
 *      and saves the config data at eNom, and also keeps a local copy of some of 
 *      the params.  
 * 
 *  - Now all that needs to happen is a client visits the watchlist client area 
 *      page and they are either automatically logged in using their WHMCS stuff 
 *      (by using the Token we generate for them) or if they dont have an account
 *      already then we create one immediately and auto log them in.
 * 
 *  - When a reseller turns off the TLD Portal it will call enom again to disable
 *      the portal (set status=0) and we delete all the data in our tables inside
 *      WHMCS.
 * 
 * CHANGES SINCE LAST TIME
 * - Added in Support Email to the configuration form, and to the enom API calls, AND DB
 * - Added the plugin Version and Embedded or not as HTML Comment in TPL
 * - Added slight form validation for user input in enomnewtlds_output()
 * - Modified some text (description in enomnewtlds_config, language, etc)
 * - Modified the configuration form HTML
 * - Added slight form validation for user input in enomnewtlds_output()
 * - Added Current Version into easy to see variable to update the actual version and pass to API calls
 * - Added a �Is Bundled� variable that will be in the TPL output and passed in to enom � should be TRUE for you, FALSE for us
 * - Updated images and CSS (logo, etc)
 * - Removed HeaderText from language file and TPL, modified some TPL HTML
 * - Renamed/Rebranded so Portal is not prevelant in the naming of functions or displayed
 *
 * ##############################################################################
 */

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");


##############################################################################
/* GLOBAL VARS */
##############################################################################

#Current Version - used in the Config array, also passed into enom calls
$enomnewtldimport_CurrentVersion = "1.0";

//This will be true for when its bundled with WHMCS, False when enom distributes
$enomnewtldimport_isbundled = false; 

//Global Error message
$enomnewtldimport_errormessage = '';

//Any random string here is fine, it doesnt really matter.  This is used to generate a password to send to enom that will never change
//Since its based off of their username and userID - both of which can't change - we cant use the WHMCS password because the user can change it
$enomnewtldimport_mysalt = 'sAR2Th4Ste363tUkUw';

#this is really only useful to us in testing, this will always be 0 for production when we launch - although it is still DB controlled so it can be overridden
$enomnewtldimport_DefaultEnvironment = '0';

#if I ever change the names of tables, I just do it once and its all done :)
$enomnewtldimport_ModuleName = 'enomnewtldimport';
$enomnewtldimport_DBName = 'mod_' . $enomnewtldimport_ModuleName;
$enomnewtldimport_CronDBName = $enomnewtldimport_DBName . '_cron';

##############################################################################



##############################################################################
/* WHMCS CORE FUNCTIONS FOR THE PLUGIN */
##############################################################################

function enomnewtldimport_config() {
    global $enomnewtldimport_CurrentVersion;
    $configarray = array(
    "name" => "eNom New TLDs",
    "description" => "Import new TLDs and automatically set pricing",
    "version" => $enomnewtldimport_CurrentVersion,
    "author" => "eNom",
    "language" => "english",
    "fields" => array());
    return $configarray;
}
function enomnewtldimport_activate($vars) {

    global $enomnewtldimport_DefaultEnvironment;
    global $enomnewtldimport_DBName;
    $LANG = $vars['_lang'];
    
    # Create Custom DB Table
    $sql = enomnewtldimport_DB_GetCreateTable();
    $retval = mysql_query($sql);
    if(!$retval)
    {
        return array('status'=>'error','description'=> $LANG['activate_failed1'] . $enomnewtldimport_DBName . ' : ' . mysql_error());
    }    
    else
    {
        $companyname = '';
        $domain = '';
        $date = enomnewtldimport_Helper_GetDateTime();

        insert_query($enomnewtldimport_DBName,
                array("enabled"=>"1",
                    "configured"=>"0", 
                    "environment"=>$enomnewtldimport_DefaultEnvironment, 
                    "enableddate"=>$date));
        
        enomnewtldimport_DB_GetCreateHookTable();
        return array('status'=>'success','description'=>$LANG['activate_success1']);
    }
}
function enomnewtldimport_deactivate($vars) {

    global $enomnewtldimport_errormessage;
    global $enomnewtldimport_DBName;
    global $enomnewtldimport_CronDBName;
    
    $fields = array();
    $fields['statusid'] = '0';
    $LANG = $vars['_lang'];
    
    $data = enomnewtldimport_DB_GetSettingsLocal();
    $wlenabled = $data['enabled'];
    $wlconfigured = $data['configured'];
    $portalid = $data['portalid'];
        
	# Remove Custom DB Table
	$sql = 'DROP TABLE `' . $enomnewtldimport_DBName .'`;';
	$retval = mysql_query($sql);
	    
	if(enomnewtldimport_DB_HookTableExists())
	{
		$sql = 'DROP TABLE `' . $enomnewtldimport_CronDBName .'`;';
		$retval = mysql_query($sql);
	}
	
	
	#dont care if it failed, just return success
    $retval = 1;

	//We dont really care if this works or not - they could also enable/disable before the cron runs to enable/create the other table too.
	return array('status'=>'success','description'=>$LANG['deactivate_success1']);
}

function enomnewtldimport_upgrade($vars) {
    $version = $vars['version'];
    global $enomnewtldimport_CurrentVersion;
    
    # Run SQL Updates for V1.0 -> V1.1
    if ($version < 1.1) {
        //No DB Updates for this release, at least not yet!
    }
    
    # Run SQL Updates for V1.1 -> V1.2
    if ($version < 1.2) {
        //No DB Updates for this release, at least not yet!
    }
}
function enomnewtldimport_clientarea($vars) {
	#no client area items, just admin
}
function enomnewtldimport_sidebar($vars) 
{
    //Not sure what this is for, I didnt notice this being displayed anywhere.
    $modulelink = $vars['modulelink'];
    $LANG = $vars['_lang'];
    $sidebar = '<span class="header"><img src="images/icons/addonmodules.png" class="absmiddle" width="16" height="16" />' . $LANG['intro'] . '</span>
    <ul class="menu">
        <li><a href="#">' . $LANG['intro'] . '</a></li>
        <li><a href="#">Version: '.$vars['version'].'</a></li>
    </ul>';
    return $sidebar;
}

##############################################################################



##############################################################################
/* WHMCS LOCAL DB FUNCTIONS */
##############################################################################

function enomnewtldimport_DB_GetCreateTable(){
    global $enomnewtldimport_DefaultEnvironment;
    global $enomnewtldimport_DBName;
    
    $sql = "CREATE TABLE IF NOT EXISTS `" . $enomnewtldimport_DBName ."` (
            `id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `enabled` INT( 1 ) NOT NULL DEFAULT '0' ,
            `configured` INT( 1 ) NOT NULL DEFAULT '0' ,
            `environment` INT( 1 ) NOT NULL DEFAULT '" . $enomnewtldimport_DefaultEnvironment . "' ,
            `enomlogin` VARCHAR( 272 ) NULL ,
            `enompassword` VARCHAR( 272 ) NULL ,
            `enableddate` VARCHAR( 272 ) NULL ,
            `configureddate` VARCHAR( 272 ) NULL
            ENGINE = MYISAM";    
    
    return($sql);
}
function enomnewtldimport_DB_GetCreateHookTable(){
    
    global $enomnewtldimport_CronDBName;
    
    if (!enomnewtldimport_DB_HookTableExists())
    {
        full_query("CREATE TABLE IF NOT EXISTS `" . $enomnewtldimport_CronDBName . "` (
            `id` INT( 100 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `tldid` INT ( 10 ) NOT NULL,
            `tld` VARCHAR( 272 ) NOT NULL,
            `regprice` VARCHAR( 272 ) NOT NULL ,
            `renewprice` VARCHAR( 272 ) NOT NULL,
            `transferprice` VARCHAR( 272 ) NOT NULL,
            `regperiod` INT( 2 ) NOT NULL DEFAULT  '1' )
             ENGINE = MYISAM;");
    }
    
    if (!mysql_num_rows(full_query("select * from `tblconfiguration` where setting='enomnewtldimport_cronbatchsize';")))
        full_query("insert into `tblconfiguration` (Setting, value) VALUES('enomnewtldimport_cronbatchsize', '50');");
}
function enomnewtldimport_DB_GetSettingsLocal()
{
    global $enomnewtldimport_DefaultEnvironment;
    global $enomnewtldimport_DBName;
    
    $result = select_query($enomnewtldimport_DBName,"enabled,configured,environment,enomlogin,enompassword",array());
    $data = mysql_fetch_array($result);
    
    //Some basic checks to ensure its not null and to give it an empty string
    //Just in case php is as finickey as .Net is when it comes to using a null
    if(enomnewtldimport_Helper_IsNullOrEmptyString($data['portalid']))
    {
        $data['portalid'] = '0';
    }
    
    if(enomnewtldimport_Helper_IsNullOrEmptyString($data['enompassword']))
    {
        $data['enompassword'] = '';
    }
    else
        $data['enompassword'] = decrypt($data['enompassword']);
    
    if(enomnewtldimport_Helper_IsNullOrEmptyString($data['enomlogin']))
    {
        $data['enomlogin'] = '';
    }
    else
        $data['enomlogin'] = decrypt($data['enomlogin']);
    
    
    if(enomnewtldimport_Helper_IsNullOrEmptyString($data['environment']))
    {
        $data['environment'] = $enomnewtldimport_DefaultEnvironment;
    }
    
    return $data;
}
function enomnewtldimport_DB_GetConfigured()
{
    if (!enomnewtldimport_DB_TableExists())
        return false;
    
    $data = enomnewtldimport_DB_GetSettingsLocal();
    if( !$data)
        return false;

    return $data['configured'] == 1;
}
function enomnewtldimport_DB_TableExists()
{
    if (!mysql_num_rows(full_query("SHOW TABLES LIKE '" . $enomnewtldimport_DBName . "'")))
        return false;

    return true;
}
function enomnewtldimport_DB_HookTableExists()
{
    global $enomnewtldimport_CronDBName;
    if (!mysql_num_rows(full_query("SHOW TABLES LIKE '" . $enomnewtldimport_CronDBName . "'")))
        return false;

    return true;
}

function enomnewtldimport_DB_BootstrapUidPw($enomuid, $enompw)
{
    global $enomnewtldimport_DBName;
    $datetime = enomnewtldimport_Helper_GetDateTime();
    update_query($enomnewtldimport_DBName, 
                    array("configureddate"=>$datetime,
                            "enomlogin"=>$enomuid, 
                            "enompassword"=>$enompw),  
                    array("id"=>"1"));
}

##############################################################################



##############################################################################
/* WHMCS HELPER FUNCTIONS */
##############################################################################

function enomnewtldimport_Helper_GetDateTime(){
    //Yes, even though its micro time, I dont actually use it :)
    $t = microtime(true);
    $micro = sprintf("%06d",($t - floor($t)) * 1000000);
    $d = new DateTime( date('Y-m-d H:i:s.'.$micro,$t) );
    return $d->format("Y-m-d H:i:s");
}
function enomnewtldimport_Helper_IsNullOrEmptyString($str){ 
    return (!isset($str) || trim($str)==='' || strlen($str) == 0); 
}
function enomnewtldimport_Helper_FormatDomain($domainname)
{
    $website = preg_replace( '/^(htt|ht|tt)p\:?\/\//i', '', $domainname );  
    if( enomnewtldimport_Helper_endsWith($website, '/'))
    {
        $length = strlen($needle);
        $website = substr($haystack, 0, ( $length > 0) ? $length-1 : $length);
    }
    return $website;
}
function enomnewtldimport_Helper_startsWith($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}
function enomnewtldimport_Helper_endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}
function enomnewtldimport_AddError($error)
{
    global $enomnewtldimport_errormessage;

    if(enomnewtldimport_Helper_IsNullOrEmptyString($enomnewtldimport_errormessage))
    {
        $enomnewtldimport_errormessage = $error;
    }
    else
    {
        $enomnewtldimport_errormessage .=  '<br />' . $error;
    }
}
function enomnewtldimport_Helper_FormatAPICallForEmail($fields,$environment)
{
    $url = 'https://'.enomnewtldimport_Helper_GetAPIHost($environment).'/interface.asp?';
    foreach ($fields AS $x=>$y) 
        $url .= $x."=".$y."&";

    return $url;
}
function enomnewtldimport_Helper_GetAPIHost($environment)
{
    switch($environment)
    {
        case '1':
            $url ='resellertest.enom.com';
            break;
        case '2':
            $url ='api.staging.local';
            break;
        case '3':
            $url ='api.build.local';
            break;
        case '4':
            $url ='reseller-sb.enom.com';
            break;
        
        default:
            $url ='reseller.enom.com';
            break;
    }

    return $url;
}
function enomnewtldimport_Helper_GetDocumentationHost($environment)
{
    switch($environment)
    {
        case '1':
            $url ='resellertest.enom.com';
            break;
        case '2':
            $url ='staging.enom.com';
            break;
        case '3':
            $url ='enom.build.local';
            break;
        case '4':
            $url ='preprod.enom.com';
            break;
        
        default:
            $url ='www.enom.com';
            break;
    }

    return $url;
}
function enomnewtldimport_Helper_Getenvironment($environment)
{
    global $enomnewtldimport_DefaultEnvironment;
    if(enomnewtldimport_Helper_IsNullOrEmptyString($environment))
    {
        $data = enomnewtldimport_DB_GetSettingsLocal();
        $environment = $data['environment'];
    }
    
    return $environment;
}
##############################################################################



##############################################################################
/* ENOM API CALLS */
##############################################################################

function enomnewtldimport_API_GetRetailPricing($vars,&$xmldata,$fields) 
{
    $LANG = $vars['_lang'];
    $postfields = array();
    $postfields['command'] = "PE_GetRetailPricing";

    if (is_array($fields)) {
        foreach ($fields AS $x=>$y) 
            $postfields[$x] = $y;
    }
    
    $xmldata = enomnewtldimport_API_CallEnom($vars,$postfields);
    $success = ($xmldata->{'ErrCount'} == 0);
	if ($success) 
    {
	    $result = "success";
	    return true;
	}
    else 
    {
        $result = enomnewtldimport_API_HandleErrors($xmldata);
        if (!$result) 
            $result = $LANG['api_unknownerror'];;
        
        enomnewtldimport_AddError($result);
    }
	return false;
}

function enomnewtldimport_API_HandleErrors($xmldata)
{
    $result = '';
    $errcnt = $xmldata->{'ErrCount'};
    for($i=1; $i <= $errcnt ; $i++)
    {
        $result = $xmldata->errors->{'Err' .$i};
        if($i < $errcnt && $errcnt > 1)
            $result .= '<br />';
    }
    
    return $result;
}

function enomnewtldimport_API_UpdatePortalAccount($vars,$portalid,$fields)
{    
    $LANG = $vars['_lang'];
    $postfields = array();
    $postfields['command'] = "PORTAL_UPDATEDETAILS";
    $postfields['PortalAccountID'] = $portalid;

    if (is_array($fields)) {
        foreach ($fields AS $x=>$y) 
            $postfields[$x] = $y;
    }
    
    $xmldata = enomnewtldimport_API_CallEnom($vars,$postfields);
    $success = ($xmldata->{'ErrCount'} == 0);
	if ($success) 
    {
	    return true;
	} 
    else 
    {
        $result = enomnewtldimport_API_HandleErrors($xmldata);
        if (!$result) 
            $result = $LANG['api_unknownerror'];;
        
        enomnewtldimport_AddError($result);
    }
	return false;
}

function enomnewtldimport_API_CallEnom($vars,$postfields) 
{
    global $enomnewtldimport_ModuleName;
    global $enomnewtldimport_CurrentVersion;
    
    $LANG = $vars['_lang'];
    $data = enomnewtldimport_DB_GetSettingsLocal();
    $environment = enomnewtldimport_Helper_Getenvironment($data['environment']);
    $portalid = $data['portalid'];

    //See if we set the UID already, if so then dont add it again
    if (!in_array('uid', $postfields)) 
    {
        $enomuid = $data['enomlogin'];
        $postfields['uid'] = $enomuid;
    }

    //See if we set the PW already, if so then dont add it again
    if( !in_array('pw', $postfields))
    {
        $enompw = $data['enompassword'];
        $postfields['pw'] = $enompw;
    }
    
    if (!in_array('portalid', $postfields)) 
    {
        if( !enomnewtldimport_Helper_IsNullOrEmptyString($portalid) && (int)$portalid > 0)
            $postfields['portalid'] = $portalid;
    }
    
    $postfields['ResponseType'] = "XML";
    $postfields['Source'] = 'WHMCS';
    $postfields['sourceid'] = '37';
    $postfields['bundled'] = $enomnewtldimport_isbundled ? 1 : 0;
    $postfields['pluginversion'] = $enomnewtldimport_CurrentVersion;
    
    $url = 'https://'.enomnewtldimport_Helper_GetAPIHost($environment).'/interface.asp';
    $data = curlCall($url,$postfields);
    
    //$xmldata = XMLtoArray($data);
    //$url = enomnewtldimport_Helper_FormatAPICallForEmail($postfields, $environment);
    
    $xmldata = simplexml_load_string($data);
    logModuleCall($enomnewtldimport_ModuleName,$postfields['command'],$postfields,$data,$xmldata);
    return $xmldata;
}

##############################################################################



##############################################################################
/* ADMIN CONFIGURATION FORM  */
##############################################################################

function enomnewtldimport_output($vars) {

    //use the global guy but reset it now since we dont care much about anything it could have had previously
    global $enomnewtldimport_errormessage;
    $enomnewtldimport_errormessage = '';
    
    global $enomnewtldimport_isbundled;
    global $enomnewtldimport_CurrentVersion;
    
    $success_message = '';
    
    $modulelink = $vars['modulelink'];
    $LANG = $vars['_lang'];
    $data = enomnewtldimport_DB_GetSettingsLocal();
    $companyname = $data['companyname'];
    $companyurl = $data['companyurl'];
    $enomuid = $data['enomlogin'];
    $enompw = $data['enompassword'];
    $portalid = $data['portalid'];
    $supportemail = $data['supportemail'];
    
    $environment = enomnewtldimport_Helper_Getenvironment($data['environment']);

    $configured = enomnewtldimport_DB_GetConfigured();
    $form_iframe_tab = $configured ? 2 : 1;
    $form_button_text = $configured ? $LANG['form_update'] : $LANG['form_activate'];
    $form_terms_text = $configured ? $LANG['form_terms2'] : $LANG['form_terms1'];
    $documentation_link = $LANG['documentation'];
    $url = enomnewtldimport_Helper_GetDocumentationHost($environment);
    
    if( $environment != '0')
    {
        $documentation_link = str_replace('www.enom.com', $url, $documentation_link);
        $form_terms_text = str_replace('www.enom.com', $url, $form_terms_text);
    }
    
    $create = false;
    $update = false;
    
    if (isset($_POST['enomuid'])) 
    {
        $enomuid = $_POST['enomuid'];
        $enompw = $_POST['enompw'];

        if( $enompw === '************')
        {
            $enompw = $data['enompassword'];
        }
        
        $success = true;
        
        # Form was submitted, do some validation
        if( enomnewtldimport_Helper_IsNullOrEmptyString($enomuid))
        {
            enomnewtldimport_AddError($LANG['enomuidrequired']);
            $success = false;
        }
        
        if( enomnewtldimport_Helper_IsNullOrEmptyString($enompw))
        {
            enomnewtldimport_AddError($LANG['enompwdrequired']);
            $success = false;
        }
                
        if($success)
        {
            enomnewtldimport_DB_BootstrapUidPw(encrypt($enomuid), encrypt($enompw));
            
            # Call enom API
            $fields = array();
            
            //If we already have this in the database then we dont need to try and get it again
			$nofields = array();
			//See if they have a portal account already - like if they activated and then deactivated the addon
			//as in that case we would have dropped the table - this would help to repopulate those fields
			$success = enomnewtldimport_API_GetRetailPricing($vars,$portalid,$nofields);
                
            if($success)
            {
            }
            else
            {
                enomnewtldimport_AddError($LANG['api_failedtoget']);
                $success = false;
            }
            
            if( $success && ($update || $create))
            {
                $mydata = array();
                $mydata['enomLogin'] = encrypt($enomuid);
                $mydata['enomPassword'] = encrypt($enompw);
                
                $result = enomnewtldimport_DB_BootstrapUidPw($mydata['enomLogin'], $mydata['enomPassword']);
                if( $result == 1)
                {
                    $success_message = $LANG['api_setupsuccess'];
                }
                else
                {
                    $success_message = $LANG['api_setupsuccess2'];
                }
            }
            else
            {
                //If the GET above failed, then we dont need to add anything else because we already set an error for that
                if( $create || $update)
                    enomnewtldimport_AddError( $create ? $LANG['api_failedtocreate'] : $LANG['api_failedtoupdate']);
            }
        }
    }
    
    //Set the local to the global so I can be lazy and not update code for now :)
    $errormessage = $enomnewtldimport_errormessage;

?>

<script type="text/javascript" language="JavaScript">
    $("#floatbar").click(function (e) {
        e.preventDefault();
        $(this).find(".popup").fadeIn("slow");
    });

    function InvalidValue(item) {
        var control = document.getElementById(item);
        if (control != null)
        { control.style.backgroundColor = "#FFE4E1"; }
    }

    function RevertForm(item) {
        var control = document.getElementById(item);
        if (control != null)
        { control.style.backgroundColor = ""; }
    }
    function ReturnFalse(msg) {
        alert(msg);
        return false;
    }
    function ValidateEmail(strValue) {
        if (window.echeck(strValue)) {
            var objRegExp = /(^[a-zA-Z0-9\-_\.]([a-zA-Z0-9\-_\.]*)@([a-z_\.]*)([.][a-z]{3})$)|(^[a-z]([a-z_\.]*)@([a-z_\.]*)(\.[a-z]{3})(\.[a-z]{2})*$)/i;
            return objRegExp.test(strValue);
        }
        return false;
    }

    function ValidateForm() {
        var email = document.getElementById('supportemail');
        var enomuid = document.getElementById('enomuid');
        var enompw = document.getElementById('enompw');
        var companyurl = document.getElementById('companyurl');
        var companyname = document.getElementById('companyname');
        var msg = '';
        
         if (enomuid.value == "") {
            InvalidValue('enomuid');
            msg += "eNom LoginID is required\n";
        } else { RevertForm('enomuid'); }

        if (enompw.value == "") {
            InvalidValue('enompw');
            msg += "eNom Password is required\n";
        } else { RevertForm('enompw'); }

       if (email.value == "") {
            InvalidValue('supportemail');
            msg += "Support Email Address is required\n";
        } else { RevertForm('supportemail'); }

        if (companyname.value == "") {
            InvalidValue('companyname');
            msg += "Company Name is required\n";
        } else { RevertForm('companyname'); }

        if (companyurl.value == "") {
            InvalidValue('companyurl');
            msg += "Company Url is required\n";
        } else { RevertForm('companyurl'); }

        if(msg != '')
            return ReturnFalse(msg);
         
        return true;
    }
    

</script>

<style type="text/css">

	.tld_wrp {margin-top:10px;font:16px/24px Arial, Verdana, Helvetica;padding:10px;color:#3C3C3C;background-color:#FFF;-webkit-border-radius:5px;border-radius:5px}
	.tld_wrp DIV,
	.tld_wrp SPAN,
	.tld_wrp A,
	.tld_wrp IMG,
	.tld_wrp STRONG,
	.tld_wrp FORM,
	.tld_wrp TABLE, 
	.tld_wrp TR, 
	.tld_wrp TH, 
	.tld_wrp TD {font-family:inherit;font-size:inherit;line-height:inherit;margin:0;padding:0;border:0;vertical-align:baseline;background-repeat:no-repeat;-webkit-appearance:none;-moz-appearance:none;appearance:none;-webkit-text-size-adjust:none;-ms-text-size-adjust:none}
	.tld_wrp STRONG {font-weight:bold}
	.tld_wrp A {text-decoration:none;cursor:pointer;color:#024DD6}
	.tld_wrp A:Hover {text-decoration:underline}
	.tld_wrp TABLE {border-collapse:collapse;border-spacing:0}
	.tld_wrp TH,
	.tld_wrp TD {font-weight:normal;vertical-align:top;text-align:left}
	.tld_wrp IMG {font-size:0;vertical-align:middle;max-width:100%;height:auto;-ms-interpolation-mode:bicubic}
	.tld_wrp INPUT[type=text], 
	.tld_wrp INPUT[type=password] {-webkit-appearance:none;-moz-appearance:none;appearance:none;-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;margin-bottom:3px;color:#000;display:inline;padding:0;font-weight:normal;vertical-align:baseline;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;height:20px;border-style:solid;border-color:#000 #CCC #CCC #000;-webkit-border-radius:2px;border-radius:2px;background-color:#FFF;background-size:100% 100%;margin-bottom:3px;border-width:1px;background-image:-webkit-gradient(linear, left top, left bottom, from(#EEE), to(#FFF));background-image:-webkit-linear-gradient(#EEE 0%, #FFF 100%);background-image:-moz-linear-gradient(#EEE 0%, #FFF 100%);background-image:-ms-linear-gradient(#EEE 0%, #FFF 100%);background-image:-o-linear-gradient(#EEE 0%, #FFF 100%);background-image:linear-gradient(#EEE 0%, #FFF 100%)}

	.tld_wrp .sError1, 
	.tld_wrp .sSuccess1 {text-align:left;padding:8px 10px 8px 42px;line-height:18px;font-size:14px;margin:1px 0 15px 0;position:relative;z-index:1;border:1px solid #000000;-moz-border-radius:5px;-webkit-border-radius:5px;border-radius:5px}
	.tld_wrp .sError1:Before, 
	.tld_wrp .sSuccess1:Before {content:"";position:absolute;top:4px;left:10px;z-index:2;height:24px;width:24px;background:transparent url('../modules/addons/enomnewtldimport/images/ico-info24x.png') no-repeat 0 0}
	.tld_wrp .sError1 {border-color:#CC9999;color:#C00;background:#FFEAEA}
	.tld_wrp .sSuccess1 {border-color:#A7B983;color:#333;background:#E8FF74}
				
	.tld_wrp .clearfix:after {content: "."; display: block; height: 0; clear: both; visibility: hidden;}
	.tld_wrp .clearfix {display: inline-block;}
    
</style>


<form method="post" action="<?php echo $modulelink ?>">

	<div class="tld_wrp" style="padding:10px;width:852px;">

		<?php if(!enomnewtldimport_Helper_IsNullOrEmptyString($errormessage)) { ?>
			<div class="sError1">
				<strong><?php echo $errormessage ?></strong>
			</div>
		<?php } ?>
		
		<?php if(!enomnewtldimport_Helper_IsNullOrEmptyString($success_message)){ ?>
			<div class="sSuccess1">
				<strong><?php echo $success_message ?></strong>
			</div>
		<?php } ?>

		<div class="clearfix" style="display:block;clear:both;border:1px solid #CCC;width:850px;font-weight:bold;font-size:14px;background-color:#EEE">
	 
			<div style="float:left;width:500px;min-height:485px;background-color:#FFF;">
				<div style="padding:20px">
				
					<table width="100%" cellspacing="0" cellpadding="0">
						<tr>
							<td width="50%" style="font-size:14px">
								<strong><?php echo $LANG['form_enomloginid'] ?></strong> <span style="color:red">*</span>
							</td>
							<td width="50%"style="text-align:right">
								<a href="https://www.whmcs.com/members/freeenomaccount.php" target="_blank""><?php echo $LANG['form_getenomaccount'] ?></a>
							</td>
						</tr>
						<tr>
							<td colspan="2" width="100%" style="font-size:14px;padding-bottom:10px">
								<input type="text" style="width:99%" name="enomuid" id="enomuid" value="<?php echo $enomuid ?>" onfocus="RevertForm(this.id);" />
							</td>
						</tr>
						<tr>
							<td colspan="2" width="100%" style="font-size:14px;padding-bottom:10px">
								<strong><?php echo $LANG['form_enompassword'] ?></strong> <span style="color:red">*</span><br />
								<input type="password" style="width:99%" name="enompw" id="enompw" value="<?php if(!enomnewtldimport_Helper_IsNullOrEmptyString($enompw)) { echo "************"; } ?>" onfocus="RevertForm(this.id);" />
							</td>
						</tr>
						<tr>
							<td colspan="2" width="100%" style="font-size:14px;padding-bottom:10px">
								<strong><?php echo $LANG['form_companyname'] ?></strong> <span style="color:red">*</span><br />
								<input type="text" name="companyname" style="width:99%" id="companyname" value="<?php echo $companyname ?>" onfocus="RevertForm(this.id);" />
							</td>
						</tr>
						<tr>
							<td colspan="2" width="100%" style="font-size:14px;padding-bottom:10px">
								<strong><?php echo $LANG['form_supportemail'] ?></strong> <span style="color:red">*</span><br />
								<input type="text" name="supportemail" style="width:99%" id="supportemail" value="<?php echo $supportemail ?>" onfocus="RevertForm(this.id);" />
								<div style="margin-top:0;font-size:12px;line-height:16px;color:#666"><?php echo $LANG['form_support_email_desc'] ?></div>
							</td>
						</tr>
						<tr>
							<td colspan="2" width="100%" style="font-size:14px;border-bottom:dotted 1px #CCC;padding-bottom:10px">
								<strong><?php echo $LANG['form_companyurl'] ?></strong> <span style="color:red">*</span><br />
								<input type="text" name="companyurl" style="width:99%" id="companyurl" value="<?php echo $companyurl ?>" onfocus="RevertForm(this.id);" />
								<div style="margin-top:0;font-size:12px;line-height:16px;color:#666;padding-bottom:10px">
									<?php echo $LANG['form_companyurl_text'] ?> <a href="javascript:void(0)" onclick="ResetDefault();"><?php echo $LANG['form_resetdefault'] ?></a>
								</div>
								<div><?php echo $form_terms_text ?></div>
							</td>
						</tr>
						<tr>
							<td width="50%" valign="bottom" style="padding-top:15px">
								<input type="submit" value="<?php echo $form_button_text ?> &raquo;" style="cursor:pointer;border-style:outset;padding:7px;font-size:1.55em;*font-size:1.3em;font-family:Arial, Helvetica, sans-serif;font-weight:normal;-moz-border-radius:5px;-webkit-border-radius:5px;border-radius:5px;border-width:1px" onclick="return ValidateForm();" />
							</td>
							<td width="50%" valign="bottom" style="text-align:right;padding-top:15px">
								<img src="../modules/addons/enomnewtldimport/images/enom.gif" border="0" />
							</td>
						</tr>
						<!--<tr>
							<td colspan="2" width="100%"><p><?php echo $documentation_link ?></p></td>
						</tr>-->
					</table>
				
				</div>
			</div>
			<div style="float:right;width:350px;min-height:485px">
				<div style="padding:20px">
					<div style="border:1px solid #CCC;background:#FFF;">
						<iframe frameborder="0" height="440px" width="308px" marginheight="0" marginwidth="0" scrolling="yes" src="https://<?php echo $url?>/whmcs/tld-portal/addon-iframe.aspx?p=<?php echo $form_iframe_tab ?>&version=<?php echo $enomnewtldimport_CurrentVersion?>&bundled=<?php echo $enomnewtldimport_isbundled ? "1" : "0" ?>"></iframe>
					</div>
				</div>
			</div>

	</div>

</div>

</form>





<?php
}

##############################################################################

?>

