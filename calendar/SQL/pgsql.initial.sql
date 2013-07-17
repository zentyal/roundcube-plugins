CREATE TABLE events (
   event_id serial NOT NULL PRIMARY KEY,
   uid text,
   recurrence_id integer,
   exdates text,
   user_id integer NOT NULL
     REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "start" integer NOT NULL DEFAULT 0,
   "end" integer NOT NULL DEFAULT 0,
   expires integer NOT NULL DEFAULT 0,
   rr varchar(1) NOT NULL DEFAULT 0,
   recurring text NOT NULL,
   occurrences integer DEFAULT 0,
   byday text,
   bymonth text,
   bymonthday text,
   summary varchar(255) NOT NULL,
   description text NOT NULL,
   location varchar(255) NOT NULL DEFAULT '',
   categories varchar(255) NOT NULL DEFAULT '',
   "group" text,
   caldav text,
   url text,
   "timestamp" text,
   del smallint NOT NULL DEFAULT 0,
   reminder integer DEFAULT NULL,
   reminderservice text,
   remindermailto text,
   remindersent integer DEFAULT NULL,
   notified smallint NOT NULL DEFAULT 0,
   client text
);

CREATE TABLE events_cache (
   event_id serial NOT NULL PRIMARY KEY,
   uid text,
   recurrence_id integer,
   exdates text,
   user_id integer NOT NULL
     REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "start" integer NOT NULL DEFAULT 0,
   "end" integer NOT NULL DEFAULT 0,
   expires integer NOT NULL DEFAULT 0,
   rr varchar(1) NOT NULL DEFAULT 0,
   recurring text NOT NULL,
   occurrences integer DEFAULT 0,
   byday text,
   bymonth text,
   bymonthday text,
   summary varchar(255) NOT NULL,
   description text NOT NULL,
   location varchar(255) NOT NULL DEFAULT '',
   categories varchar(255) NOT NULL DEFAULT '',
   "group" text,
   caldav text,
   url text,
   "timestamp" text,
   del smallint NOT NULL DEFAULT 0,
   reminder integer DEFAULT NULL,
   reminderservice text,
   remindermailto text,
   remindersent integer DEFAULT NULL,
   notified smallint NOT NULL DEFAULT 0,
   client text
);

CREATE TABLE events_caldav (
   event_id serial NOT NULL PRIMARY KEY,
   uid text,
   recurrence_id integer,
   exdates text,
   user_id integer NOT NULL
  REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "start" integer NOT NULL DEFAULT 0,
   "end" integer NOT NULL DEFAULT 0,
   expires integer NOT NULL DEFAULT 0,
   rr varchar(1) NOT NULL DEFAULT 0,
   recurring text NOT NULL,
   occurrences integer DEFAULT 0,
   byday text,
   bymonth text,
   bymonthday text,
   summary varchar(255) NOT NULL,
   description text NOT NULL,
   location varchar(255) NOT NULL DEFAULT '',
   categories varchar(255) NOT NULL DEFAULT '',
   "group" text,
   caldav text,
   url text,
   "timestamp" text,
   del smallint NOT NULL DEFAULT 0,
   reminder integer DEFAULT NULL,
   reminderservice text,
   remindermailto text,
   remindersent integer DEFAULT NULL,
   notified smallint NOT NULL DEFAULT 0,
   client text
);

CREATE TABLE reminders (
    reminder_id serial NOT NULL PRIMARY KEY,
    user_id integer NOT NULL
  REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    events integer NOT NULL DEFAULT 0,
    cache integer NOT NULL DEFAULT 0,
    caldav integer NOT NULL DEFAULT 0,
    "type" text,
    props text,
    runtime integer NOT NULL
);

CREATE TABLE IF NOT EXISTS "system" (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);

INSERT INTO "system" (name, value) VALUES ('myrc_calendar', 'initial');

CREATE INDEX events_user_id ON events (user_id, del);
CREATE INDEX events_cache_user_id ON events_cache (user_id, del);
CREATE INDEX events_caldav_user_id ON events_caldav (user_id, del);