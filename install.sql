CREATE TABLE IF NOT EXISTS `PREFIX_product_comment` (
  `id_product_comment` int(10) unsigned NOT NULL auto_increment,
  `id_product` int(10) unsigned NOT NULL,
  `id_customer` int(10) unsigned NOT NULL,
  `id_guest` int(10) unsigned NULL,
  `title` varchar(64) NULL,
  `content` text NOT NULL,
  `customer_name` varchar(64) NULL,
  `grade` float unsigned NOT NULL,
  `validate` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id_product_comment`),
  KEY `id_product` (`id_product`),
  KEY `id_customer` (`id_customer`),
  KEY `id_guest` (`id_guest`)
) ENGINE=ENGINE_TYPE  DEFAULT CHARSET=utf8;



CREATE TABLE IF NOT EXISTS `PREFIX_product_comment_grade` (
  `id_product_comment` int(10) unsigned NOT NULL,
  `grade` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_product_comment`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;


