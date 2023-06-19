<?php

require_once includes_path('conn.php');
/*$mdn = '8801717428261';
init($mdn);
echo $v = init($mdn);*/


function init($mdn)
{
    $qry = "DELETE FROM mdnservices WHERE mdn = '".$mdn."'";
    mysql_query($qry);
    
    //get user text
    //$text = getWHOis($mdn);
    $text = "SELECT user_type FROM users where gsm = '".$mdn."'";
    mysql_query($text);
    
    $qry = "INSERT INTO mdnservices(mdn, service, center) VALUES('".$mdn."','".$text."','TRUST')";
    mysql_query($qry);
    
    return $text;
}

function getWHOis($mdn)
{
    //$str = "userNumber=".$mdn."&bankCode=TRUST";
    //url hit
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $_ENV['SERVER_WHOIS'] . "wcfSMSService.svc/whoProcessRequested?userNumber=".$mdn."");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('userNumber' => $mdn, 'bankCode' => 'TRUST')));
    //curl_setopt($ch, CURLOPT_POSTFIELDS, $str);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    $server_output = str_replace('"', '', $server_output);
    curl_close($ch);
    //exit();
    
    $service = array(
        "User" => 'TBL-USER',
        "Paypoint" => 'TBL-AGENT',
        "Authorized" => 'TBL-REG',
        "DSR" => 'TBL-DSR',
        "Retail" => "TBL-MAR",
        "Merchant" => "TBL-MAR-REST",
        "Distributor" => 'TBL-DIS',
        "Your account is Pre Active. Please contact customer service no.09604016201" => 'TBL-PRE',
        "You are not a registered user. Please contact customer service no.09604016201. " => 'TBL-OTHER'
    );

    $result = $service[$server_output];
    return $result;
}

function serviceType($mdn)
{
    $qry = "SELECT service FROM mdnservices WHERE mdn = '".$mdn."' AND center = 'TRUST'";
    $result = mysql_query($qry);

    $nbrows = mysql_num_rows($result);
    $service = '';
    if ($nbrows > 0) {
        while ($rec = mysql_fetch_array($result)) {
            $service = $rec['service'];
        }
    } else {
        $service = 'TBL-OTHER';
    }

    return $service;
}
