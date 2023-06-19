<?php

# Helpers defined in this file can use Composer packages

const KEYWORD_UTILITY_INSURANCE_METLIFE_ALICO = 'MA';
const KEYWORD_UTILITY_DHAKA_WASA = 'WASADE';
const KEYWORD_UTILITY_KHULNA_WASA = 'WASAKE';
const KEYWORD_UTILITY_BAKHRABAD_GAS = 'BAKHRE';
const KEYWORD_UTILITY_WZPDC_POSTPAID = 'WZPDCE';
const KEYWORD_UTILITY_AKASH_DTH = 'AKASHDTH';
const KEYWORD_UTILITY_CARNIVAL = 'CARNIVAL';
const KEYWORD_SCHOOL_BANKING_NASIRABAD = 'NASIRABAD';
const SCHOOL_BANKING_NASIRABAD_WALLET = '8800200000029';
const KEYWORD_REB_PREPAID = 'REBPREPAID';
const KEYWORD_REB_PREPAID_FETCH = 'REBPREFETCH';
const KEYWORD_REB_PREPAID_RETRY = 'REBPRERETRY';
const KEYWORD_UTILITY_INSURANCE_PRIME_LIFE = 'PILIL';

if (!function_exists('getSmsText')) {
    /**
     * Get SMS text for t-cash API
     *
     * @param  string  $keyword
     * @param  string  $text
     *
     * @return string
     */
    function getSmsText(string $keyword, string $text): string
    {
        return implode(' ', [
            'TRUSTMM',
            $keyword,
            $text
        ]);
    }
}

if (!function_exists('getMetLifeAlicoPolicy')) {
    /**
     * Get MetLife ALICO policy string by index
     *
     * @param  int  $index
     *
     * @return string
     */
    function getMetLifeAlicoPolicy(int $index): string
    {
        $policies = [
            'n/a',
            'Premium',
            'Application',
            'Repayment_of_Loan',
            'Repayment_of_APL',
            'Reinstatement_(Auto_surrender)',
            'Reinstatement_(Lapse)',
            'Balance_Premium',
        ];

        return !isset($policies[$index]) ? $policies[0] : $policies[$index];
    }
}

if (!function_exists('getSmsTextForMetLifeAlico')) {
    /**
     * Get SMS text for MetLife ALICO
     *
     * @param  string  $text
     *
     * @return string
     */
    function getSmsTextForMetLifeAlico(string $text): string
    {
        // InsurancePolicyNumber PolicyType Amount PIN NotifyingMobileNumber
        preg_match('/^\s?([^\s]+)\s+(\d)\s+(\d+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/i', $text, $matches);

        if (!empty($matches)) {
            // Move policy position at the end
            $text = implode(' ', [
                $matches[1], // InsurancePolicyNumber
                $matches[3], // Amount
                $matches[4], // PIN
                '88'.$matches[8], // NotifyingMobileNumber
                getMetLifeAlicoPolicy($matches[2]) // PolicyType
            ]);
        } else {
            $text = $text.' '.getMetLifeAlicoPolicy(0);
        }

        return getSmsText(KEYWORD_UTILITY_INSURANCE_METLIFE_ALICO, $text);
    }
}

