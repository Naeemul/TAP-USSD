<?php
//header("Content-length: 100");
error_reporting (0);

//header("Content-Encoding: none");
//error_reporting (0);
//header('Content-Transfer-Encoding: binary');

function endTran($msg="")
{
global $tid;
global $data_coding;
global $msisdn;
global $source_addr;
global $input;

header("Content-Type:application/xml");
$timestamp=date('Y/m/d H:i:s');
//$service_type="EA";
$service_type="CR";
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

################################# taking request #####################################
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

/** new changes for header character length */
$outputlength = (int) strlen(returnmsisdn());
header("Content-length: $outputlength");


$ussd_msg = "Your MSISDN is : $msisdn and User Input is : $input";
endTran($ussd_msg);
//returnmsisdn();


##################################
?>
