-- Drop the tables first if it exists. This allows copy-pasting this entire file in any case.
DROP TABLE IF EXISTS bev_date;
DROP TABLE IF EXISTS bev_addresses;
DROP TABLE IF EXISTS ort;
DROP TABLE IF EXISTS bezirk;
DROP TABLE IF EXISTS bundesland;

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
  house_function character varying,
  municipality_has_ambiguous_addresses boolean NOT NULL DEFAULT FALSE,
  house_attribute character varying,
  gkz character varying,
  adrcd character varying,
  subcd character varying,
  point geography(Point,4326) NOT NULL,
  address_point geography(Point,4326) NOT NULL
);

-- Create an index on the 'point' column to speed up reverse geocoding.
CREATE INDEX bev_addresses_point ON bev_addresses USING GIST (point);
CREATE INDEX bev_addresses_address_point ON bev_addresses USING GIST (address_point);

-- Other indices.
CREATE INDEX bev_addresses_municipality ON bev_addresses(municipality);
CREATE INDEX bev_addresses_locality ON bev_addresses(locality);
CREATE INDEX bev_addresses_street ON bev_addresses(street);
CREATE INDEX bev_addresses_house_number ON bev_addresses(house_number);

comment on column bev_addresses.house_attribute is 'Überwiegende Eigenschaft dieses Objektes:
01: Gebäude mit einer Wohnung
02: Gebäude mit zwei oder mehr Wohnungen
03: Wohngebäude für Gemeinschaften
04: Hotels und ähnliche Gebäude
05: Bürogebäude
06: Groß- und Einzelhandelsgebäude
07: Gebäude des Verkehrs- und Nachrichtenwesens
08: Industrie- und Lagergebäude
09: Gebäude für Kultur- und Freizeitzwecke sowie das Bildungs- und Gesundheitswesen';

comment on column bev_addresses.house_function is 'zugehörige Funktionskennziffer:
00 nicht bearbeitet
01 Apotheke
02 Einsatzzentrale/Rettungsdienst
03 Polizei
04 Feuerwehr
05 Gemeindeamt
06 Krankenanstalt
07 Tankstelle
08 Schule
99 zur Zeit keine Funktion zugeordnet
Mehrfachangaben sind möglich';

create table bundesland
(
    blkz       integer not null
        constraint bundesland_pk
            primary key,
    bundesland varchar not null
);

create table bezirk
(
    bzkz   integer not null
        constraint bezirk_pk
            primary key,
    bezirk varchar not null,
    blkz   integer not null
        constraint bezirk_bundesland_blkz_fk
            references bundesland
            on delete restrict
);

create table ort
(
    gkz         integer not null,
    name        varchar not null,
    status      varchar,
    amt_plz     integer,
    weitere_plz varchar,
    bzkz        integer not null
        constraint orte_bezirk_bzkz_fk
            references bezirk
            on delete restrict,
    constraint orte_pk
        primary key (name, gkz)
);
create index orte_name_index
    on ort (name);
create index ort_bzkz_index
    on ort (bzkz);

GRANT SELECT, INSERT, UPDATE, DELETE, TRUNCATE ON ALL TABLES IN SCHEMA public to bev;
GRANT SELECT ON ALL TABLES IN SCHEMA public to bev_read;