if (!function_exists('getGuzzelClient')) {
    /**
     * Get an instance of Guzzel HTTP client
     *
     * @param  string  $baseUri
     * @param  array  $headers
     *
     * @return \GuzzleHttp\Client
     */
    function getGuzzelClient(string $baseUri, array $headers = []): GuzzleHttp\Client
    {
        return new GuzzleHttp\Client([
            'base_uri' => $baseUri,
            'http_errors' => false,
            'headers' => !empty($headers) ? $headers : [
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}

if (!function_exists('ekpayPost')) {
    /**
     * Submit for Ekpay
     *
     * @param  array  $params
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function ekpayPost(array $params): string
    {
        $client = getGuzzelClient($_ENV['SERVER_EKPAY']);

        $response = $client->post('billing/ussd-payment', [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('getEkpayBillType')) {
    /**
     * Get Ekpay bill type string by index
     *
     * @param  int  $index
     *
     * @return string
     */
    function getEkpayBillType(int $index): string
    {
        $billTypes = [
            '',
            'M',
            'NM',
        ];

        return !isset($billTypes[$index]) ? $billTypes[0] : $billTypes[$index];
    }
}


if (!function_exists('getEkpayDhakaWasaParams')) {
    /**
     * Get params for Ekpay: Dhaka WASA
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getEkpayDhakaWasaParams(string $mobileNumber, string $ssv): array
    {
        // BillNumber PIN NotificationNumber
        preg_match('/^\s?([^\s]+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/i', $ssv, $matches);

        if (!empty($matches)) {
            return [
                'code' => KEYWORD_UTILITY_DHAKA_WASA,
                'accountNumber' => $mobileNumber,
                'billNumber' => $matches[1],
                'pin' => base64_encode($matches[2]),
                'notificationNumber' => $matches[6]
            ];
        }

        return [];
    }
}

if (!function_exists('getEkpayKhulnaWasaParams')) {
    /**
     * Get params for Ekpay: Khulna WASA
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getEkpayKhulnaWasaParams(string $mobileNumber, string $ssv): array
    {
        // BillNumber BillType PIN NotificationNumber
        preg_match('/^\s?([^\s]+)\s+(\d)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/i', $ssv, $matches);

        if (!empty($matches)) {
            return [
                'code' => KEYWORD_UTILITY_KHULNA_WASA,
                'accountNumber' => $mobileNumber,
                'billNumber' => $matches[1],
                'billType' => getEkpayBillType($matches[2]),
                'pin' => base64_encode($matches[3]),
                'notificationNumber' => '88'.$matches[7]
            ];
        }

        return [];
    }
}

if (!function_exists('getEkpayBakhrabadGasParams')) {
    /**
     * Get params for Ekpay: Bakhrabad Gas
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getEkpayBakhrabadGasParams(string $mobileNumber, string $ssv): array
    {
        // CustomerOnlineCode OnlineRegisteredMobileNumber BillType PIN NotificationNumber
        preg_match('/^\s?([^\s]+)\s+((\+)?(8{2})?(01[^012]\d{8}))\s+(\d{1})\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/i',
            $ssv, $matches);

        if (!empty($matches)) {
            return [
                'code' => KEYWORD_UTILITY_BAKHRABAD_GAS,
                'accountNumber' => $mobileNumber,
                'billAccountNumber' => $matches[1],
                'billMobileNumber' => '88'.$matches[5],
                'billType' => getEkpayBillType($matches[6]),
                'pin' => base64_encode($matches[7]),
                'notificationNumber' => '88'.$matches[11]
            ];
        }

        return [];
    }
}


if (!function_exists('getEkpayWzpdcParams')) {
    /**
     * Get params for Ekpay: WZPDC Postpaid
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getEkpayWzpdcParams(string $mobileNumber, string $ssv): array
    {
        // AccountNumber PIN NotificationNumber
        preg_match('/^\s?([^\s]+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/i', $ssv, $matches);

        if (!empty($matches)) {
            return [
                'code' => KEYWORD_UTILITY_WZPDC_POSTPAID,
                'accountNumber' => $mobileNumber,
                'billAccountNumber' => $matches[1],
                'pin' => base64_encode($matches[2]),
                'notificationNumber' => '88'.$matches[6]
            ];
        }

        return [];
    }
}

if (!function_exists('akashDthPost')) {
    /**
     * Submit for AKASH DTH
     *
     * @param  array  $params
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function akashDthPost(array $params): string
    {
        $client = getGuzzelClient($_ENV['SERVER_TV']);

        $response = $client->post('/billing/tv/akash/ussd', [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('getAkashDthParams')) {
    /**
     * Get params for AKASH DTH
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getAkashDthParams(string $mobileNumber, string $ssv): array
    {
        // SubscriberID Amount PIN NotificationNumber
        preg_match('/^\s?([^\s]+)\s+(\d+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/', $ssv, $matches);

        if (!empty($matches)) {
            return [
                'accountNo' => $matches[1], // AKASH DTH Subscriber ID
                'amount' => $matches[2],
                'mfsAccountNo' => $mobileNumber,
                'pin' => base64_encode($matches[3]),
                'notificationNumber' => '88'.$matches[7],
            ];
        }

        return [];
    }
}

if (!function_exists('getSchoolServiceParams')) {
    /**
     * Get params for School Service
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getSchoolServiceParams(string $mobileNumber, string $ssv): array
    {
        // Code billNumber billMonth pin notificationNumber?
        preg_match('/^\s?(CCPC|SGC|SCBHS|BNSCK|SHCPSC|JCPSC|AFMC|SCBBHS|RGC|GCBHS|HCPSC|RCBS|NSTU|CDC|HSDYKM|LIAMAD|ARMC|AMC|MKBMC|FCC|TIS|NCM)\s+([^\s]+)\s+(\d{6})\s+([^\d]+)\s+(\d{4,5})(\s?(\+)?(8{2})?(01[^012]\d{8}))?\s?$/i',
            $ssv, $matches);

        $params = [];

        if (!empty($matches)) {
            $params['code'] = strtoupper($matches[1]);
            $params['accountNumber'] = $mobileNumber;
            $params['billNumber'] = $matches[2];
            $params['billMonth'] = $matches[3];
            $params['pin'] = base64_encode($matches[5]);

            if (isset($matches[9])) {
                $params['notificationNumber'] = $matches[9];
            }
        }

        return $params;
    }
}

if (!function_exists('schoolServicePost')) {
    /**
     * Submit for School Service
     *
     * @param  array  $params
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function schoolServicePost(array $params): string
    {
        $client = getGuzzelClient($_ENV['SERVER_SCHOOL_SERVICE_ENDPOINT']);

        $response = $client->post('billing/ussd-payment', [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('nescoWasionPost')) {
    /**
     * Submit for NESCO Wasion
     *
     * @param  string  $slug
     * @param  array  $params
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function nescoWasionPost(string $slug, array $params): string
    {
        $client = getGuzzelClient($_ENV['SERVER_NESCO_WASION_ENDPOINT']);

        $response = $client->post('billing/nesco/prepaid/ussd/'.$slug, [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('nescoFetchType')) {
    /**
     * Fetch NESCO PrePaid Type
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function nescoFetchType(string $mobileNumber, string $ssv): array
    {
        // CustomerNo Amount PIN NotifyingNumber
        preg_match('/^\s?([^\s]+)\s+(\d+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/', $ssv, $matches);

        $params = [
            'amount' => $matches['2'],
            'mfsAccountNo' => $mobileNumber,
            'customerId' => $matches[1],
        ];

        $response = nescoWasionPost('fetch', $params);

        $responseArr = json_decode($response, true);

        $output = [
            'responseCode' => '',
            'prepaidType' => '',
            'transactionId' => '',
            'responseMessage' => $response,
        ];

        if (is_null($responseArr)) {
            return $output;
        }

        if (
            is_array($responseArr) &&
            array_key_exists('responseCode', $responseArr) &&
            array_key_exists('prepaidType', $responseArr) && !is_null($responseArr['prepaidType']) &&
            array_key_exists('transactionId', $responseArr) && !is_null($responseArr['transactionId'])
        ) {
            $output['responseCode'] = $responseArr['responseCode'];
            $output['prepaidType'] = $responseArr['prepaidType'];
            $output['transactionId'] = $responseArr['transactionId'];
        }

        if (array_key_exists('responseMessage', $responseArr)) {
            $output['responseMessage'] = $responseArr['responseMessage'];
        }

        return $output;
    }
}


if (!function_exists('getNescoWasionParams')) {
    /**
     * Get params for NESCO Wasion
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     * @param  string  $transactionId
     *
     * @return array
     */
    function getNescoWasionParams(string $mobileNumber, string $ssv, string $transactionId)
    {
        // CustomerNo Amount PIN NotifyingNumber
        preg_match('/^\s?([^\s]+)\s+(\d+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/', $ssv, $matches);

        if (!empty($matches)) {
            return [
                "amount" => $matches[2],
                "customerId" => $matches[1], // Customer No
                "mfsAccountNo" => $mobileNumber,
                "notificationNumber" => '88'.$matches[7],
                "pin" => base64_encode($matches[3]),
                "transactionId" => $transactionId,
            ];
        }

        return [];
    }
}

if (!function_exists('getCarnivalParams')) {
    /**
     * Get params for CARNIVAL
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array|string
     */
    function getCarnivalParams(string $mobileNumber, string $ssv)
    {
        // CarnivalUserID Amount PIN NotifyingNumber
        preg_match('/^\s?([^\s]+)\s+(\d+)\s+(\d{4,5})\s+((\+)?(8{2})?(01[^012]\d{8}))\s?$/', $ssv, $matches);

        if (!empty($matches)) {
            $amountMin = $_ENV['CARNIVAL_AMOUNT_MIN'] == '' ? 10 : $_ENV['CARNIVAL_AMOUNT_MIN'];
            $amountMax = $_ENV['CARNIVAL_AMOUNT_MAX'] == '' ? 10000 : $_ENV['CARNIVAL_AMOUNT_MAX'];

            if ($matches[2] < $amountMin || $matches[2] > $amountMax) {
                return "Amount must be between {$amountMin} and {$amountMax}.";
            }

            return [
                "amount" => $matches[2],
                "mfsAccountNo" => $mobileNumber,
                "notificationNumber" => '88'.$matches[7],
                "pin" => base64_encode($matches[3]),
                "userNumber" => $matches[1], // Carnival User ID
            ];
        }

        return [];
    }
}

if (!function_exists('carnivalPost')) {
    /**
     * Submit for Carnival
     *
     * @param  array  $params
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function carnivalPost(array $params): string
    {
        $client = getGuzzelClient($_ENV['SERVER_CARNIVAL_ENDPOINT']);

        $response = $client->post('billing/internet/carnival/ussd', [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('deleteFromMdnstateByMdn')) {
    /**
     * Delete rows from mdnstate by mdn
     *
     * @param  string  $mdn
     *
     * @return void
     */
    function deleteFromMdnstateByMdn(string $mdn): void
    {
        global $mysqli;

        $mysqli->query("DELETE FROM mdnstate WHERE mdn = '$mdn';");
    }
}

if (!function_exists('insertIntoMdnstate')) {
    /**
     * Insert into mdnstate table
     *
     * @param  string  $mdn
     * @param  string  $state
     * @param  string  $branch
     * @param  int  $inputValue
     * @param  string  $inputType
     *
     * @return void
     */
    function insertIntoMdnstate(string $mdn, string $state, string $branch, int $inputValue, string $inputType): void
    {
        global $mysqli;

        $sql = "INSERT INTO mdnstate SET mdn = '$mdn', state = '$state', branch = '$branch', input_value = '$inputValue',";
        $sql .= "input_type = '$inputType';";

        $mysqli->query($sql);
    }
}

if (!function_exists('getRebPreBaseUrl')) {
    /**
     * Get REB Prepaid Service URL
     *
     * @return string
     */
    function getRebPreBaseUrl(): string
    {
        // @TODO: Remove later
        $baseUrl = $_ENV['SERVER_TRNX_REB'] == '' ? $_ENV['SERVER_TRNX'] : $_ENV['SERVER_TRNX_REB'];

        return $baseUrl.'billing/reb/prepaid/';
    }
}

if (!function_exists('getRebPreParams')) {
    /**
     * Get params for REB Prepaid
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getRebPreParams(string $mobileNumber, string $ssv): array
    {
        // MeterNo Amount PIN Notification Number
        preg_match('/\s?(\d*)\s+(\d*)\s+(\d{4,5})\s+((8{2})?(01[^012]\d{8}))/i', $ssv, $matches);

        $params = [];

        if (!empty($matches)) {
            $params['amount'] = strtoupper($matches[2]);
            $params['meterNumber'] = $matches[1];
            $params['mfsAccountNo'] = $mobileNumber;
            $params['notificationNumber'] = $matches[6];
            $params['pin'] = base64_encode($matches[3]);
        }

        return $params;
    }
}

if (!function_exists('rebPrePost')) {
    /**
     * Submit for REB Prepaid
     *
     * @param  array  $params
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function rebPrePost(array $params): string
    {
        $client = getGuzzelClient(getRebPreBaseUrl());

        $response = $client->post('ussd/payment', [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('prependCountryCodeTo')) {
    /**
     * Prepend country code to MSISDN
     *
     * @param  string  $msisdn
     * @param  string  $countryCode
     *
     * @return string
     */
    function prependCountryCodeTo(string $msisdn, string $countryCode = '88'): string
    {
        return $countryCode.substr($msisdn, -11);
    }
}

if (!function_exists('fetchRebPrePendingBills')) {
    /**
     * Fetch REB Prepaid pending bills
     *
     * @param  string  $msisdn
     *
     * @return mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function fetchRebPrePendingBills(string $msisdn)
    {
        $client = getGuzzelClient(getRebPreBaseUrl());

        $response = $client->post('pending-list', [
            'query' => [
                'msisdn' => prependCountryCodeTo($msisdn, '')
            ]
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        $body = (string) $response->getBody();
        $pendingTransactions = json_decode($body, true);

        if (is_array($pendingTransactions) && array_key_exists('rebPendingTransactionList', $pendingTransactions)) {
            return $pendingTransactions['rebPendingTransactionList'];
        }

        return [];
    }
}

if (!function_exists('getFormattedRebPrePendingBills')) {
    /**
     * Get formatted REB Prepaid pending bills
     *
     * @param  string  $msisdn
     *
     * @return string
     */
    function getFormattedRebPrePendingBills(string $msisdn): string
    {
        $bills = fetchRebPrePendingBills($msisdn);

        $formattedString = '';

        foreach ($bills as $key => $bill) {
            $formattedString .= '|'.($key + 1).'. '.$bill['meterNumber'];

            // @TODO: Install Carbon
            $dateParts = explode(' ', $bill['transactionDate']);

            if (count($dateParts) > 1) {
                $formattedString .= ' ('.$dateParts[0].')';
            }
        }

        return $formattedString != '' ? 'Pending Bills (Meter No.)'.$formattedString : 'No record found<400';
    }
}

if (!function_exists('retryRebPrePendingBill')) {
    /**
     * Retry REB Prepaid pending bill
     *
     * @param  string  $msisdn
     * @param  int  $input
     *
     * @return string
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function retryRebPrePendingBill(string $msisdn, int $input)
    {
        $index = $input - 1;
        $bills = fetchRebPrePendingBills($msisdn);

        if (empty($bills) || !isset($bills[$index])) {
            return 'Invalid input. Please try again.';
        }

        $bill = $bills[$index];

        $client = getGuzzelClient(getRebPreBaseUrl());

        $response = $client->post('retry', [
            'query' => [
                'transactionId' => $bill['transactionId']
            ]
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        $response = json_decode((string) $response->getBody(), true);

        return $response['response']['responseMessage'] ?? 'Retry failed!';
    }
}

if (!function_exists('primeLifeInsPost')) {
    /**
     * Submit for AKASH DTH
     *
     * @param  array  $params
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function primeLifeInsPost(array $params): string
    {
        $client = getGuzzelClient($_ENV['SERVER_PRIMELIFE_ENDPOINT']);

        $response = $client->post('/billing/insurance/pil/ussd', [
            'json' => $params
        ]);

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('getPrimeLifeParams')) {
    /**
     * Get params for AKASH DTH
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getPrimeLifeParams(string $mobileNumber, string $ssv): array
    {
        // SubscriberID Amount PIN NotificationNumber
        $matches = explode(' ', $ssv);
        //dd($matches);

        if (!empty($matches)) {
            if(count($matches) == 4){
                return [
                    'mfsAccountNo' => $mobileNumber,
                    'userNumber' => $matches[0],
                    'amount' => $matches[1],
                    'pin' => base64_encode($matches[2]),
                    'notificationNumber' => '88'.$matches[3],
                ];
            } else{
                return 'Invalid input. Please try again.';
            }
        }
        return [];
    }
}


if (!function_exists('sendTopupNewProcess')) {
    /**
     * Submit for AKASH DTH
     *
     * @param  array  $params
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function sendTopupNewProcess(array $params, $inputParam): string
    {
        $matchesInput = explode(' ', $inputParam);
        /*$client_bl = getGuzzelClient($_ENV['TOP_UP_BL_PROD']);
        $client_gp = getGuzzelClient($_ENV['TOP_UP_GP_PROD']);
        $client_rb = getGuzzelClient($_ENV['TOP_UP_RB_PROD']);
        $client_ab = getGuzzelClient($_ENV['TOP_UP_AB_PROD']);
        $client_tt = getGuzzelClient($_ENV['TOP_UP_TT_PROD']);*/

        $client_bl = getGuzzelClient($_ENV['TOP_UP_BL_STG']);
        $client_gp = getGuzzelClient($_ENV['TOP_UP_GP_STG']);
        $client_rb = getGuzzelClient($_ENV['TOP_UP_RB_STG']);
        $client_ab = getGuzzelClient($_ENV['TOP_UP_AB_STG']);
        $client_tt = getGuzzelClient($_ENV['TOP_UP_TT_STG']);

        //dd($params);


        if($matchesInput[3] == "2"){
            $response = $client_bl->post('/billing/topup/blink/ussd', [
                'json' => $params
            ]);
        } elseif ($matchesInput[3] == "1"){
            $response = $client_gp->post('/billing/topup/gp/ussd', [
                'json' => $params
            ]);
        } elseif ($matchesInput[3] == "3"){
            $response = $client_rb->post('/billing/topup/rb/ussd', [
                'json' => $params
            ]);
        } elseif ($matchesInput[3] == "4"){
            $response = $client_ab->post('/billing/topup/ab/ussd', [
                'json' => $params
            ]);
        } elseif ($matchesInput[3] == "5"){
            $response = $client_tt->post('/billing/topup/tt/ussd', [
                'json' => $params
            ]);
        }

        if ($response->getStatusCode() == 400) {
            return 'Invalid input. Please try again.';
        }

        return (string) $response->getBody();
    }
}

if (!function_exists('getTopUpParams')) {
    /**
     * Get params for AKASH DTH
     *
     * @param  string  $mobileNumber
     * @param  string  $ssv
     *
     * @return array
     */
    function getTopUpParams(string $mobileNumber, string $ssv): array
    {
        // SubscriberID Amount PIN NotificationNumber

        //echo "ssv".$ssv."-".$mobileNumber;
        $matches = explode(' ', $ssv);
        //dd($matches);
        //dd(count($matches));


        if (!empty($matches)) {
            if(count($matches) == 5){
                if($matches[3] == "2" || $matches[3] == "1"){
                    return [
                        'accountNumber' => $mobileNumber,
                        'pinTAP' => base64_encode($matches[4]),
                        'msisdn2' => substr($matches[0], -10),
                        'amount' => floatval($matches[1])
                    ];
                } else if($matches[3] == "5"){
                    return [
                        'accountNumber' => $mobileNumber,
                        'pinTAP' => base64_encode($matches[4]),
                        'msisdn2' => substr($matches[0], -11),
                        'amount' => floatval($matches[1]),
                    ];
                }
                else if($matches[3] == "3" || $matches[3] == "4"){
                    $connectionType = "";
                    if($matches[2] == "1"){
                        $connectionType = "Prepaid";
                    } else if($matches[2] == "2"){
                        $connectionType = "Postpaid";
                    }
                    return [
                        'accountNumber' => $mobileNumber,
                        'pinTAP' => base64_encode($matches[4]),
                        'msisdn2' => substr($matches[0], -11),
                        'amount' => floatval($matches[1]),
                        'connectionType' =>  $connectionType
                    ];
                }

            } else{
                return 'Invalid input. Please try again.';
            }
        }
        return [];
    }
}
