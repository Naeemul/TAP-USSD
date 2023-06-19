<?php

require_once includes_path('schools.php');

function umenu($mdn, $state, $service, $ussdid, $input, $sessionId, $carrier)
{
    global $skul_list;
    $input = trim($input);

    $conn = mysqli_connect($_ENV['DB_HOST'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_DATABASE'], $_ENV['DB_PORT']);

    if ($state =='init') {
        $qry2 = "DELETE FROM mdnstate WHERE mdn = '".$ussdid."'";

        $conn->query($qry2);

          
          $qrydel = "DELETE FROM ussdinputs WHERE msisdn = '".$ussdid."'";

          $conn->query($qrydel);
          

        $sql = "SELECT text FROM appmenu where nid = '1' and service= '".$service."'";

        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                $nsql = "INSERT INTO mdnstate (mdn, state, branch, input_value, input_type) 
						VALUES  ('".$ussdid."', '1', '".$service."', '".$input."', 'MENU')";
                $conn->query($nsql);

                $output=$row["text"]."<200";
            }
        } else {
            $output = 'Invalid Input \nPress 0 for Main Menu <200';
        }
    }


    if ($state =='node') {

            
             $sql = "INSERT INTO ussdinputs (msisdn, input) 
             VALUES  ('".$ussdid."',  '".$input."')";
            
              $conn->query($sql);
              

              
        $sql3 = "SELECT branch, state, input_type FROM mdnstate where mdn = '".$ussdid."' and id= (select max(id) from mdnstate where mdn='".$ussdid."')";

        $result3 = mysqli_query($conn, $sql3);

        if (mysqli_num_rows($result3) > 0) {
            while ($row3 = mysqli_fetch_assoc($result3)) {
                $ztype= $row3["input_type"];
                $xnid= $row3["state"];

                // For REB Prepaid retry pending item
                if (
                    ($row3['branch'] == 'TBL-USER' && $row3['state'] == 14142) ||
                    ($row3['branch'] == 'TBL-AGENT' && $row3['state'] == 16142)
                ) {
                    $ztype = 'INPUT';
                }
            }
        }

        if ($ztype=='MENU') {
            $ustate= $xnid.$input;
        }

        if ($ztype=='INPUT') {
            $ustate= $xnid.'S';
            $smsText = $smsText.$input;
        }

        $sql2 = "SELECT id, text, type, userprocess, process, pervnode FROM appmenu where nid = '".$ustate."' and service= '".$service."'";
        $result2 = mysqli_query($conn, $sql2);

        if (mysqli_num_rows($result2) > 0) {
            while ($row2 = mysqli_fetch_assoc($result2)) {
                try {
                    updateIfRechargeAttempt($carrier, $sessionId, $row2['id']);
                } catch (Exception $e) {
                    // Do Nothing
                }

                $formatMob = array(
                    9,18,22,36,40,44,51,63,69,75,81,87,93,104,108,122,126,130,137,149,155,161,167,173,179,195,200,206,212,217,222,228,234,240,245,252,258,270,278,283,289,294,304,308,313,355,369,377,384,425,431,440,446,481,508,519,526,532,536,573,579,590,597,600,605,617,621,639,644,652,657,663,668,
                    720, // Merchant Cash Out from Agent
                    747, // User: School Banking (Nasirabad)
                    753, // Agent: School Banking (Nasirabad)
                    758, // User: Reb Prepaid: Notifying Mobile Number
                    763, // Agent: Reb Prepaid: Notifying Mobile Number
                    771, // Agent: Merchant Payment: Merchant Mobile Number
                    774, // Agent: Merchant Payment: Notifying Mobile Number
                );

                if (in_array($row2["id"], $formatMob)) {
                    $input = "880".substr($input, -10);
                }

                // Account list display array
                $accountList = array(297,361,545);

                // prepare for flash message general
                $flashMessageId = array(
                    10,19,23,105,109,305,309,314,356,370,378,385,533,537,554,583,
                    721, // Merchant Cash Out from Agent
                );

                // prepare for flash message, Fund Transfer
                $flashMsgFundTrasfer = array(299,363,547);

                // prepare flash message for utility WZPDC
                $WZPDCFlashId = array(281,286);

                // pin first array
                $pinFirstSkul = array("RCPSC","CPSCR","DCGPC","NCPSC","BISC","PISE","TMSR","LPSC","BGBPS","DPSC","CESC");

                if (in_array($row2["id"], $flashMessageId)) {
                    $get_user_input_sql ="SELECT input_value FROM mdnstate WHERE mdn = '".$ussdid."' AND state = '".$row2["pervnode"]."'";
                    $get_user_input_result = mysqli_query($conn, $get_user_input_sql);

                    while ($get_user_input_row = mysqli_fetch_assoc($get_user_input_result)) {
                        $agentno = $get_user_input_row["input_value"];
                    }

                    $message = $row2["process"]." BDT ".$input." to ".$agentno."|Please enter PIN to confirm: <200";
                } elseif (in_array($row2["id"], $WZPDCFlashId)) {
                    $get_user_input_sql ="SELECT input_value FROM mdnstate WHERE mdn = '".$ussdid."' AND state = '".$row2["pervnode"]."'";
                    $get_user_input_result = mysqli_query($conn, $get_user_input_sql);

                    while ($get_user_input_row = mysqli_fetch_assoc($get_user_input_result)) {
                        $agentno = $get_user_input_row["input_value"];
                    }

                    if ($service == 'TBL-USER') {
                        $WZPDCAmount = getWZPDCAmount($agentno, $input, 0);
                    } elseif ($service == 'TBL-AGENT') {
                        $WZPDCAmount = getWZPDCAmount($agentno, $input, 0);
                    }

                    $message = $row2["process"]." ".$agentno.", Bill Month ".$input.", Total Amount: ".$WZPDCAmount;
                } elseif ($row2["id"] == 42 ||$row2["id"] == 128) {
                    $DESCOresp = GetDESCOAmount($input);
                    $DESCOamount = str_replace('"', '', $DESCOresp);

                    if ((float)$DESCOamount > 0) {
                        $message = "Pay Tk". $DESCOamount ." to DESCO for Bill Number ". $input ."|Enter PIN to Confirm:";
                    } else {
                        $message = "Invalid Bill Number ". $input ."|Enter 0 to Retry:";
                    }
                    // $message = $DESCOamount;
                } elseif ($row2["id"] == 226 ||$row2["id"] == 232) {
                    $dpdc_sql = "SELECT GROUP_CONCAT(input_value SEPARATOR ' ') as userinput FROM mdnstate WHERE mdn = '".$ussdid."' AND state like '%S' GROUP BY mdn";
                    $dpdc_result = mysqli_query($conn, $dpdc_sql);

                    while ($dpdc_row = mysqli_fetch_assoc($dpdc_result)) {
                        $dpdc_arr = explode(' ', $dpdc_row["userinput"]);
                    }

                    $DPDCresp = GetDPDCAmount($dpdc_arr[1], $input, $dpdc_arr[0]);
                    $DPDCamount = str_replace('"', '', $DPDCresp);

                    if ((float)$DPDCamount > 0) {
                        $message = "Pay Tk". $DPDCamount ." to DPDC for Bill Number ". $dpdc_arr[0] ."|Enter PIN to Confirm:";
                    } else {
                        $message = "Invalid Customer Number/Location Code/Bill Month|Enter 0 to Retry:";
                    }
                } elseif (in_array($row2["id"], $accountList)) {
                    $accountArr = json_decode(getCBSAccount($ussdid));
                    $num = 1;

                    foreach ($accountArr as $val) {
                        $message = $message.$num.". ".$val."|";
                        $num ++;
                    }

                    $message = "Select Serial No.: |".$message.'<200';
                } elseif (in_array($row2["id"], $flashMsgFundTrasfer)) {
                    $get_user_input_sql ="SELECT input_value FROM mdnstate WHERE mdn = '".$ussdid."' AND state = '".$row2["pervnode"]."'";
                    $get_user_input_result = mysqli_query($conn, $get_user_input_sql);

                    while ($get_user_input_row = mysqli_fetch_assoc($get_user_input_result)) {
                        $agentno = $get_user_input_row["input_value"];
                    }

                    $message = $row2["process"]." BDT ".$input."|Please enter PIN to confirm: <200";
                } elseif (in_array($row2["id"], [142, 56, 140, 54])) {
                    $skul_code_sql = "SELECT GROUP_CONCAT(input_value SEPARATOR ' ') as userinput FROM mdnstate WHERE mdn = '".$ussdid."' AND state like '%S' GROUP BY mdn";
                    $skul_code_res = mysqli_query($conn, $skul_code_sql);

                    while ($get_skul_row = mysqli_fetch_assoc($skul_code_res)) {
                        $skul_code = $get_skul_row["userinput"];
                    }

                    $skulinputarry = explode(' ', $skul_code);

                    if (in_array($row2["id"], [142, 56])) {
                        $skullname = $skul_list[strtoupper($skulinputarry[0])];

                        if (!$skullname) {
                            $skullname = "Wrong school code: $skulinputarry[0]";
                        }

                        $message = $skullname."|Student Ref No ".$skulinputarry[1].'|Enter PIN to Confirm:';
                    } elseif (
                        in_array(strtoupper($skulinputarry[0]), ['CCPC', 'SGC', 'SCBHS', 'BNSCK', 'SHCPSC', 'JCPSC', 'AFMC', 'SCBBHS', 'RGC', 'GCBHS', 'HCPSC', 'RCBS', 'CDC', 'NSTU', 'HSDYKM', 'LIAMAD', 'ARMC', 'AMC', 'MKBMC', 'FCC', 'TIS', 'NCM', 'SEPI', 'KPI', 'NASCD','CBHSR', 'DC','MIS','RVCBS','NIC','CPSCJK', 'CBHSG','RUMC','AFMC' ,'CPSCBUSMS', 'JPSC' , 'SSMHS', 'BISC' , 'DCGPC' , 'GNIBC', 'IPSC','BBCPSC','HCISC','GTSC','DRMC']) &&
                        in_array($row2["id"], [140, 54])
                    ) {
                        // Custom Month/Year prompt for CCPC
                        $message = 'Enter Bill Month and Year (MMYYYY):';
                    } else {
                        $message = $row2["text"].'<200';
                    }
                } /*elseif($row2["id"] == 589){

                    $sql_carrier = "SELECT input from ussdinputs where id = (select max(id) from ussdinputs where msisdn = '".$ussdid."')";
                    
                    $result = mysqli_query($conn, $sql_carrier );
                    while ($row = mysqli_fetch_assoc($result)) {
                       
                        $carrier_type = $row['input'];
                    }

                    $sql_operator = "select input from ussdinputs where id = (select max(id) from ussdinputs where id < (select max(id) from ussdinputs)) and msisdn = '".$ussdid."'";
                    
                    $result = mysqli_query($conn, $sql_operator );
                    while ($row = mysqli_fetch_assoc($result)) {
                       
                        $operator_type = $row['input'];
                    }
                     
                    if ( ($operator_type ==1 &&   $carrier_type >=1 &&   $carrier_type <=3) 
                         || ($operator_type >=2 && $operator_type <=5  &&   $carrier_type >=1 &&   $carrier_type <=2)
                        ){
                        $message = $row2["text"];

                    }else{
                        $message = "Invalid Input|Press 0 for Main Menu <200";
                    }
                    
		    }*/elseif($row2["id"] == 591  ||  $row2["id"] == 580 ){

                    $sql_operator = "SELECT input from ussdinputs where id = (select max(id) from ussdinputs where msisdn = '".$ussdid."')";
                    
                    $result = mysqli_query($conn, $sql_operator );
                    while ($row = mysqli_fetch_assoc($result)) {
                       
                        $operator_type = $row['input'];
                    }
                    if ($operator_type >=1 && $operator_type <=5){

                        if ($operator_type == 1){
                            $message = $row2["text"]."|3. skitto";
                        }else{
                            $message = $row2["text"];
                        }

                    }else{
                        $message = "Invalid Input|Press 0 for Main Menu <200";
                    }

                } elseif ($row2["id"] == 582||$row2["id"] == 593) {
                    
                    $topup_sql = "SELECT GROUP_CONCAT(input_value SEPARATOR ' ') as userinput FROM mdnstate WHERE mdn = '".$ussdid."' AND state like '%S' GROUP BY mdn";
                    $topup_result = mysqli_query($conn, $topup_sql);

                    while ($topup_row = mysqli_fetch_assoc($topup_result)) {
                        
                        $topup_arry = explode(' ', $topup_row["userinput"]);
                    }

                    $con_type = array('blank', 'Prepaid', 'Postpaid');
                     //old msg
                    //$message =  "Your TopUp Tk. ". $topup_arry[1] ." to ". $con_type[$topup_arry[2]] ." number ". $topup_arry[0] ."|Enter PIN to confirm:";
                    $sql = "SELECT input from ussdinputs where id = (select max(id) from ussdinputs where msisdn = '".$ussdid."')";
                    
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)) {
                       
                        $topup_amount = $row['input'];
                    }
                    $message =  "Your TopUp Tk. ".$topup_amount ." to number ". $topup_arry[2] ."|Enter PIN to confirm:";

                } elseif ($row2["id"] == 624 || $row2["id"] == 615) {
                    $desco_sql = "SELECT input_value FROM mdnstate WHERE mdn = '".$ussdid."' AND state like '%S'";
                    $desco_result = mysqli_query($conn, $desco_sql);

                    while ($desco_row = mysqli_fetch_assoc($desco_result)) {
                        $meterNo = $desco_row["input_value"];
                    }
                   $message = "Your recharge request Tk $input to DESCO Account No. $meterNo|Enter PIN to Confirm:";
                   
                   // $message = "Your recharge request Tk $input to DESCO prepaid meter $meterNo|Enter PIN to Confirm:";
                    // $message = $DESCOamount;
                } elseif ($row2["id"] == 600 || $row2["id"] == 774) {
                    $mpay_sql = "SELECT GROUP_CONCAT(input_value SEPARATOR ' ') as userinput FROM mdnstate WHERE mdn = '".$ussdid."' AND state like '%S' GROUP BY mdn";
                    $mpay_result = mysqli_query($conn, $mpay_sql);

                    while ($mpay_row = mysqli_fetch_assoc($mpay_result)) {
                        $mpay_arry = explode(' ', $mpay_row["userinput"]);
                    }

                    $message =  "You are paying Tk. ". $mpay_arry[1] ." to ". $mpay_arry[0] ." for ". $mpay_arry[2] ."|Enter PIN to confirm:";
                } else {
                    $message = $row2["text"].'<200';
                }

                $ntype = $row2["type"];
                $nsql = "INSERT INTO mdnstate (mdn, state, branch, input_value, input_type, input_name) 
						VALUES  ('".$ussdid."', '".$ustate."', '".$service."', '".$input."', '".$ntype."', '".$row2["text"]."')";
                $conn->query($nsql);

                if ($ntype=='POST') {
                    $get_user_input_sql = "SELECT GROUP_CONCAT(input_value ORDER BY id SEPARATOR ' ') as userinput FROM mdnstate WHERE mdn = '".$ussdid."' AND state like '%S' GROUP BY mdn";
                    $get_user_input_result = mysqli_query($conn, $get_user_input_sql);

                    if (mysqli_num_rows($get_user_input_result) > 0) {
                        while ($get_user_input_row = mysqli_fetch_assoc($get_user_input_result)) {
                            $userinput = $get_user_input_row["userinput"];

                            $inputarry = explode(' ', $userinput);
                            if (in_array(strtoupper($inputarry[0]), $pinFirstSkul)) {
                                if ($service == 'TBL-USER') {
                                    // $output = "TRUSTMM ".$inputarry[0]." ".$inputarry[1]." ".$inputarry[2]." ".$inputarry[4]." ".$inputarry[3].'<400';
                                    $output = callTcashAPI($ussdid, "TRUSTMM ".$inputarry[0]." ".$inputarry[1]." ".$inputarry[2]." ".$inputarry[4]." ".$inputarry[3], $carrier);
                                    $output=  $output.'<400';
                                } elseif ($service == 'TBL-AGENT') {
                                    // $output = "TRUSTMM ".$inputarry[0]." ".$inputarry[1]." ".$inputarry[2]." ".$inputarry[4]." ".$inputarry[3]." ".$inputarry[5];
                                    $output = callTcashAPI($ussdid, "TRUSTMM ".$inputarry[0]." ".$inputarry[1]." ".$inputarry[2]." ".$inputarry[4]." ".$inputarry[3]." ".$inputarry[5], $carrier);
                                    $output=  $output.'<400';
                                }
                            } elseif (strtoupper($inputarry[0]) == 'BUP') {
                                if ($service == 'TBL-USER') {
                                    $output = callTcashAPI($ussdid, "TRUSTMM ".$inputarry[0]." ".$inputarry[1]." 1 ".$inputarry[2]." 8809999999999 ".$inputarry[4], $carrier);
                                    $output=  $output.'<400';
                                } elseif ($service == 'TBL-AGENT') {
                                    $output = callTcashAPI($ussdid, "TRUSTMM ".$inputarry[0]." ".$inputarry[1]." 1 ".$inputarry[2]." ".$inputarry[5]." ".$inputarry[4], $carrier);
                                    $output=  $output.'<400';
                                }
			    } else {
                                 $sqlinput = "SELECT * FROM ussdinputs WHERE msisdn = '".$ussdid."' ORDER BY id asc";
                                $result2 = mysqli_query($conn, $sqlinput);
                                $pre_post_data = array();

                                if (mysqli_num_rows($result2) > 0) {
                                    while ($rowdata = mysqli_fetch_assoc($result2)) {
                                     
                                        array_push( $pre_post_data, $rowdata["input"]);
                                    }
                                }
                                if (strtolower($row2["userprocess"]) == 'despr' && $pre_post_data[2] == 2) {
                                                                      
                                    $output =  descoService($ussdid, $pre_post_data, $service);
                                    $output .= '<400';
                                    $output=  $output.'<400';
                                
                                    
                                }
                                else if (strtolower($row2["userprocess"]) == 'topup') {
                                    $operator = array("blank","gp","blink","robi","airtel","teletalk","citycell");
                                    //$output = callTcashAPI($ussdid, "TRUSTMM topup ".$inputarry[0]." ".$inputarry[1]." ".$input." ".$inputarry[2]." ".$operator[$inputarry[3]], $carrier);
                                    //$output = sendTopup($ussdid, $inputarry[0], $inputarry[1], $inputarry[2], str_replace(4, 6, $inputarry[3]), $input);
                                    $output = sendTopupNewProcess(getTopUpParams($ussdid, $userinput), $userinput);
                                    $output .= '<400';


                                    $output=  $output.'<400';
                                } elseif (strtolower($row2["userprocess"]) == 'mpay') {
                                    $output = callTcashAPI($ussdid, "TRUSTMM MPAY ".$inputarry[0]." ".$inputarry[2]." ".$inputarry[1]." ".$input." ".$inputarry[3], $carrier);
                                    $output=  $output.'<400';
                                } elseif (strtolower($row2["userprocess"]) == 'nid') {
                                    $output = callTcashAPI($ussdid, "TRUSTMM NID ".$inputarry[0]." ".$inputarry[1]." ".$inputarry[2]." 1 ".$input, $carrier);
                                    $output=  $output.'<400';
                                } elseif (strtolower($row2["userprocess"]) == 'ppreg') {
                                    $gender = array("blank","M","F");
                                    $accountType = array(0,78,72);
                                    $output = callTcashAPI($ussdid, "TRUSTMM PPREG ".$inputarry[0]." ".$inputarry[1]." ".$inputarry[2]." ".$inputarry[3]." ".$inputarry[4]." ".$gender[$inputarry[5]]." ".$accountType[$input], $carrier);
                                    $output=  $output.'<400';
                                } elseif (strtolower($row2["userprocess"]) == 'nespr') {
                                    $nescoFetchResponse = nescoFetchType($ussdid, $userinput);

                                    if ($nescoFetchResponse['responseCode'] == '000') {
                                        if ($nescoFetchResponse['prepaidType'] == 'WASION') {
                                            $output = nescoWasionPost('wasion/payment', getNescoWasionParams($ussdid, $userinput, $nescoFetchResponse['transactionId']));
                                            $output .= '<400';
                                        } else {
                                            $output = callTcashAPI($ussdid, "TRUSTMM {$row2['userprocess']} {$userinput}", $carrier);
                                            $output .= '<400';
                                        }
                                    } else {
                                        $output = $nescoFetchResponse['responseMessage'];
                                        $output .= '<400';
                                    }
                                } elseif (strtoupper($row2["userprocess"]) == KEYWORD_REB_PREPAID) {
                                    $output = '<400';

                                    if (!preg_match('/(8{2}01[^012]\d{8})$/', $userinput)) {
                                        $output = 'Invalid Notifying Mobile Number!<400';
                                    } else {
                                        $output = rebPrePost(getRebPreParams($ussdid, $userinput));
                                        $output .= '<400';
                                    }
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_REB_PREPAID_RETRY) {
                                    $output = retryRebPrePendingBill($ussdid, $userinput);
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_INSURANCE_METLIFE_ALICO) {
                                    $output = callTcashAPI($ussdid, getSmsTextForMetLifeAlico($userinput), $carrier);
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_DHAKA_WASA) {
                                    $output = ekpayPost(getEkpayDhakaWasaParams($ussdid, $userinput));
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_KHULNA_WASA) {
                                    $output = ekpayPost(getEkpayKhulnaWasaParams($ussdid, $userinput));
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_BAKHRABAD_GAS) {
                                    $output = ekpayPost(getEkpayBakhrabadGasParams($ussdid, $userinput));
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_WZPDC_POSTPAID) {
                                    $output = ekpayPost(getEkpayWzpdcParams($ussdid, $userinput));
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_CARNIVAL) {
                                    $params = getCarnivalParams($ussdid, $userinput);
                                    $output = !is_array($params) ? $params : carnivalPost($params);
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_AKASH_DTH) {
                                    $output = akashDthPost(getAkashDthParams($ussdid, $userinput));
                                    $output .= '<400';
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_SCHOOL_BANKING_NASIRABAD) {
                                    $output = '<400';

                                    if (!preg_match('/(8{2}01[^012]\d{8})$/', $userinput)) {
                                        $output = 'Invalid Notifying Mobile Number!<400';
                                    } else {
                                        $output = callTcashAPI($ussdid, "TRUSTMM MPAY " . SCHOOL_BANKING_NASIRABAD_WALLET . " " . $userinput, $carrier);
                                        $output .= '<400';
                                    }
                                } elseif ($row2["userprocess"] == '' && preg_match('/^\s?(CCPC|SGC|SCBHS|BNSCK|SHCPSC|JCPSC|AFMC|SCBBHS|RGC|GCBHS|HCPSC|RCBS|NSTU|CDC|HSDYKM|LIAMAD|ARMC|AMC|MKBMC|FCC|TIS|NCM|SEPI|KPI|NASCD|CBHSR|DC|MIS|RVCBS|NIC|CPSCJK|CBHSG|RUMC|AFMC|CPSCBUSMS|JPSC|SSMHS|BISC|DCGPC|GNIBC|IPSC|BBCPSC|HCISC|GTSC|DRMC)\s.*/i', $userinput, $matches)) {
                                    $output = schoolServicePost(getSchoolServiceParams($ussdid, $userinput));
                                    $output .= '<400';
                                    // } elseif ($row2["userprocess"] == '' && preg_match('/^\s?(AFMC)\s.*/i', $userinput, $matches)) {
                                    //     $output = "Payment for {$matches[1]} is temporarily unavailable.<400";
                                } elseif (strtoupper($row2['userprocess']) == KEYWORD_UTILITY_INSURANCE_PRIME_LIFE) {
                                    //dd($userinput);
                                    $output = primeLifeInsPost(getPrimeLifeParams($ussdid, $userinput));
                                    $output .= '<400';
                                } else {
                                    //dd(strtoupper($row2['userprocess']), $ussdid, $userinput);
                                    $output = callTcashAPI($ussdid, "TRUSTMM ".$row2["userprocess"]." ".$userinput, $carrier);
                                    $output=  $output.'<400';
                                }
                            }
                        }
                    } else {
                        if (strtoupper($row2['userprocess']) == 'DIP-DIS') { // Disable Passport Fees
                            $output = 'Passport Fee payment is temporarily unavailable. Sorry for the inconvenience.<400';
                        } elseif (strtoupper($row2["userprocess"]) == KEYWORD_REB_PREPAID_FETCH) {
                            $output = getFormattedRebPrePendingBills($ussdid);
                        } else {
                            $output = callTcashAPI($ussdid, "TRUSTMM ".$row2["userprocess"], $carrier);
                            $output=  $output.'<400';
                        }
                    }

                    try {
                        setRgCandidateWithResponse($carrier, $sessionId, $row2['id'], $output);
                    } catch (Exception $e) {
                        // Do Nothing
                    }
                } else {
                    $output = $message;
                }
            }
        } else {
            $output = 'Invalid Input|Press 0 for Main Menu <200';
        }
    }

    mysqli_close($conn);

    $var = str_replace('|', '\n', $output);
    return $var;
}

