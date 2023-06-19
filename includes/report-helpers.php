<?php

if (!function_exists('getSessionTable')) {
    /**
     * Get the name of the session log table for generating USSD report
     *
     * @param  string  $carrier
     *
     * @return string
     */
    function getSessionTable(string $carrier): string
    {
        return "_sessions_{$carrier}";
    }
}

if (!function_exists('initSession')) {
    /**
     * Initiate the session log
     *
     * @param  string  $carrier
     * @param  string  $msisdn
     * @param  string  $sessionId
     *
     * @return void
     */
    function initSession(string $carrier, string $msisdn, string $sessionId): void
    {
        global $mysqli;

        $table = getSessionTable($carrier);
        $msisdn = '88'.substr($msisdn, -11);

        $result = $mysqli->query("SELECT count(*) FROM {$table} WHERE session_id = '{$sessionId}';");
        $rows = $result->fetch_row();

        // Insert if doesn't exist
        if ($rows[0] == 0) {
            $mysqli->query("INSERT INTO {$table} SET session_id = '{$sessionId}', msisdn = '{$msisdn}', user_type = 0, is_recharge_attempt = 0, is_invoiceable = 1, is_rg_candidate = 0, row_id = null, response = null, created_at = now();");
        }
    }
}

if (!function_exists('getUserType')) {
    /**
     * Get the user type by service string
     *
     * @param  string  $service
     *
     * @return int
     */
    function getUserType(string $service): int
    {
        if ($service == 'TBL-AGENT') {
            return 1;
        }

        if ($service == 'TBL-USER') {
            return 2;
        }

        if ($service == 'TBL-OTHER') {
            return -1;
        }

        // Default or unknown
        return 0;
    }
}

if (!function_exists('setUserType')) {
    /**
     * Set the user type
     *
     * @param  string  $carrier
     * @param  string  $sessionId
     * @param  string  $service
     *
     * @return void
     */
    function setUserType(string $carrier, string $sessionId, string $service): void
    {
        global $mysqli;

        $table = getSessionTable($carrier);
        $userType = getUserType($service);

        $sql = "UPDATE {$table} SET user_type = $userType, updated_at = now()";

        // Set is_invoiceable to 0 if the user is unregistered
        if ($userType == -1) {
            $sql .= ", is_invoiceable = 0";
        }

        $sql .= " WHERE session_id = '{$sessionId}';";

        $mysqli->query($sql);
    }
}

if (!function_exists('updateIfRechargeAttempt')) {
    /**
     * Set is_recharge_attempt to 1 and is_invoiceable to 0 if applicable
     *
     * @param  string  $carrier
     * @param  string  $sessionId
     * @param  int  $id
     *
     * @return bool
     */
    function updateIfRechargeAttempt(string $carrier, string $sessionId, int $id): bool
    {
        if (!in_array($id, [
            578, // Agent: Top Up
            589, // User: Top Up
        ])) {
            return false;
        }

        global $mysqli;

        $table = getSessionTable($carrier);

        $mysqli->query("UPDATE {$table} SET is_recharge_attempt = 1, is_invoiceable = 0, updated_at = now() WHERE session_id = '{$sessionId}';");

        return true;
    }
}

if (!function_exists('getAgentRgCandidateIds')) {
    /**
     * Get revenue generating transactions IDs for agent
     *
     * @return int[]
     */
    function getAgentRgCandidateIds(): array
    {
        // Query to figure out the IDs
        // SELECT id FROM `appmenu` WHERE (`SERVICE` = 'TBL-AGENT') AND (`NID` LIKE '16%') AND (`TYPE` = 'POST');

        return [
            12, // Agent: Cash In
            36, // Agent: Utility
            40, // Agent: Utility
            44, // Agent: Utility
            51, // Agent: Utility
            195, // Agent: Utility
            222, // Agent: Utility
            234, // Agent: Utility
            258, // Agent: Utility
            617, // Agent: Utility
            644, // Agent: Utility
            663, // Agent: Utility
            668, // Agent: Utility
            693, // Agent: Utility
            698, // Agent: Utility
            704, // Agent: Utility
            708, // Agent: Utility
            718, // Agent: Utility
            741, // Agent: Utility
            763, // Agent: Utility
            769, // Agent: Utility
            58, // Agent: Tution Fees
            63, // Agent: Tution Fees
            69, // Agent: Tution Fees
            75, // Agent: Tution Fees
            81, // Agent: Tution Fees
            87, // Agent: Tution Fees
            93, // Agent: Tution Fees
            206, // Agent: Tution Fees
            246, // Agent: Tution Fees
            440, // Agent: Tution Fees
            446, // Agent: Tution Fees
            489, // Agent: Tution Fees
            494, // Agent: Tution Fees
            499, // Agent: Tution Fees
            504, // Agent: Tution Fees
            509, // Agent: Tution Fees
            520, // Agent: Tution Fees
            753, // Agent: School Banking
        ];
    }
}

if (!function_exists('getUserRgCandidateIds')) {
    /**
     * Get revenue generating transactions IDs for user
     *
     * @return int[]
     */
    function getUserRgCandidateIds(): array
    {
        // Query to figure out the IDs
        // SELECT id FROM `appmenu` WHERE (`SERVICE` = 'TBL-USER') AND (`NID` LIKE '14%') AND (`TYPE` = 'POST');

        return [
            106, // User: Cash Out
            110, // User: P2P
            122, // User: Utility
            126, // User: Utility
            130, // User: Utility
            137, // User: Utility
            200, // User: Utility
            217, // User: Utility
            228, // User: Utility
            252, // User: Utility
            626, // User: Utility
            639, // User: Utility
            652, // User: Utility
            657, // User: Utility
            674, // User: Utility
            679, // User: Utility
            685, // User: Utility
            689, // User: Utility
            713, // User: Utility
            736, // User: Utility
            758, // User: Utility
            766, // User: Utility
            143, // User: Tution Fees
            149, // User: Tution Fees
            155, // User: Tution Fees
            161, // User: Tution Fees
            167, // User: Tution Fees
            173, // User: Tution Fees
            295, // User: Tution Fees
            425, // User: Tution Fees
            431, // User: Tution Fees
            454, // User: Tution Fees
            461, // User: Tution Fees
            468, // User: Tution Fees
            475, // User: Tution Fees
            482, // User: Tution Fees
            527, // User: Tution Fees
            565, // User: Tution Fees
            571, // User: Tution Fees
            747, // User: School Banking
        ];
    }
}

if (!function_exists('setRgCandidateWithResponse')) {
    /**
     * Set is_rg_candidate, is_invoiceable to 1 and response to message if applicable
     *
     * @param  string  $carrier
     * @param  string  $sessionId
     * @param  int  $id
     * @param  string  $response
     *
     * @return void
     */
    function setRgCandidateWithResponse(string $carrier, string $sessionId, int $id, string $response): void
    {
        global $mysqli;

        if (in_array($id, array_merge(getAgentRgCandidateIds(), getUserRgCandidateIds()))) {
            // $mysqli->query("UPDATE _sessions_{$carrier} SET is_rg_candidate = 0, is_invoiceable = 1 WHERE session_id = '{$sessionId}';");
            $table = getSessionTable($carrier);
            $response = substr($response, 0, 512);

            $mysqli->query("UPDATE {$table} SET is_rg_candidate = 1, is_invoiceable = 1, row_id = {$id}, response = '{$response}', updated_at = now() WHERE session_id = '{$sessionId}';");
        }
    }
}
