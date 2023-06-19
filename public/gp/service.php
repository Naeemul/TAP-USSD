<?php

//error_reporting(1);
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 */
//echo "opu";

error_reporting(1);

header('Content-Type: text/html; charset=UTF-8');

// Include bootstrap file
require_once __DIR__ . '/../../includes/bootstrap.php';

$userid = $_GET['userid'];
$password = $_GET['password'];
$input = $_GET['response'];
$msisdn = $_GET['msisdn'];  /* This is the user number */
$SessionId = $_GET['SessionID'];
$ShortCode = $_GET['ShortCode'];

$carrier = 'gp';

$SessionId=123;

$mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE'], $_ENV['DB_PORT']);

try {
    initSession($carrier, $msisdn, $SessionId);
} catch (Exception $e) {
    // Do Nothing
}

$sql = "SELECT user_type FROM users where gsm = $msisdn";  /* msisdn is the user number */

if ($userid == '' || $userid != 'trust' || $password == '' || $password != 'trust123') {
    header('Freeflow: FB');
    echo 'Request Error: invalid userid or password';
} 
else {
    if ($result = $mysqli->query($sql)) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_array()) {
                $service = $row['user_type'];    ///which type of user like TBL-agent, TBL-user, TBL-DIS
            }
            $result->free();
        } 
        else {
            $service = getWHOis($msisdn);
            $userType = array('TBL-USER', 'TBL-AGENT', 'TBL-DIS','TBL-DSR', 'TBL-MAR', 'TBL-MAR-REST');

            if (in_array($service, $userType)) {
                $sql = "INSERT INTO users (user_type, gsm) VALUES ('$service', '$msisdn')";
                $mysqli->query($sql);
            }
        }
        
    }
    //get the service where we find which type of user

    try {
	    //echo $carrier."-".$SessionId."-".$service; 
        // $service='TBL-USER';   
        // setUserType($carrier, $SessionId, $service);
    } 
    catch (Exception $e) {
        // Do Nothing
    }
    // $mysqli->close();
    
    /* This is initial point of showing ussd menu */
    if ($input == '' || $input == '0') { 
        //$service = init($msisdn);
        //$var = nodeinfo('1234','init', $service, $msisdn, '1');
        $var = umenu('1234', 'init', $service, $msisdn, '1', $SessionId, $carrier);
        $msg = explode('<', $var);

        $ussdmenu = $msg[0];
        $ussdmenu = str_replace('\n', "<br>", $ussdmenu);
        
        $flow = '<a href=service.php default=”yes”></a>';
        if ($msg[1] != '400') {
            $ussdmenu = $ussdmenu.$flow;
        }
        $ussdmenu = utf8_encode($ussdmenu);
        echo $ussdmenu;
        
        // echo "Service is temporarily down for maintenance up to 7:00 pm";
    } 

    /* When input response is hit then this part is going to run */
    else {
        //$var = nodeinfo('1234','node', $service, $msisdn, $input);
        $var = umenu('1234', 'node', $service, $msisdn, $input, $SessionId, $carrier);
        $msg = explode('<', $var);

        $ussdmenu = $msg[0];
        $ussdmenu = str_replace('\n', "<br>", $ussdmenu);
        
        $flow = '<a href=service.php default=”yes”></a>';
        if ($msg[1] != '400') {
            $ussdmenu = $ussdmenu.$flow;
        }
        $ussdmenu = utf8_encode($ussdmenu);
        echo $ussdmenu;
        // echo "Service is temporarily down for maintenance up to 7:00 pm";
    }

    // write ussd log
    $sql = "INSERT INTO ussd_log (user_type, gsm, session_id, input, resp) VALUES ('$service', '$msisdn', '$SessionId', '$input', '$ussdmenu')";
    $mysqli->query($sql);
    //$mysqli->close();
}
$mysqli->close();

