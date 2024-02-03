#!/usr/bin/env python3
import argparse
import csv
import sys
import os

import psycopg2
import urllib3


def is_float(value):
    try:
        float(value)
        return True
    except ValueError:
        return False


def main():
    parser = argparse.ArgumentParser(description="Imports the Austrian Länder, Bezirke and Gemeinde data.",
                                     formatter_class=argparse.ArgumentDefaultsHelpFormatter)
    parser.add_argument("-H", "--hostname", dest="hostname", required=False, help="Host name or IP Address")
    parser.add_argument("-d", "--database", dest="database", default="gis", help="The name of the database")
    parser.add_argument("-u", "--user", dest="user", required=False, help="The database user")
    parser.add_argument("-p", "--password", dest="password", required=False, help="The database password")
    parser.add_argument("-o", "--orte", dest="orte", required=True, help="The ORTSCHAFT.csv to read from")
    args = parser.parse_args()

    # Try to connect
    try:
        conn = psycopg2.connect(
            host=args.hostname,
            database=args.database,
            user=args.user,
            password=args.password
        )
    except Exception as e:
        print("I am unable to connect to the database (%s)." % e)
        sys.exit(1)

    cursor = conn.cursor()

    url = 'https://www.statistik.at/verzeichnis/reglisten/polbezirke.csv'
    http = urllib3.PoolManager()
    resp = http.request("GET", url)

    if resp.status != 200:
        print("Fetching url %s failed: %i", url, resp.status)
        sys.exit(-1)

    data = resp.data.decode('utf-8').split("\n")
    cr = csv.reader(data, quotechar='"', delimiter=";", quoting=csv.QUOTE_MINIMAL)

    try:
        cursor.execute("TRUNCATE bev.public.bundesland CASCADE", ())
    except Exception as e:
        print("Unable to truncate the bundesland table. (Error: %s)" % e.__str__().strip())
        sys.exit(1)

    statementBL = "INSERT INTO bev.public.bundesland VALUES(%s, %s)"
    statementBZ = "INSERT INTO bev.public.bezirk VALUES(%s, %s, %s)"
    keys = []
    for row in cr:
        if len(row) < 2:
            continue

        if not row[0][0].isdigit():
            continue

        print(row)
        try:
            if not row[0] in keys:
                cursor.execute(statementBL, (row[0], row[1],))
            cursor.execute(statementBZ, (row[4], row[3], row[0],))
        except Exception as e:
            print("Unable to insert the bundesland / bezirk. Is the format correct? (Error: %s)" % e.__str__().strip())
            sys.exit(1)

        keys.append(row[0])

    url = 'https://www.statistik.at/verzeichnis/reglisten/gemliste_knz.csv'
    resp = http.request("GET", url)

    if resp.status != 200:
        print("Fetching url %s failed: %i", url, resp.status)
        sys.exit(-1)

    data = resp.data.decode('utf-8').split("\n")
    cr = csv.reader(data, quotechar='"', delimiter=";", quoting=csv.QUOTE_MINIMAL)

    statement = "INSERT INTO bev.public.gemeinde VALUES(%s, %s, %s, %s, %s, %s)"
    for row in cr:
        if len(row) < 2:
            continue

        if not row[0][0].isdigit():
            continue

        print(row)
        try:
            cursor.execute(statement, (row[2], row[1], row[3], row[4], row[5], row[2][0:3],))
        except Exception as e:
            print("Unable to insert the gemeinde. Is the format correct? (Error: %s)" % e.__str__().strip())
            sys.exit(1)


    # Check ORTSCHAFT.csv
    if os.path.isfile(args.orte):
        try:
            statement = "TRUNCATE TABLE ortschaft"
            cursor.execute(statement)
        except Exception as e:
            print("Unable delete ortschaft data. (Error: %s)" % e.__str__().strip())
            sys.exit(1)

        # Iterate through the file and insert rows.
        with open(args.orte) as f:
            # Skip the first line as it contains only the header.
            next(f)

            statement = "INSERT INTO ortschaft VALUES(%s, %s, %s)"
            for line in csv.reader(f, quotechar='"', delimiter=";", quoting=csv.QUOTE_MINIMAL):
                try:
                    print(line)
                    cursor.execute(statement, (line[0], line[1], line[2]))
                except Exception as e:
                    print("I can't insert the row '%s'! The exception was: %s" % (line, e,))
                    conn.rollback()
                    conn.close()
                    sys.exit(1)

    # Commit all changes and close the connection.
    conn.commit()
    conn.close()


if __name__ == "__main__":
    main()
