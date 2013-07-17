--
-- Table: carddav_contacts
--
CREATE TABLE IF NOT EXISTS "carddav_contacts" (
  "carddav_contact_id" serial NOT NULL,
  "carddav_server_id" integer NOT NULL,
  "user_id" integer NOT NULL,
  "etag" character varying(64) NOT NULL,
  "last_modified" character varying(128) NOT NULL,
  "vcard_id" character varying(64) NOT NULL,
  "vcard" text NOT NULL,
  "words" text,
  "firstname" character varying(128) DEFAULT NULL,
  "surname" character varying(128) DEFAULT NULL,
  "name" character varying(255) DEFAULT NULL,
  "email" character varying(255) DEFAULT NULL,
  PRIMARY KEY ("carddav_contact_id"),
  CONSTRAINT "carddav_server_id" UNIQUE ("carddav_server_id", "user_id", "vcard_id")
);

--
-- Table: carddav_server
--
CREATE TABLE IF NOT EXISTS "carddav_server" (
  "carddav_server_id" serial NOT NULL,
  "user_id" integer NOT NULL,
  "url" character varying(255) NOT NULL,
  "username" character varying(128) NOT NULL,
  "password" character varying(128) NOT NULL,
  "label" character varying(128) NOT NULL,
  "read_only" smallint NOT NULL,
  "autocomplete" smallint DEFAULT 1 NOT NULL,
  "idx" integer,
  "edt" smallint,
  PRIMARY KEY ("carddav_server_id")
);

--
-- Table: carddav_contactgroups
--
CREATE TABLE IF NOT EXISTS "carddav_contactgroups" (
  "contactgroup_id" serial NOT NULL,
  "user_id" integer NOT NULL,
  "changed" timestamp DEFAULT '1000-01-01 00:00:00' NOT NULL,
  "del" smallint DEFAULT 0 NOT NULL,
  "name" character varying(128) DEFAULT '' NOT NULL,
  "addressbook" character varying(256) NOT NULL,
  PRIMARY KEY ("contactgroup_id")
);

--
-- Table: carddav_contactgroupmembers
--
CREATE TABLE IF NOT EXISTS "carddav_contactgroupmembers" (
  "contactgroup_id" integer NOT NULL,
  "contact_id" integer NOT NULL,
  "created" timestamp DEFAULT '1000-01-01 00:00:00' NOT NULL,
  PRIMARY KEY ("contactgroup_id", "contact_id")
);

--
-- Table: collected_contacts
--
CREATE TABLE IF NOT EXISTS "collected_contacts" (
  "contact_id" serial NOT NULL,
  "changed" timestamp DEFAULT '1000-01-01 00:00:00' NOT NULL,
  "del" smallint DEFAULT 0 NOT NULL,
  "name" character varying(128) DEFAULT '' NOT NULL,
  "email" text NOT NULL,
  "firstname" character varying(128) DEFAULT '' NOT NULL,
  "surname" character varying(128) DEFAULT '' NOT NULL,
  "vcard" text,
  "words" text,
  "user_id" integer NOT NULL,
  PRIMARY KEY ("contact_id")
);

CREATE TABLE IF NOT EXISTS "system" (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);

INSERT INTO "system" (name, value) VALUES ('myrc_carddav', 'initial');

--
-- Index Definitions
--

CREATE INDEX "carddav_contacts_user_id" on "carddav_contacts" ("user_id");
CREATE INDEX "carddav_server_user_id" on "carddav_server" ("user_id");
CREATE INDEX "carddav_contactgroups_user_index" on "carddav_contactgroups" ("user_id", "del");
CREATE INDEX "carddav_contactgroupmembers_contact_index" on "carddav_contactgroupmembers" ("contact_id");
CREATE INDEX "user_collected_contacts_index" on "collected_contacts" ("user_id", "del");
--
-- Foreign Key Definitions
--

ALTER TABLE "carddav_contacts" ADD CONSTRAINT "carddav_contacts_ibfk_1" FOREIGN KEY ("carddav_server_id")
  REFERENCES "carddav_server" ("carddav_server_id") ON DELETE CASCADE DEFERRABLE;

ALTER TABLE "carddav_server" ADD CONSTRAINT "carddav_server_ibfk_1" FOREIGN KEY ("user_id")
  REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE;

ALTER TABLE "carddav_contactgroups" ADD CONSTRAINT "carddav_contactgroups_ibfk_1" FOREIGN KEY ("user_id")
  REFERENCES "users" ("user_id") ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE;

ALTER TABLE "carddav_contactgroupmembers" ADD CONSTRAINT "carddav_contactgroupmembers_ibfk_1" FOREIGN KEY ("contactgroup_id")
  REFERENCES "carddav_contactgroups" ("contactgroup_id") ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE;

ALTER TABLE "collected_contacts" ADD CONSTRAINT "user_id_fk_collected_contacts" FOREIGN KEY ("user_id")
  REFERENCES "users" ("user_id") ON DELETE CASCADE ON UPDATE CASCADE DEFERRABLE;