function callTcashAPI($mdn, $text, $operator)
{
    $baseUrl = $_ENV['SERVER_TRNX'];
    $telcoId = null;
    if($operator == "bl"){
        $telcoId = 3;
    } elseif ($operator == "gp"){
        $telcoId = 1;
    } elseif ($operator == "robi"){
        $telcoId = 4;
    } elseif ($operator == "tt"){
        $telcoId = 5;
    }

    $curl = curl_init();

    $post_text = urlencode($text);

    //dd("wcfSMSService.svc/ProcessRequest?msgId=adwwa12&userNumber=$mdn&smstext=$post_text&telcoid=$telcoId&shortCode=16201&encKey=122212");


    curl_setopt_array($curl, array(
        CURLOPT_URL => $baseUrl . "wcfSMSService.svc/ProcessRequest?msgId=adwwa12&userNumber=$mdn&smstext=$post_text&telcoid=$telcoId&shortCode=16201&encKey=122212",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array('Content-Length: 0'),
    ));

    $response = curl_exec($curl);
    //$err = curl_error($curl);
    curl_close($curl);
    return $response;
}

function getWZPDCAmount($account, $bill, $otc)
{
    try {
        $TBLC_XmlMsgClient = new SoapClient($_ENV['SERVER_CHECKOUT'] . "checkout/MbillPlus_payment.asmx?WSDL");
        $TBLC_XmlMsgParams = array(
            'acc_num'  => $account,
            'billcycle' => $bill,
            'otc' => $otc,
            'KeyCode' => 'ADC20F88-7244-4F61-8539-F24317CFFD26'
        );

        $TBLC_XmlMsgResult = $TBLC_XmlMsgClient->Get_Bill_Due_Info_MB($TBLC_XmlMsgParams)->Get_Bill_Due_Info_MBResult;

        if (strpos($TBLC_XmlMsgResult, '|') !== false) {
            $resp = explode('|', $TBLC_XmlMsgResult);
            return $resp[1].', Please enter PIN to confirm.|t-Cash PIN:<200';
        }

        if (strpos($TBLC_XmlMsgResult, ',') !== false) {
            $resp = explode(',', $TBLC_XmlMsgResult);
            return $resp[1].'<400';
        }
    } catch (Exception $e) {
        return $e;
    }
}

