<?php
if (substr(PHP_SAPI, 0, 3) !== 'cli') {
    header('HTTP/1.1 404 Not Found');
    exit;
}

/* Init Discuz */
define('CURSCRIPT', 'eefmigrate');
require '../source/class/class_core.php';
$discuz = C::app();
$discuz->init();
require libfile('function/member');
require libfile('function/post');
require libfile('class/member');
require 'db2.php';

/* Load uc client */
loaducenter();

$config = array(
	'analog' => array(
		'db' => array(
			'dbhost' => '192.168.2.82:3310',
			'dbuser' => 'pi',
			'dbpw' => 'pi0812',
			'dbcharset' => 'utf8',
			'pconnect' => '0',
			'dbname' => 'pi_analog_eefocus',
		),
		'fid' => 290,//模拟/电源
		'fid_not_in' => '1283, 1284',//排除板块ID
		'typeid' => 0,//主题分类ID
	),
	'rohm' => array(
		'db' => array(
			'dbhost' => '192.168.2.82:3310',
			'dbuser' => 'pi',
			'dbpw' => 'pi0812',
			'dbcharset' => 'utf8',
			'pconnect' => '0',
			'dbname' => 'pi_rohm_eefocus',
		),
		'fid' => 290,//模拟/电源
		'fid_in' => '1333, 1334, 1335, 1336, 1337, 1338',//板块ID
		'typeid' => 0,//主题分类ID
	),
	'rf' => array(
		'db' => array(
			'dbhost' => '192.168.2.82:3310',
			'dbuser' => 'pi',
			'dbpw' => 'pi0812',
			'dbcharset' => 'utf8',
			'pconnect' => '0',
			'dbname' => 'pi_rf_eefocus',
		),
		'fid' => 291,//模拟/电源
		'fid_not_in' => '1245, 1248',//排除板块ID
		'typeid' => 0,//主题分类ID
	),
);

$option = getopt('o::');
if (!empty($option)) $option = $option['o'];
if (empty($option)) {
	foreach ($config as $site => $siteConf) {
		echo '-------- ' . $site . ' >> migrate:user start --------';
		echo "\n";ob_flush();
		migrate_user($site);
		echo '-------- ' . $site . ' >> migrate:user end --------';
		echo "\n";ob_flush();
		echo '-------- ' . $site . ' >> migrate:thread start --------';
		echo "\n";ob_flush();
		migrate_thread($site);
		echo '-------- ' . $site . ' >> migrate:thread end --------';
		echo "\n";ob_flush();
	}
	// foreach ($config as $site => $siteConf) {
	// 	echo '-------- ' . $site . ' >> migrate:reset member start --------';
	// 	echo "\n";ob_flush();
	// 	member_reset($site);
	// 	echo '-------- ' . $site . ' >> migrate:reset member end --------';
	// 	echo "\n";ob_flush();
	// }
} elseif ($option == 'attach') {
	foreach ($config as $site => $siteConf) {
		echo '-------- ' . $site . ' >> migrate:unused attach start --------';
		echo "\n";ob_flush();
		migrate_unusedattach($site);
		echo '-------- ' . $site . ' >> migrate:unused attach end --------';
		echo "\n";ob_flush();
	}
} elseif ($option == 'clean') {
	// do_clean('rf');
} elseif ($option == 'member') {
	foreach ($config as $site => $siteConf) {
		echo '-------- ' . $site . ' >> migrate:reset member start --------';
		echo "\n";ob_flush();
		member_reset($site);
		echo '-------- ' . $site . ' >> migrate:reset member end --------';
		echo "\n";ob_flush();
	}
}

echo 'sueesss';exit;

