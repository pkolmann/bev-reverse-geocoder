-- Drop the tables first if it exists. This allows copy-pasting this entire file in any case.
DROP TABLE IF EXISTS bev_date;
DROP TABLE IF EXISTS bev_addresses;

-- Create the date table.
CREATE TABLE bev_date
(
  date DATE NOT NULL
);

-- Create the address table.
CREATE TABLE bev_addresses
(
  municipality character varying NOT NULL,
  locality character varying NOT NULL,
  postcode character varying NOT NULL,
  street character varying NOT NULL,
  house_number character varying,
  subaddress character varying,
  house_name character varying,
  address_type character varying,
  municipality_has_ambiguous_addresses boolean NOT NULL DEFAULT FALSE,
  house_attribute character varying,
  point geography(Point,4326) NOT NULL
);

-- Create an index on the 'point' column to speed up reverse geocoding.
CREATE INDEX bev_addresses_point ON bev_addresses USING GIST (point);

-- Other indices.
CREATE INDEX bev_addresses_municipality ON bev_addresses(municipality);
CREATE INDEX bev_addresses_locality ON bev_addresses(locality);
CREATE INDEX bev_addresses_street ON bev_addresses(street);
CREATE INDEX bev_addresses_house_number ON bev_addresses(house_number);

comment on column bev_addresses.house_attribute is "Überwiegende Eigenschaft dieses Objektes:
01: Gebäude mit einer Wohnung
02: Gebäude mit zwei oder mehr Wohnungen
03: Wohngebäude für Gemeinschaften
04: Hotels und ähnliche Gebäude
05: Bürogebäude
06: Groß- und Einzelhandelsgebäude
07: Gebäude des Verkehrs- und Nachrichtenwesens
08: Industrie- und Lagergebäude
09: Gebäude für Kultur- und Freizeitzwecke sowie das Bildungs- und Gesundheitswesen";

