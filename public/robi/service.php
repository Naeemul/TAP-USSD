<?php

error_reporting(0);

header('Content-Type: text/html; charset=UTF-8');
header('Freeflow: FC');
header('charge: N');
header('amount: 0');

// Include bootstrap file
require_once __DIR__ . '/../../includes/bootstrap.php';

$userid = $_GET['userid'];
$password = $_GET['password'];
$input = $_GET['input'];
$msisdn = $_GET['msisdn'];
$SessionId = $_GET['SessionId'];
$ShortCode = $_GET['ShortCode'];

$carrier = 'robi';

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

if ($userid == '' || $userid != 'trust' || $password == '' || $password != 'trust123') {
    header('Freeflow: FB');
    echo 'Request Error: invalid userid or password';
} else {
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

    if (strpos($input, '*') !== false) {
        $parts = explode('*', $input);
        if (!empty($parts) && count($parts) == 7 &&
            $parts[1] == 99 && $parts[2] == 1) {
            deleteFromMdnstateByMdn($msisdn);

            $branch = '';

            foreach ($parts as $key => $value) {
                $iType = '';

                if ($key == 0) {
                    $value = 1;
                }

                if (in_array($key, [0, 1, 2])) {
                    $iType = 'MENU';
                    $branch .= $value;
                }

                if (in_array($key, [3, 4, 5])) {
                    $iType = 'INPUT';
                    $branch .= 'S';
                }

                if ($key == 6) {
                    $input = $value;

                    continue;
                }

                insertIntoMdnstate($msisdn, $branch, $service, $value, $iType);
            }
        }
    }
    
    if ($input == '0') {
        //$service = init($msisdn);
        //$var = nodeinfo('1234','init', $service, $msisdn, '1');
        $var = umenu('1234', 'init', $service, $msisdn, '1', $SessionId, $carrier);
        $msg = explode('<', $var);

        $ussdmenu = $msg[0];
        $ussdmenu = str_replace('\n', "\n", $ussdmenu);
        $ussdmenu = utf8_encode($ussdmenu);
        echo $ussdmenu;
    // echo "Service is temporarily down for maintenance up to 7:00 pm";
    } else {
        //$var = nodeinfo('1234','node', $service, $msisdn, $input);
        $var = umenu('1234', 'node', $service, $msisdn, $input, $SessionId, $carrier);
        $msg = explode('<', $var);

        $ussdmenu = $msg[0];
        $ussdmenu = str_replace('\n', "\n", $ussdmenu);
        $ussdmenu = utf8_encode($ussdmenu);
        if ($msg[1] == '400') {
            header('Freeflow: FB');
        }
        echo $ussdmenu;
        // echo "Service is temporarily down for maintenance up to 7:00 pm";
    }
    // write ussd log
    $sql = "INSERT INTO ussd_log (user_type, gsm, session_id, input, resp) VALUES ('$service', '$msisdn', '$SessionId', '$input', '$ussdmenu')";
    $mysqli->query($sql);
    //$mysqli->close();
}
$mysqli->close();