function migrate_user($site) {
	global $config;
	DB_2::init('db_driver_mysql', array(
		1 => $config[$site]['db'],
		'common' => array('slave_except_table' => '')
	));
	$limit = 100;
	$offset = (int) memory('get', 'eefmigrate_offset_user_' . $site);
	$needClean = true;

	while (1) {
	    $members = DB_2::fetch_all(sprintf('select * from eef_common_member order by regdate asc limit %s offset %s', $limit, $offset));
	    if (empty($members)) {
	        break;
	    }
	    if ($needClean) {
	    	user_clean($members[0]['uid'], $site);
	    	$needClean = false;
	    }
	    $memberIds = [];
	    foreach ($members as $value) {
	    	if (!in_array($value['uid'], $memberIds)) {
	    		$memberIds[] = $value['uid'];
	    	}
	    }
	    // ucenter
	    $membersUc = [];
	    $membersUcex = DB_2::fetch_all('select uid,regip from eef_ucenter_members where uid in (' . implode(',', $memberIds) . ')');
	    foreach ($membersUcex as $value) {
	    	$membersUc[$value['uid']] = $value;
	    }
	    // cirmall bbs
	    $membersExists = getMembers($members);
	    foreach ($members as $value) {
	    	$eefUid = $value['uid'];
	    	if (isset($membersUc[$eefUid])) {
	    		$regIp = $membersUc[$eefUid]['regip'];
	    	} else {
	    		$regIp = 'hidden';
	    	}
	    	if (strpos($value['email'], ',') || strlen($value['email']) > 32) {
	    		$value['email'] = '';
	    	}
	    	if (!isset($membersExists[$eefUid])) {
	    		if ($value['username'] == 'yang982236\\') {
			    	$ucid = uc_user_register('yang982236__', $value['password'], $value['email'], '', '', $regIp, $value['uid']);
			   		if ($ucid) {
			    		DB::update('ucenter_members', array('username' => $value['username']), "`uid` = " . $ucid);
			   		}
	    		} else {
			    	$ucid = uc_user_register($value['username'], $value['password'], $value['email'], '', '', $regIp, $value['uid']);
	    		}
			    if (empty($ucid) || $ucid < 0) {
			    	if ($ucid == -3) {
			    		$account = getAccount($value['uid']);
			    		if (!empty($account)) {
			    			if ($account['nick_name'] != $value['username']) {
			    				$exist = DB::fetch_first("select * from bbs_ucenter_members where " . DB::field('username', $account['nick_name']));
			    				if (empty($exist)) {
			    					$value['username'] = $account['nick_name'];
			    					$ucid = uc_user_register($value['username'], $value['password'], $value['email'], '', '', $regIp, $value['uid']);
			    				}
			    			} else {
					    		$exist = DB::fetch_first("select * from bbs_ucenter_members where " . DB::field('username', $value['username']));
					    		if (!empty($exist)) {
						    		$account = getAccount($exist['eefocus_uid']);
						    		if (!empty($account)) {
						    			$nameExist = DB::fetch_first("select * from bbs_ucenter_members where " . DB::field('username', $account['nick_name']));
						    			if (empty($nameExist)) {
								    		DB::update('ucenter_members', array('username' => $account['nick_name']), "`uid` = " . $exist['uid']);
								    		DB::update('common_member', array('username' => $account['nick_name']), "`uid` = " . $exist['uid']);
								    		$ucid = uc_user_register($value['username'], $value['password'], $value['email'], '', '', $regIp, $value['uid']);
						    			}
						    		}
					    		}
					    	}
					    }
			    	} elseif ($ucid == -6) {
			    		$account = getAccount($value['uid']);
			    		if (!empty($account)) {
			    			if ($account['email'] != $value['email']) {
			    				$exist = DB::fetch_first(sprintf("select * from bbs_ucenter_members where email='%s'", $account['email']));
			    				if (empty($exist)) {
			    					$value['email'] = $account['email'];
			    					$ucid = uc_user_register($value['username'], $value['password'], $value['email'], '', '', $regIp, $value['uid']);
			    				}
			    			} else {
				    			$exist = DB::fetch_first(sprintf("select * from bbs_ucenter_members where email='%s'", $value['email']));
					    		if (!empty($exist)) {
						    		$account = getAccount($exist['eefocus_uid']);
						    		if (!empty($account)) {
						    			$emailExist = DB::fetch_first(sprintf("select * from bbs_ucenter_members where email='%s'", $account['email']));
						    			if (empty($emailExist)) {
								    		DB::update('ucenter_members', array('email' => $account['email']), "`uid` = " . $exist['uid']);
								    		DB::update('common_member', array('email' => $account['email']), "`uid` = " . $exist['uid']);
								    		$ucid = uc_user_register($value['username'], $value['password'], $value['email'], '', '', $regIp, $value['uid']);
						    			}
						    		}
					    		}
					    	}
			    		}
			    	} elseif ($ucid == -4) {
			    		$value['email'] = '';
			    		$ucid = uc_user_register($value['username'], $value['password'], $value['email'], '', '', $regIp, $value['uid']);
			    	}
			    	if (empty($ucid) || $ucid < 0) {
				        throw new Exception('Failed to register user in UCenter. ucid:' . $ucid . ' user:' . json_encode(array(
				        	$value['uid'], $value['username'], $value['email']
				        )));
				    }
			    }
		    	DB::insert('migrate_eef', array('uid' => $ucid,'eefocus_uid' => $value['uid'], 'from' => $site));

			    C::t('common_member')->insert($ucid, $value['username'], md5(random(10)), $value['email'], $regIp, 10, explode(',', '0,0,0,0,0,0,0,0,0'));
	    	}
	    	$offset += 1;
	    	echo $offset;
	    	echo "\n";ob_flush();

	    	memory('set', 'eefmigrate_offset_user_' . $site, $offset);
	    }
	    sleep(2);
	}
}

