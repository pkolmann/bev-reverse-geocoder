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
  SELECT okz, ortsname AS name
  FROM ortschaft
  WHERE gkz = \$1
  ORDER BY 2
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", array($_GET['gkz']));

$data = [];
while ($line = pg_fetch_array($dateResult, null, PGSQL_ASSOC)) {
    $data[] = $line;
}

print json_encode($data);
