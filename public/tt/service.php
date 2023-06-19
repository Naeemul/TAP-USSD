<?php
//error_reporting (1);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
ini_set('max_execution_time', 300); // 300 (seconds) = 5 Minute
error_reporting (0);
//error_reporting(E_ALL);
//ini_set('display_errors', '1');


// Include bootstrap file
require_once __DIR__ . '/../../includes/bootstrap.php';
              
          /*
           *Author : Anirban Das
           *Date: 14-03-2022 
           *Purpose: Teletalk USSD features 
           *
           */



################################# Main Execution #####################################
$data_post = trim(file_get_contents('php://input'));
$xml_data = simplexml_load_string($data_post);
$tid = (string) $xml_data->sequence_number;
# MSISDN of the user
$msisdn = (string) $xml_data->source_addr;
$t = date('Y-m-d H:i:s');
$input = (string) $xml_data->msg_content;

//echo $xaml_data;

# Session Status {begin,continue,end}
$data_coding="4";
$source_addr=(string) $xml_data->dest_addr;
//$input = (string) $xml_data->msg_content;
$source_addr=$source_addr.'#';
$flow = (string) $xml_data->service_type;

$filen = fopen("/../../../ussd551.log", "a"); // change directory
$TEXT = "$t::$msisdn:: USSD Request found - $tid -*551#.";
fwrite($filen, "$TEXT\n");
fclose($filen);


$carrier = 'tt';
$SessionId = $tid;

$mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE'], $_ENV['DB_PORT']);

try {
    initSession($carrier, $msisdn, $SessionId);
} catch (Exception $e) {
    // Do Nothing
}

$sql = "SELECT user_type FROM users where gsm = $msisdn";

 $result = $mysqli->query($sql); 
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_array()) {
            $service = $row['user_type'];
        }
        $result->free();
    } else {
        $service = getWHOis($msisdn);
        $userType = array('TBL-USER', 'TBL-AGENT', 'TBL-DIS','TBL-DSR', 'TBL-MAR', 'TBL-MAR-REST');

        if (in_array($service, $userType)) {
            $sql = "INSERT INTO users (user_type, gsm) VALUES ('$service', '$msisdn')";
            $mysqli->query($sql);
        }
    } 


try {
    setUserType($carrier, $SessionId, $service);
} catch (Exception $e) {
    // Do Nothing
}

if ($input == '*733#' || $input == '0') {
    //$service = init($msisdn);
    //$var = nodeinfo('1234','init', $service, $msisdn, '1');
    $var = umenu('1234', 'init', $service, $msisdn, '1', $SessionId, $carrier);
    $msg = explode('<', $var);

    $ussdmenu = $msg[0];
    $ussdmenu = str_replace('\n', "
 ", $ussdmenu);
    
    //$flow = '<a href=service.php default=”yes”></a>';
    $flow='';
    if ($msg[1] != '400') {
        $ussdmenu = $ussdmenu.$flow;
    }
    $ussdmenu = utf8_encode($ussdmenu);
    //echo $ussdmenu;
    $outputlength = (int) strlen($ussdmenu);
    header("Content-length: $outputlength");


    USSDActivity($ussdmenu, "CR");

   // echo "Service is temporarily down for maintenance up to 7:00 pm";
} else {
    //$var = nodeinfo('1234','node', $service, $msisdn, $input);
    $var = umenu('1234', 'node', $service, $msisdn, $input, $SessionId, $carrier);
    $msg = explode('<', $var);

    $ussdmenu = $msg[0];
    $ussdmenu = str_replace('\n', "
 ", $ussdmenu);
    
    //$flow = '<a href=service.php default=”yes”></a>';
    $flow='';
    if ($msg[1] != '400') {
        $ussdmenu = $ussdmenu.$flow;
    }
    $ussdmenu = utf8_encode($ussdmenu);
    //echo $ussdmenu;
    $outputlength = (int) strlen($ussdmenu);
    header("Content-length: $outputlength");

    USSDActivity($ussdmenu, "CR");

    // echo "Service is temporarily down for maintenance up to 7:00 pm";
}
// write ussd log
$sql = "INSERT INTO ussd_log (user_type, gsm, session_id, input, resp) VALUES ('$service', '$msisdn', '$SessionId', '$input', '$ussdmenu')";
$mysqli->query($sql);
$mysqli->close();

/** new changes for header character length */
//$ussd_msg="Your MSISDN is : $msisdn and User Input is : $input";
//$outputlength = (int) strlen($ussd_msg);
//header("Content-length: $outputlength");

//$ussd_msg = "Your msisdn : $msisdn carrier :$carrier sessionID : $Sessionid input: $input ";

//USSDActivity($ussd_msg, "CR");


##################################

function USSDActivity($msg="", $service_type ="")
{
global $tid;
global $data_coding;
global $msisdn;
global $source_addr;
global $input;

header("Content-Type:application/xml");
$timestamp=date('Y/m/d H:i:s');
//$service_type="EA";
//$service_type="CR";
$msg_len=strlen($msg);
$reply='<?xml version="1.0" encoding="utf-8" ?>
<cps-message>
<sequence_number>'.$tid.'</sequence_number>
<version>16</version>
<service_type>'.$service_type.'</service_type>
<timestamp>2022/01/21 10:07:04</timestamp>
<source_addr>'.$source_addr.'</source_addr>
<dest_addr>'.$msisdn.'</dest_addr>
<timestamp>'.$timestamp.'</timestamp>
<command_status>0</command_status>
<data_coding>'.$data_coding.'</data_coding>
<msg_len>'.$msg_len.'</msg_len>
<msg_content>'.$msg.'</msg_content>
</cps-message>';
die($reply);
header("X-Flow:end");
die($msg);
}


function returnmsisdn()
{
global $msisdn;
global $input;

//if (strlen($input)>0){
//	$ussd_msg = "Your input is $input";
//}else{
$ussd_msg="Your MSISDN is : $msisdn and User Input is : $input";
//}
//$ussd_msg = $xml_data;
endTran($ussd_msg);
return true;
}
?>
