CREATE TABLE IF NOT EXISTS `associations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `type` varchar(32) NOT NULL,
  `child_id` int(11) NOT NULL,
  `ring` int(11) NOT NULL,
  `creation` datetime NOT NULL,
  `modification` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `child_id` (`child_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

CREATE TABLE IF NOT EXISTS `association_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `association_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `value` text NOT NULL,
  `ring` int(11) NOT NULL,
  `creation` datetime NOT NULL,
  `modification` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `association_id` (`association_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

CREATE TABLE IF NOT EXISTS `objects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL,
  `value` varchar(255) NOT NULL,
  `ring` int(11) NOT NULL,
  `creation` datetime NOT NULL,
  `modification` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

CREATE TABLE IF NOT EXISTS `object_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `object_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `value` text NOT NULL,
  `ring` int(11) NOT NULL,
  `creation` datetime NOT NULL,
  `modification` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;
