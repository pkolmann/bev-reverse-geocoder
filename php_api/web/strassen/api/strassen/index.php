<?php
header("content-type: application/json");

$appDir = dirname(__FILE__, 5);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");

// Connecting, selecting database
$dbconn = pg_connect(DB_CONNECT_STRING)
   or die('{"status": "server_error", "message":"The web application was unable to connect to the database: ' . pg_last_error().'"}');

if (!array_key_exists('gkz', $_GET)) {
    print json_encode([
        "error" => true,
        "errorString" => "No gkz received!"
    ]);
    die();
}

$query = <<<SQL
    SELECT municipality, locality, street
    FROM bev_addresses b
    WHERE gkz = \$1
    GROUP BY 1, 2, 3
    ORDER BY 1, 2, 3
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", array($_GET['gkz']));

$data = [];
$kgs = [];
while ($line = pg_fetch_array($dateResult, null, PGSQL_ASSOC)) {
    if (!array_key_exists($line['locality'], $kgs)) {
        $kgs[$line['locality']] = count($kgs);
        $data[$kgs[$line['locality']]] = [
            'name' => $line['locality'],
            'kgkz' => $kgs[$line['locality']],
            'streets' => []
        ];
    }

    $data[$kgs[$line['locality']]]['streets'][] = $line['street'];
}

print json_encode($data);