function migrate_thread($site) {
	global $config;
	$limit = 100;
	$offset = (int) memory('get', 'eefmigrate_offset_thread_' . $site);
	$needClean = true;

	while (1) {
		if (!empty($config[$site]['fid_in'])) {
			$threads = DB_2::fetch_all(sprintf('select * from eef_forum_thread where fid in (%s) order by tid asc limit %s offset %s', $config[$site]['fid_in'], $limit, $offset));
		} elseif (!empty($config[$site]['fid_not_in'])) {
			$threads = DB_2::fetch_all(sprintf('select * from eef_forum_thread where fid not in (%s) order by tid asc limit %s offset %s', $config[$site]['fid_not_in'], $limit, $offset));
		} else {
	    	$threads = DB_2::fetch_all(sprintf('select * from eef_forum_thread order by tid asc limit %s offset %s', $limit, $offset));
		}
	    if (empty($threads)) {
	        break;
	    }
	    if ($needClean) {
	    	thread_clean($threads[0]['tid'], $site);
	    	$needClean = false;
	    }
	    $membersUcenter = getMembers($threads, 'authorid');
	    foreach ($threads as $thread) {
	    	$oriTid = $thread['tid'];
	    	unset($thread['tid']);
	    	$thread['fid'] = $config[$site]['fid'];
	    	$thread['typeid'] = $config[$site]['typeid'];
	    	$thread['author'] = $membersUcenter[$thread['authorid']]['username'];
	    	$thread['authorid'] = $membersUcenter[$thread['authorid']]['uid'];
	    	$thread['replycredit'] = 0;//TODO
	    	$thread['relatebytag'] = 0;
	    	$tid = DB::insert('forum_thread', $thread, true);

	    	DB::insert('migrate_eef_thread', array('tid' => $tid, 'ori_tid' => $oriTid, 'from' => $site));

	    	$threadTags = DB_2::fetch_all("select * from eef_common_tagitem left join eef_common_tag on eef_common_tagitem.tagid=eef_common_tag.tagid where idtype='tid' and itemid=" . $oriTid);
	    	$posts = DB_2::fetch_all('select * from eef_forum_post where tid=' . $oriTid);
		    $tagNames = [];
		    if (empty($threadTags)) $threadTags = [];
	    	foreach ($threadTags as $threadTag) {
	    		$tagNames[] = $threadTag['tagname'];
	    	}
		    foreach ($posts as $post) {
		    	if (!empty($post['tags']) && $post['tags'] != '0') {
		    		$tagIdNames = explode("\t", $post['tags']);
		    		foreach ($tagIdNames as $tagIdName) {
		    			$tagName = preg_replace('/^\d+,/', '', $tagIdName);
		    			if (!empty($tagName) && !in_array($tagName, $tagNames)) {
		    				$tagNames[] = $tagName;
		    			}
		    		}
		    	}
		    }
		    if (!empty($tagNames)) {
		    	$tagNameIds = [];
		    	$tagItems = [];
			    $exTags = DB::fetch_all('select * from bbs_common_tag where ' . DB::field('tagname', $tagNames, 'in'));
			    if (empty($exTags)) $exTags = [];
		    	foreach ($tagNames as $tagName) {
		    		$exist = false;
				    foreach ($exTags as $exTag) {
				    	if ($tagName == $exTag['tagname']) {
				    		$tagid = $exTag['tagid'];
				    		$exist = true;
				    		break;
				    	}
				    }
				    if (!$exist) {
				    	$tagid = DB::insert('common_tag', array(
				    		'tagname' => $tagName,
				    		'status' => 0
				    	), true);
				    }
				    $tagNameIds[$tagName] = $tagid;

				    foreach ($threadTags as $threadTag) {
				    	if ($tagName == $threadTag['tagname']) {
				    		$tagItems[] = array(
				    			'tagid' => $tagid,
				    			'itemid' => $tid,
				    			'idtype' => 'tid',
				    		);
				    	}
					}
		    	}
		    	if (!empty($tagItems)) {
		    		DB::query(batchInsertSql('bbs_common_tagitem', $tagItems));
		    	}
		    }

		    $pMembersUcenter = getMembers($posts, 'authorid');
		    $postMap = [];
	    	foreach ($posts as $key => $post) {
	    		$oriPid = $post['pid'];
	    		$pid = DB::insert('forum_post_tableid', array('pid' => null), true);
	    		$post['pid'] = $pid;
	    		$post['tid'] = $tid;
	    		$post['fid'] = $config[$site]['fid'];
		    	$post['author'] = $pMembersUcenter[$post['authorid']]['username'];
		    	$post['authorid'] = $pMembersUcenter[$post['authorid']]['uid'];
		    	$postTags = [];
		    	if (!empty($post['tags']) && $post['tags'] != '0') {
		    		$tagIdNames = explode("\t", $post['tags']);
		    		foreach ($tagIdNames as $tagIdName) {
		    			$tagName = preg_replace('/^\d+,/', '', $tagIdName);
		    			if (!empty($tagName)) {
		    				$postTags[] = $tagNameIds[$tagName] . ',' . $tagName;
		    			}
		    		}
		    	}
		    	$post['tags'] = empty($postTags) ? '' : implode("\t", $postTags);
		    	$post['replycredit'] = 0;
		    	DB::insert('forum_post', $post);
		    	$postMap[$oriPid] = $pid;
	    	}
	    	//comment
	    	$comments = DB_2::fetch_all('select * from eef_forum_postcomment where tid=' . $oriTid);
	    	if (!empty($comments)) {
	    		$commentMembers = getMembers($comments, 'authorid');
		    	foreach ($comments as $ck => $comment) {
		    		unset($comment['id']);
		    		$comment['tid'] = $tid;
		    		$comment['pid'] = $postMap[$comment['pid']];
			    	$comment['author'] = $commentMembers[$comment['authorid']]['username'];
			    	$comment['authorid'] = $commentMembers[$comment['authorid']]['uid'];
			    	$comment['rpid'] = 0;
		    		$comments[$ck] = $comment;
		    	}
	    		DB::query(batchInsertSql('bbs_forum_postcomment', $comments));
	    	}

	    	//attachment
	    	$attachments = DB_2::fetch_all('select * from eef_forum_attachment where tid=' . $oriTid);
	    	if (!empty($attachments)) {
	    		$attachMembers = getMembers($attachments);
		    	$attachTables = [];
		    	$tableid = getattachtableid($tid);
		    	$aidMap = [];
		    	foreach ($attachments as $attachment) {
		    		if ($attachment['tableid'] > 9) {
		    			throw new Exception('attachment tableid error');
		    		}
		    		$aid = DB::insert('forum_attachment', array(
		    			'tid' => $tid,
		    			'pid' => $postMap[$attachment['pid']],
		    			'uid' => $attachMembers[$attachment['uid']]['uid'],
		    			'tableid' => $tableid,
		    			'downloads' => $attachment['downloads'],
		    		), true);
		    		$aidMap[$attachment['aid']] = $aid;
	    			$attachTable = 'eef_forum_attachment_' . $attachment['tableid'];
	    			if (!isset($attachTables[$attachTable])) {
	    				$attachTables[$attachTable] = [];
	    			}
	    			$attachTables[$attachTable][] = $attachment['aid'];
		    	}
		    	foreach ($attachTables as $attachTable => $aids) {
		    		$attachDetail = DB_2::fetch_all('select * from ' . $attachTable . ' where aid in(' . implode(',', $aids) . ')');
		    		$attachDetailMembers = getMembers($attachDetail);
		    		foreach ($attachDetail as $k => $attachRow) {
		    			$attachDetail[$k]['aid'] = $aidMap[$attachRow['aid']];
		    			$attachDetail[$k]['tid'] = $tid;
		    			$attachDetail[$k]['pid'] = $postMap[$attachRow['pid']];
		    			$attachDetail[$k]['uid'] = $attachDetailMembers[$attachRow['uid']]['uid'];
		    		}
		    		DB::query(batchInsertSql('bbs_forum_attachment_' . $tableid, $attachDetail));
		    	}
	    	}

	    	$offset += 1;
	    	echo $offset;
	    	echo "\n";ob_flush();
	    	memory('set', 'eefmigrate_offset_thread_' . $site, $offset);
	    }
	    sleep(2);
	}
	updateforumcount($config[$site]['fid']);
}

