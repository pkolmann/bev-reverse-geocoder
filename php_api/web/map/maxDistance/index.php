<html>
<body>
<ul>
<?php

$appDir = dirname(__FILE__, 4);
require_once($appDir . DIRECTORY_SEPARATOR . "config.php");
require_once($appDir . DIRECTORY_SEPARATOR . "enum.php");

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

$query = <<<SQL
  SELECT b.municipality, b.locality, b.postcode, b.street,
          CASE
            WHEN b.subaddress != '' THEN b.house_number || b.subaddress
            ELSE b.house_number
          END AS house_number, b.house_name, b.address_type,
          ST_Distance(b.address_point, b.point) AS distance,
          ST_X(ST_Transform(point::geometry, 4326)) AS lon, ST_Y(ST_Transform(point::geometry, 4326)) AS lat,
          ST_X(ST_Transform(address_point::geometry, 4326)) AS address_lon, ST_Y(ST_Transform(address_point::geometry, 4326)) AS address_lat,
          house_attribute, house_function, CONCAT(adrcd, '-', subcd) AS adrcd
  FROM bev_addresses b
  ORDER BY distance desc
  LIMIT 2000
SQL;

$pgQuery = pg_prepare($dbconn, "addr_query", $query);
$result = pg_execute($dbconn, "addr_query", array());
if ($result === false) {
    $err = array();
    $err['status'] = 'server_error';
    $err['message'] = 'There was a problem querying the database. Please verify that the parameters you submitted ' .
                      '(especially the coordinates according to the EPSG you specified) make sense.';
    print json_encode($err);
    die;
}

while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    print "<li>";

    print "<a href=\"../?adrcd={$line['adrcd']}#18/{$line['lat']}/{$line['lon']}\" target=\"_blank\">";
    print "Dist: {$line['distance']} - ";
    print "{$line['street']} {$line['house_number']}";
    if (!empty($line['house_name'])) {
        print " ({$line['house_name']})";
    }
    print ", {$line['postcode']} {$line['municipality']}";
    if ($line['municipality'] != $line['locality']) {
        print " ({$line['locality']})";
    }

    if (array_key_exists($line['house_attribute'], $house_attribute_string)) {
        print " - " . $house_attribute_string[$line['house_attribute']];
    }
    if ($line['house_function'] != '') {
        $func = '';
        foreach (explode(',', $line['house_function']) as $val) {
            if ($func != '') {
                $func .= ', ';
            }
            if (array_key_exists($val, $house_function_string)) {
                $func .= $house_function_string[$val];
            }
        }
        print " - $func";
    }
    print "</a></li>\n";
}

print "</ul></body></html>";

