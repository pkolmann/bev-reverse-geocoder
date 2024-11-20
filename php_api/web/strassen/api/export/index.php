<?php
header("content-type: application/json");

$appDir = dirname(__FILE__, 5);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");
require_once($appDir . DIRECTORY_SEPARATOR . "enum.php");

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

$gkzs = explode(',', $_GET['gkz']);

if (count($gkzs) > 10) {
    print json_encode([
        'error' => true,
        'errorStr' => 'Too many GKZs specified!'
    ]);
    die();
}

foreach ($gkzs as $key => $value) {
    $gkzs[$key] = trim($value);
}

$params = '';
for ($i = 1; $i <= count($gkzs); $i++) {
    if ($params != '') $params .= ', ';
    $params .= "\$$i";
}
$query = <<<SQL
  SELECT b.municipality, b.gkz, b.locality, b.okz, b.street,
          CASE
            WHEN b.subaddress != '' THEN b.house_number || b.subaddress
            ELSE b.house_number
          END AS house_number, house_attribute,
          ST_X(ST_Transform(point::geometry, 4326)) AS lon, ST_Y(ST_Transform(point::geometry, 4326)) AS lat,
          ST_X(ST_Transform(address_point::geometry, 4326)) AS address_lon, ST_Y(ST_Transform(address_point::geometry, 4326)) AS address_lat
    FROM bev_addresses b
    WHERE gkz IN ($params)
    ORDER BY 1, 3, 5, 6
SQL;

$pgQuery = pg_prepare($dbconn, "date_query", $query);
$dateResult = pg_execute($dbconn, "date_query", $gkzs);

$data = [];
while ($line = pg_fetch_array($dateResult, null, PGSQL_ASSOC)) {
    if (!array_key_exists($line['municipality'], $data)) {
        $data[$line['municipality']] = [
            'id' => $line['gkz'],
            'data' => []
        ];
    }

    if (!array_key_exists($line['locality'], $data[$line['municipality']]['data'])) {
        $data[$line['municipality']]['data'][$line['locality']] = [
            'id' => $line['okz'],
            'data' => []
        ];
    }

    if (!array_key_exists($line['street'], $data[$line['municipality']]['data'][$line['locality']]['data'])) {
        $data[$line['municipality']]['data'][$line['locality']]['data'][$line['street']] = [];
    }

    $data[$line['municipality']]['data'][$line['locality']]['data'][$line['street']][$line['house_number']] = [
        'gebaeude' => [$line['lat'], $line['lon']],
        'adresse' => [$line['address_lat'], $line['address_lon']],
        'house_attribute' => $line['house_attribute']
    ];

    if (array_key_exists($line['house_attribute'], $house_attribute_string)) {
        $data[$line['municipality']]['data'][$line['locality']]['data'][$line['street']]
            [$line['house_number']]['house_attribute_string'] = $house_attribute_string[$line['house_attribute']];
    }

}

print json_encode($data);
