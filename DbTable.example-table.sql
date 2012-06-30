--
-- Table structure for table `Product`
--

CREATE TABLE `Product` (
  `pid` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `Shopper`
--

CREATE TABLE `Shopper` (
  `sid` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Table structure for table `Shopper_Product`
--

CREATE TABLE `Shopper_Product` (
  `spid` int(11) unsigned NOT NULL auto_increment,
  `sid` int(11) unsigned NOT NULL,
  `pid` int(11) unsigned NOT NULL,
  `add_date` datetime NOT NULL,
  PRIMARY KEY  (`spid`),
  UNIQUE KEY `sidpid` (`sid`,`pid`),
  KEY `sid` (`sid`),
  KEY `pid` (`pid`),
  CONSTRAINT `Shopper_Product_ibfk_1` FOREIGN KEY (`pid`) REFERENCES `Product` (`pid`) ON DELETE CASCADE,
  CONSTRAINT `Shopper_Product_ibfk_2` FOREIGN KEY (`sid`) REFERENCES `Shopper` (`sid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;