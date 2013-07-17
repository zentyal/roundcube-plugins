CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` text,
  `recurrence_id` int(10) DEFAULT NULL,
  `exdates` text,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `start` int(11) NOT NULL DEFAULT '0',
  `end` int(11) NOT NULL DEFAULT '0',
  `expires` int(11) NOT NULL DEFAULT '0',
  `rr` varchar(1) DEFAULT NULL,
  `recurring` text NOT NULL,
  `occurrences` int(11) DEFAULT '0',
  `byday` text,
  `bymonth` text,
  `bymonthday` text,
  `summary` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL DEFAULT '',
  `categories` varchar(255) NOT NULL DEFAULT '',
  `group` text,
  `caldav` text,
  `url` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `del` int(1) NOT NULL DEFAULT '0',
  `reminder` int(10) DEFAULT NULL,
  `reminderservice` text,
  `remindermailto` text,
  `remindersent` int(10) DEFAULT NULL,
  `notified` int(1) NOT NULL DEFAULT '0',
  `client` text,
  PRIMARY KEY (`event_id`),
  KEY `user_id_fk_events` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `events_cache` (
  `event_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` text CHARACTER SET utf8,
  `recurrence_id` int(10) DEFAULT NULL,
  `exdates` text CHARACTER SET utf8,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `start` int(11) NOT NULL DEFAULT '0',
  `end` int(11) NOT NULL DEFAULT '0',
  `expires` int(11) NOT NULL DEFAULT '0',
  `rr` varchar(1) CHARACTER SET utf8 DEFAULT NULL,
  `recurring` text CHARACTER SET utf8 NOT NULL,
  `occurrences` int(11) DEFAULT '0',
  `byday` text CHARACTER SET utf8,
  `bymonth` text CHARACTER SET utf8,
  `bymonthday` text CHARACTER SET utf8,
  `summary` varchar(255) CHARACTER SET utf8 NOT NULL,
  `description` text CHARACTER SET utf8 NOT NULL,
  `location` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `categories` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `group` text CHARACTER SET utf8,
  `caldav` text CHARACTER SET utf8,
  `url` text COLLATE utf8_unicode_ci,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `del` int(1) NOT NULL DEFAULT '0',
  `reminder` int(10) DEFAULT NULL,
  `reminderservice` text CHARACTER SET utf8,
  `remindermailto` text CHARACTER SET utf8,
  `remindersent` int(10) DEFAULT NULL,
  `notified` int(1) NOT NULL DEFAULT '0',
  `client` text CHARACTER SET utf8,
  PRIMARY KEY (`event_id`),
  KEY `user_id_fk_events_cache` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `events_caldav` (
  `event_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` text,
  `recurrence_id` int(10) DEFAULT NULL,
  `exdates` text,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `start` int(11) DEFAULT '0',
  `end` int(11) DEFAULT '0',
  `expires` int(11) DEFAULT '0',
  `rr` varchar(1) DEFAULT NULL,
  `recurring` text NOT NULL,
  `occurrences` int(11) DEFAULT '0',
  `byday` text,
  `bymonth` text,
  `bymonthday` text,
  `summary` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) NOT NULL DEFAULT '',
  `categories` varchar(255) NOT NULL DEFAULT '',
  `group` text,
  `caldav` text,
  `url` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `del` int(1) NOT NULL DEFAULT '0',
  `reminder` int(10) DEFAULT NULL,
  `reminderservice` text,
  `remindermailto` text,
  `remindersent` int(10) DEFAULT NULL,
  `notified` int(1) NOT NULL DEFAULT '0',
  `client` text,
  PRIMARY KEY (`event_id`),
  KEY `user_id_fk_events_caldav` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `reminders` (
  `reminder_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `events` int(10) unsigned DEFAULT NULL,
  `cache` int(10) unsigned DEFAULT NULL,
  `caldav` int(10) unsigned DEFAULT NULL,
  `type` text,
  `props` text,
  `runtime` int(11) NOT NULL,
  PRIMARY KEY (`reminder_id`),
  KEY `reminders_ibfk_1` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2299 ;

CREATE TABLE IF NOT EXISTS `system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
);

INSERT INTO `system` (name, value) VALUES ('myrc_calendar', 'initial');

ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
  
ALTER TABLE `events_cache`
  ADD CONSTRAINT `events_cache_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
  
ALTER TABLE `events_caldav`
  ADD CONSTRAINT `events_caldav_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