function migrate_unusedattach($site) {
	global $config;
	DB_2::init('db_driver_mysql', array(
		1 => $config[$site]['db'],
		'common' => array('slave_except_table' => '')
	));
	$limit = 100;
	$offset = (int) memory('get', 'eefmigrate_offset_attach_' . $site);
	$needClean = true;

	while (1) {
	    $attachs = DB_2::fetch_all(sprintf('select * from eef_forum_attachment as a inner join eef_forum_attachment_unused as u on a.aid=u.aid order by a.aid asc limit %s offset %s', $limit, $offset));
	    if (empty($attachs)) {
	        break;
	    }
	    if ($needClean) {
	    	attach_clean($attachs[0]['aid'], $site);
	    	$needClean = false;
	    }
	    $attachMembers = getMembers($attachs);

	    foreach ($attachs as $attach) {
	    	if ($attach['tid'] == 0 && $attach['pid'] == 0 && $attach['tableid'] == 127) {
		    	$aid = DB::insert('forum_attachment', array(
	    			'tid' => 0,
	    			'pid' => 0,
	    			'uid' => $attachMembers[$attach['uid']]['uid'],
	    			'tableid' => 127,
	    			'downloads' => $attach['downloads'],
	    		), true);
		    	DB::insert('migrate_eef_attach', array('aid' => $aid, 'ori_aid' => $attach['aid'], 'from' => $site));

	    		$attachUnuse = $aidMap[$attach['aid']];
	    		$attachUnuse['aid'] = $aid;
	    		$attachUnuse['uid'] = $attachMembers[$attach['uid']]['uid'];
		    	DB::insert('forum_attachment_unused', array(
		    		'aid' => $aid,
		    		'uid' => $attachMembers[$attach['uid']]['uid'],
		    		'dateline' => $attach['dateline'],
		    		'filename' => $attach['filename'],
		    		'filesize' => $attach['filesize'],
		    		'attachment' => $attach['attachment'],
		    		'remote' => $attach['remote'],
		    		'isimage' => $attach['isimage'],
		    		'width' => $attach['width'],
		    		'thumb' => $attach['thumb'],
		    	));

		    	$offset += 1;
		    	echo $offset;
		    	echo "\n";ob_flush();
		    	memory('set', 'eefmigrate_offset_attach_' . $site, $offset);
	    	} else {
	    		throw new Exception('attachment unused error');
	    	}
	    }
	    sleep(2);
	}
}

