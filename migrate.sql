create table bbs_migrate_eef(
`uid` int(11) DEFAULT NULL,
`eefocus_uid` int(11) DEFAULT NULL,
`from` varchar(10) not null default '',
PRIMARY KEY (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
create table bbs_migrate_eef_thread(
`tid` int(11) DEFAULT NULL,
`ori_tid` int(11) DEFAULT NULL,
`from` varchar(10) not null default '',
PRIMARY KEY (`tid`),
KEY `ori_tid` (`ori_tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

create table bbs_migrate_eef_attach(
`aid` int(11) DEFAULT NULL,
`ori_aid` int(11) DEFAULT NULL,
`from` varchar(10) not null default '',
PRIMARY KEY (`aid`),
KEY `ori_tid` (`ori_aid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
