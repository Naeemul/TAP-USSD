<?php

error_reporting(0);
//set necessary header
header('Content-Type: text/html; charset=UTF-8');
header('operation 6003');
header('Freeflow: FC');
header('charge: N');
header('amount: 0');

// Include bootstrap file
require_once __DIR__ . '/../../includes/bootstrap.php';

//ser parameter value
$userid = $_GET['userid'];
$password = $_GET['password'];
$input = $_GET['userData'];
$msisdn = $_GET['mobileNo'];
$SessionId = $_GET['transId'];
$ShortCode = $_GET['short_code'];
$request = $_GET['NEW_REQUEST'];

$carrier = 'bl';

$mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE'], $_ENV['DB_PORT']);

try {
    initSession($carrier, $msisdn, $SessionId);
} catch (Exception $e) {
    // Do Nothing
}

$ititCheckSQL = "SELECT count(ID) total from mdnstate WHERE MDN = '$msisdn' and SESSION_ID = '$SessionId'";
$ititCheckResult = $mysqli->query($ititCheckSQL);

if ($ititCheckResult->num_rows > 0) {
    while ($row = $ititCheckResult->fetch_array()) {
        $count = $row['total'];
    }
    $ititCheckResult->free();
}

if ($count == 0 && $input == '733') {
    $input = 0;
}

$sql = "SELECT user_type FROM users where gsm = $msisdn";

// if($userid == '' || $userid != 'trust' || $password == '' || $password != 'trust123'){
// 	header('operation:6007');
//     echo 'Request Error: invalid userid or password';

// }
// else{
    
    if ($result = $mysqli->query($sql)) {
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
    }

    try {
        setUserType($carrier, $SessionId, $service);
    } catch (Exception $e) {
        // Do Nothing
    }

    // $mysqli->close();
    
    if ($input == '0' || $request == 1) {
        //$service = init($msisdn);
        //$var = nodeinfo('1234','init', $service, $msisdn, '1');
        $var = umenu('1234', 'init', $service, $msisdn, '1', $SessionId, $carrier);
        $msg = explode('<', $var);

        $ussdmenu = $msg[0];
        $ussdmenu = str_replace('\n', "\n", $ussdmenu);
        $ussdmenu = utf8_encode($ussdmenu);
        echo $ussdmenu;
    // echo "Service is temporarily down for maintenance up to 5:00 pm";
    } else {
        //$var = nodeinfo('1234','node', $service, $msisdn, $input);
        $var = umenu('1234', 'node', $service, $msisdn, $input, $SessionId, $carrier);
        $msg = explode('<', $var);

        $ussdmenu = $msg[0];
        $ussdmenu = str_replace('\n', "\n", $ussdmenu);
        $ussdmenu = utf8_encode($ussdmenu);
        if ($msg[1] == '400') {
            header('operation: 6007');
            header('Freeflow: FB');
        }

        echo $ussdmenu;
        // echo "Service is temporarily down for maintenance up to 5:00 pm";
    }

    //write ussd log
    $sql = "INSERT INTO ussd_log (user_type, gsm, session_id, input, resp) VALUES ('$service', '$msisdn', '$SessionId', '$input', '$ussdmenu')";
    $mysqli->query($sql);
    $mysqli->close();