function member_reset($site) {
	global $config;
	DB_2::init('db_driver_mysql', array(
		1 => $config[$site]['db'],
		'common' => array('slave_except_table' => '')
	));
	$limit = 100;
	$offset = (int) memory('get', 'eefmigrate_offset_mreset_' . $site);

	while (1) {
	    $uids = DB_2::fetch_all(sprintf('select uid from eef_common_member order by uid asc limit %s offset %s', $limit, $offset));
	    if (empty($uids)) {
	        break;
	    }
	    $members = getMembers($uids);

	    foreach ($uids as $eefUid) {
	    	$eefUid = $eefUid['uid'];
	    	if (!isset($members[$eefUid])) {
	    		throw new Exception("Missing uid" . $eefUid);
	    	}
	    	$mem = $members[$eefUid];
			$postcount = 0;
			$postcount += C::t('forum_post')->count_by_authorid(0, $mem['uid']);
			$postcount += C::t('forum_postcomment')->count_by_authorid($mem['uid']);
			$threadcount = DB::result_first("SELECT COUNT(*) FROM bbs_forum_thread WHERE authorid=%d AND displayorder >= 0", array($mem['uid']));
			C::t('common_member_count')->update($mem['uid'], array('posts' => $postcount, 'threads' => $threadcount));
	    	$offset += 1;
	    	echo $offset;
	    	echo "\n";ob_flush();
	    	memory('set', 'eefmigrate_offset_mreset_' . $site, $offset);
		}
		sleep(2);
	}
}

