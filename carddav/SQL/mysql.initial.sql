CREATE TABLE IF NOT EXISTS `carddav_contacts` (
  `carddav_contact_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `carddav_server_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `etag` varchar(64) NOT NULL,
  `last_modified` varchar(128) NOT NULL,
  `vcard_id` varchar(64) NOT NULL,
  `vcard` longtext NOT NULL,
  `words` text,
  `firstname` varchar(128) DEFAULT NULL,
  `surname` varchar(128) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`carddav_contact_id`),
  UNIQUE KEY `carddav_server_id` (`carddav_server_id`,`user_id`,`vcard_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `carddav_server` (
  `carddav_server_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `read_only` tinyint(1) NOT NULL,
  `autocomplete` tinyint(1) NOT NULL DEFAULT '1',
  `idx` int(10) NULL,
  `edt` tinyint(1) NULL,
  PRIMARY KEY (`carddav_server_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `carddav_contactgroups` (
  `contactgroup_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `del` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(128) NOT NULL DEFAULT '',
  `addressbook` varchar(256) NOT NULL,
  PRIMARY KEY (`contactgroup_id`),
  KEY `carddav_contactgroups_user_index` (`user_id`,`del`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `carddav_contactgroupmembers` (
  `contactgroup_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY (`contactgroup_id`,`contact_id`),
  KEY `carddav_contactgroupmembers_contact_index` (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
  
CREATE TABLE IF NOT EXISTS `collected_contacts` (
 `contact_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
 `changed` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
 `del` tinyint(1) NOT NULL DEFAULT '0',
 `name` varchar(128) NOT NULL DEFAULT '',
 `email` text NOT NULL,
 `firstname` varchar(128) NOT NULL DEFAULT '',
 `surname` varchar(128) NOT NULL DEFAULT '',
 `vcard` longtext NULL,
 `words` text NULL,
 `user_id` int(10) UNSIGNED NOT NULL,
 PRIMARY KEY(`contact_id`),
 CONSTRAINT `user_id_fk_collected_contacts` FOREIGN KEY (`user_id`)
   REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
 INDEX `user_collected_contacts_index` (`user_id`,`del`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `system` (
 `name` varchar(64) NOT NULL,
 `value` mediumtext,
 PRIMARY KEY(`name`)
);

INSERT INTO `system` (name, value) VALUES ('myrc_carddav', 'initial');

ALTER TABLE `carddav_contacts`
  ADD CONSTRAINT `carddav_contacts_ibfk_1` FOREIGN KEY (`carddav_server_id`) REFERENCES `carddav_server` (`carddav_server_id`) ON DELETE CASCADE;

ALTER TABLE `carddav_server`
  ADD CONSTRAINT `carddav_server_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
  
ALTER TABLE `carddav_contactgroups`
  ADD CONSTRAINT `carddav_contactgroups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
  
ALTER TABLE `carddav_contactgroupmembers`
  ADD CONSTRAINT `carddav_contactgroupmembers_ibfk_1` FOREIGN KEY (`contactgroup_id`) REFERENCES `carddav_contactgroups` (`contactgroup_id`) ON DELETE CASCADE ON UPDATE CASCADE;