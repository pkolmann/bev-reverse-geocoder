<?php
header("content-type: application/json");

$default_distance = 30;
$max_distance = 100;
$default_limit = 5;
$max_limit = 10;


$appDir = dirname(__FILE__, 2);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");

// Connecting, selecting database
$dbconn = pg_connect(DB_CONNECT_STRING)
   or die('{"status": "server_error", "message":"The web application was unable to connect to the database: ' . pg_last_error().'"}');

$query = <<<SQL
  SELECT date
  FROM bev_date
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", array());

$line = pg_fetch_array($dateResult, null, PGSQL_ASSOC);
$date = $line['date'];


# Get the HTTP GET parameters and use default values where it makes sense.

$plz = null;
if (array_key_exists('plz', $_GET)) {
    $plz = $_GET['plz'];
}

if (is_null($plz)) {
    $err = array();
    $err['status'] = 'bad_request';
    $err['message'] = 'No PLZ given.';
    print json_encode($err);
    die;
}


$query = <<<SQL
    SELECT ST_X(x.center) AS x, ST_Y(x.center) AS y
    FROM (
          SELECT ST_CENTROID(ST_MAKELINE(ARRAY(SELECT point::geometry
                                               FROM bev.public.bev_addresses
                                               WHERE postcode = \$1
              ))) AS center
      ) x
SQL;

$pgQuery = pg_prepare($dbconn, "addr_query", $query);
$result = pg_execute($dbconn, "addr_query", array($plz));

if ($result === false) {
    $err = array();
    $err['status'] = 'server_error';
    $err['message'] = 'There was a problem querying the database. Please verify that the parameters you submitted.';
    print json_encode($err);
    die;
}

$geos = array();
while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $geos[] = $line;
}

$res = array();
$res['status'] = 'ok';
$dateTime = strtotime($date);
$res['copyright'] = '© Österreichisches Adressregister 2017, N 23806/2017 (Stichtagsdaten vom ' .
    date('d. m. Y', $dateTime) . ')';
$res['address_date'] = $date;
$res['results'] = $geos;

print json_encode($res);
