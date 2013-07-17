CREATE TABLE IF NOT EXISTS 'carddav_server' (
  'carddav_server_id' INTEGER NOT NULL PRIMARY KEY ASC,
  'user_id' int(10) NOT NULL,
  'url' varchar(255) NOT NULL,
  'username' varchar(128) NOT NULL,
  'password' varchar(128) NOT NULL,
  'label' varchar(128) NOT NULL,
  'read_only' tinyint(1) NOT NULL,
  'autocomplete' tinyint(1) NOT NULL DEFAULT '1',
  'idx' int(10) NULL,
  'edt' tinyint(1) NULL,
  CONSTRAINT 'carddav_server_ibfk_1' FOREIGN KEY ('user_id') REFERENCES 'users' ('user_id') ON DELETE CASCADE
);

CREATE TABLE 'carddav_contacts' (
  'carddav_contact_id' INTEGER NOT NULL PRIMARY KEY ASC,
  'carddav_server_id' int(10) NOT NULL,
  'user_id' int(10) NOT NULL, 'etag' varchar(64) NOT NULL,
  'last_modified' varchar(128) NOT NULL,
  'vcard_id' varchar(64) NOT NULL,
  'vcard' longtext NOT NULL,
  'words' text,
  'firstname' varchar(128) DEFAULT NULL,
  'surname' varchar(128) DEFAULT NULL,
  'name' varchar(255) DEFAULT NULL,
  'email' varchar(255) DEFAULT NULL,
  CONSTRAINT 'carddav_contacts_ibfk_1' FOREIGN KEY ('carddav_server_id') REFERENCES 'carddav_server' ('carddav_server_id') ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS 'carddav_contactgroups' (
  'contactgroup_id' INTEGER NOT NULL PRIMARY KEY ASC,
  'user_id' int(10) NOT NULL,
  'changed' datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  'del' tinyint(1) NOT NULL DEFAULT '0',
  'name' varchar(128) NOT NULL DEFAULT '',
  'addressbook' varchar(256) NOT NULL,
  CONSTRAINT 'carddav_contactgroups_ibfk_1' FOREIGN KEY ('user_id') REFERENCES 'users' ('user_id') ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS 'carddav_contactgroupmembers' (
  'contactgroup_id' int(10) NOT NULL,
  'contact_id' int(10) NOT NULL,
  'created' datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (contactgroup_id, contact_id),
  CONSTRAINT 'carddav_contactgroupmembers_ibfk_1' FOREIGN KEY ('contactgroup_id') REFERENCES 'carddav_contactgroups' ('contactgroup_id') ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS collected_contacts (
  contact_id integer NOT NULL PRIMARY KEY,
  user_id integer NOT NULL,
  changed datetime NOT NULL default '0000-00-00 00:00:00',
  del tinyint NOT NULL default '0',
  name varchar(128) NOT NULL default '',
  email text NOT NULL default '',
  firstname varchar(128) NOT NULL default '',
  surname varchar(128) NOT NULL default '',
  vcard text NOT NULL default '',
  words text NOT NULL default '',
  CONSTRAINT 'user_id_fk_collected_contacts' FOREIGN KEY ('user_id') REFERENCES 'users'('user_id') ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS 'system' (
  name varchar(64) NOT NULL PRIMARY KEY,
  value text NOT NULL
);

INSERT INTO system (name, value) VALUES ('myrc_carddav', 'initial');