function user_clean($oriUid, $from) {
	$exist = DB::fetch_first(sprintf("select * from bbs_migrate_eef where eefocus_uid=%d and `from`='%s'", $oriUid, $from));
	if (!empty($exist)) {
		uc_user_delete($exist['uid']);
		C::t('common_member')->delete_no_validate($exist['uid']);
		DB::delete('migrate_eef', 'uid='.$exist['uid']);
	}
}

function thread_clean($oriTid, $from) {
	$exist = DB::fetch_first(sprintf("select * from bbs_migrate_eef_thread where ori_tid=%d and `from`='%s'", $oriTid, $from));
	if (!empty($exist)) {
		thread_clean_by_tid($exist['tid']);
	}
}

function thread_clean_by_tid($tid) {
	//tag
	DB::delete('common_tagitem', "idtype='tid' and itemid=".$tid);
	//post
	$posts = DB::fetch_all('select pid from bbs_forum_post where tid='.$tid);
	if (!empty($posts)) {
		$inPid = [];
		foreach ($posts as $post) {
			$inPid[] = $post['pid'];
		}
		DB::delete('forum_post_tableid', 'pid in ('.implode(',', $inPid).')');
	}
	DB::delete('forum_post', 'tid='.$tid);
	// attachment
	DB::delete('forum_attachment', 'tid='.$tid);
	$tableid = getattachtableid($tid);
	DB::delete('forum_attachment_'.$tableid, 'tid='.$tid);

	DB::delete('forum_thread', 'tid='.$tid);
	DB::delete('migrate_eef_thread', 'tid='.$tid);
}


function attach_clean($oriAid, $from) {
	$exist = DB::fetch_first(sprintf("select * from bbs_migrate_eef_attach where ori_aid=%d and `from`='%s'", $oriAid, $from));
	if (!empty($exist)) {
		$aid = $exist['aid'];
		DB::delete('forum_attachment', 'aid='.$aid);
		DB::delete('forum_attachment_unused', 'aid='.$aid);
		DB::delete('migrate_eef_attach', 'aid='.$aid);
	}
}

function getMembers($members, $key = 'uid') {
	if (empty($members)) {
		return array();
	}
	$memberIds = [];
    foreach ($members as $value) {
    	if (!in_array($value[$key], $memberIds)) {
    		$memberIds[] = $value[$key];
    	}
    }
	$membersUcenter = [];
    $membersEeb = DB::fetch_all('select uid,eefocus_uid,username from bbs_ucenter_members where eefocus_uid in (' . implode(',', $memberIds) . ')');
    foreach ($membersEeb as $value) {
    	$membersUcenter[$value['eefocus_uid']] = $value;
    }
    return $membersUcenter;
}

function getAccount($uid) {
    $url = sprintf('https://account.eefocus.com/account/api/get.user/id-%d', $uid);
    $json = curl_get($url);
    return empty($json) ? null : json_decode($json, true);
}

function batchInsertSql($table, $datas) {
	$keys = implode(',', array_keys($datas[0]));
	$sql = sprintf('insert into %s (%s) values', $table, $keys);
	foreach ($datas as $ii => $data) {
		foreach ($data as $jj => $value) {
			$data[$jj] = DB::quote($value);
		}
		$sql .= '(' . implode(',', $data) . '),';
	}
	return substr($sql, 0, -1) . ';';
}

function do_clean($from) {
	$limit = 100;

	// while (1) {
	// 	$exist = DB::fetch_all(sprintf("select * from bbs_migrate_eef where `from`='%s' limit %d", $from, $limit));
	// 	if (empty($exist)) {
	// 		break;
	// 	}
	// 	$uids = [];
	// 	foreach ($exist as $user) {
	// 		$uids[] = $user['uid'];
	// 	}
	// 	uc_user_delete($uids);
	// 	C::t('common_member')->delete_no_validate($uids);
	// 	DB::delete('migrate_eef', DB::field('uid', $uids));
	// }

	while (1) {
		$exist = DB::fetch_all(sprintf("select * from bbs_migrate_eef_thread where `from`='%s' limit %d", $from, $limit));
		if (empty($exist)) {
			break;
		}
		foreach ($exist as $thread) {
			thread_clean_by_tid($thread['tid']);
		}
	}
}