// Get user CBS account list
// Date: 24-10-18
function getCBSAccount($gsm)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $_ENV['SERVER_TRNX'] . "wcfSMSService.svc/GetCoreAccountInfo?userNumber=$gsm",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array('Content-Length: 0'),
    ));

    $response = curl_exec($curl);
    //$err = curl_error($curl);
    curl_close($curl);
    return $response;
}

// Get DESCO Bill Amount
// Date: 12-03-19
function GetDESCOAmount($billno)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $_ENV['SERVER_TRNX'] . "wcfSMSService.svc/GetDescoBillInfo?BillNo=$billno",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array('Content-Length: 0'),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

// Get DPDC Bill Amount
// Date: 12-03-19
function GetDPDCAmount($areaCode, $billMonth, $acNumber)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $_ENV['SERVER_TRNX'] . "wcfSMSService.svc/GetDPDCBillInfo?LocationCode=$areaCode&BillMonth=$billMonth&accountNumber=$acNumber",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array('Content-Length: 0'),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

// Send Topup
// Date: 29-06-2021
function sendTopup($gsm, $msisdn, $amount, $connectionType, $operator, $pin)
{
    $curl = curl_init();

    $connectionTypeArr = array("", "prepaid", "postpaid");
    $connectionTypeName = $connectionTypeArr[$connectionType];
    $pinBase64Encode = base64_encode($pin);

    curl_setopt_array($curl, array(
        CURLOPT_URL => $_ENV['SERVER_AUTH'] . 'wcfSMSService.svc/RechargeGateway',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
        "OperatorId":"'.$operator.'",
        "RecipientMsisdn":"'.$msisdn.'",
        "Amount":'.$amount.',
        "ConnectionType":"'.$connectionTypeName.'",
        "FromAccount":'.$gsm.',
        "Pin":"'.$pinBase64Encode.'"
    }',
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $_ENV['SERVER_AUTH_TOKEN'],
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $firstDecode = json_decode($response);
    $firstArr = array($firstDecode);
    $secondDecode = json_decode($firstArr[0]);

    return $secondDecode->Message;
};
