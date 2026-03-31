<?php
header("content-type: application/json");

$appDir = dirname(__FILE__, 2);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");

// Connecting, selecting database
$dbconn = pg_connect(DB_CONNECT_STRING)
   or die('{"status": "server_error", "message":"The web application was unable to connect to the database: ' . pg_last_error().'"}');

$query = <<<SQL
    SELECT gkz, string_agg(DISTINCT postcode, '|' ORDER BY postcode) AS postcodes
    FROM bev_addresses
    GROUP BY gkz
    ORDER BY gkz;
SQL;

$pgQuery = pg_prepare($dbconn, "gkzPlz_query", $query);
$gkzPlzResult = pg_execute($dbconn, "gkzPlz_query", array());

$data = [];
while ($line = pg_fetch_array($gkzPlzResult, null, PGSQL_ASSOC)) {
    $data[] = [
        'gkz' => intval($line['gkz']),
        'plz' => array_map('intval', explode('|', $line['postcodes']))
    ];
}

print json_encode($data);
