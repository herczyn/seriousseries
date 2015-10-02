<?php

//phpinfo();
error_reporting(E_ALL);
ini_set('display_errors',1);
//error_reporting(0);
//ini_set('display_errors', 0);

ini_set('date.timezone', 'Europe/Warsaw');

const jQueryVersion = '2.1.3';
const bootstrapVersion = '3.3.2';

const DAY = 86400;
const DAYS_TOTAL = 50;
const DAYS_TO_SHOW=10;

const BIG_NUMBER_1 = 10000000000;
const BIG_NUMBER_2 = 20000000000;
const BIG_NUMBER_3 = 30000000000;

$services = [
	'kat' => ['name'=>'kat',      'link'=>'https://kickass.to/usearch/$name$ S$season0$E$episode0$/?field=seeders&amp;sorder=desc'],
	'tpb' => ['name'=>'tpb',      'link'=>'https://thepiratebay.se/search/$name$ S$season0$E$episode0$/0/7/0'],
	'iso' => ['name'=>'isohunt',  'link'=>'http://isohunt.to/torrents/?ihq=$name$ S$season0$E$episode0$?iht=-1&amp;ihp=1&amp;ihs1=1&amp;iho1=d'],
	'add' => ['name'=>'addic7ed', 'link'=>'http://www.addic7ed.com/search.php?search=$name$ S$season0$E$episode0$'],
	'ftv' => ['name'=>'free tv',  'link'=>'http://www.free-tv-video-online.me/internet/$name_$/season_$season$.html#e$episode$']
];

$allLabels = array('heart', 'star', 'glass', 'music', 'repeat', 'home');

$debug = ""; $queryNum = 0;

$timeToComp = time();
$newEpisodesSQL  = "";
$isForLaterSQL   = "";
$showInfoSQL     = "";
$userEpisodesSQL = "";
$queryForMailSQL = "";
$pageTitle = "";
$idShowArray = 0;

$timer=microtime(true);

require "config.php";
$mysql = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_name);
if ($mysql->connect_errno) {
    echo "error connecting to db";
    exit;
}

query("DELETE FROM users_tokens WHERE creation_date < date_sub(now(), interval 1 month)");

$menu = ""; $menuC = ""; $script = ""; $page = ""; $ret = "";

if (isset($argv[1])) {
	$address = "//seriousseri.es/";
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	if ($argv[1]=='cron') {
		$result = query("SELECT * FROM shows WHERE id_show_tvmaze>0 ".(isset($argv[2]) ? " AND id_show=".$argv[2] : "ORDER BY last_update ASC LIMIT 0, 1"));
		while ($row = $result->fetch_object()) {
			if (!check($row))
				echo "fail - ".$row->n_show.", ".date("G:i j-n-Y",time())."\n";
		}
	}
	if ($argv[1]=='info') {
		$users = query("SELECT * FROM users LEFT JOIN timezones ON users.id_timezone = timezones.id_timezone WHERE IfNull(email_address,'')<>''");
		while ($userInfo = $users->fetch_object()) {
			setQueries();
			$mail = "";
			$result = query($queryForMailSQL.$userInfo->id_user.($userInfo->email_only_new ? " GROUP BY shows.n_show, shows_episodes.id_show HAVING Count(*)=1 AND ".$timeToComp."<Max(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)+".DAY : " AND ".$timeToComp."<shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY));
			while ($row = $result->fetch_object()) {
				if ($userInfo->email_auto_update)
					query("UPDATE users_shows SET season=".$row->c_season.", episode=".$row->c_episode." WHERE id_user=".$userInfo->id_user." AND id_show=".$row->id_show);
				$mail.= "<br />\n".$row->n_show." s".substr("0".$row->season, -2)."e".substr("0".$row->episode,-2)." (".$row->title."): ".serviceLinks($row->n_search, $row->season, $row->episode, "\"");
			}
			try {
				if ($mail!="") {
					$links="";
					foreach ($services as $key => $service) {
						if (strpos("|".$userInfo->services_off."|", "|".$key."|")===false)
							$links.=($links=="" ? "" : ",")." <a href='http:".$address."multi_e_".$key."/".date("Ymd", time())."'>".$service['name']."</a>";
					}
					$mail="<html><body>new show episodes:".$mail."<br /><a href='http:".$address."'>click here to visit SeriousSeri.es</a><br /><br />multilinks:".$links."</body></html>";
					//echo $userInfo->email_address."\n\n";
					//echo $mail."\n\n";
					mail($userInfo->email_address, "Serious Series", $mail, "From: SeriousSeri.es <no_reply@seriousseri.es>\r\nMIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\n");
				}
			} catch(Exception $e) {
				echo "MAIL ERROR: ".$e->getMessage()."\n";
				return false;
			}
		}
	}
	$mysql->close();
	exit;
}

$address = "//".$_SERVER['SERVER_NAME']."/";
$cdnaddress = "//cdn.seriousseri.es/".(substr($address,2,4)=="test" ? "test/" : "");

$loginForm = "<form action='".$address."' method='post'>login:<input type='text' name='login' /><br />password:<input type='password' name='password' /><br />remember me:<input type='checkbox' name='autologin' /><br /><input type='submit' value='login' id=loginButton /><br /><br /><a href='#' onClick='document.getElementById(\"registerLink\").style.display=\"none\";document.getElementById(\"registerInfo\").style.display=\"\";document.getElementById(\"loginButton\").value=\"register\";return false;' id='registerLink'>sign up</a><span style='display:none' id='registerInfo'>If you want to register enter your desired login and password above and to prove that you're not a robot the name of this website here: <input type='text' name='test' /></span></form>";
$shortLoginForm = "</ul><form action='".$address."' method='post' class='navbar-form navbar-right' id='login'><input type='checkbox' name='autologin' style='display:none' id='autologin' /><input type='text' name='login' placeholder='login' style='width:70px;margin:0' class='form-control input-sm' /> <input type='password' name='password' placeholder='password' style='width:70px;margin:0' class='form-control input-sm' /> <div class='btn-group'><button type='submit' class='btn btn-sm navbar-btn' style='margin:0'>login</button><button class='btn btn-sm navbar-btn dropdown-toggle' style='margin:0' data-toggle='dropdown'><span class='caret'></span></button><ul class='dropdown-menu' role='menu'><li><a href='#' onClick='\$(\"#autologin\").attr(\"checked\", true);\$(\"#login\").submit();return false;'>remember me</a></li><li><a href='".$address."login'>sign up</a></li></ul></div></form>";

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");   // Date in the past

session_start();
$user = (isset($_SESSION['user']) ? $_SESSION['user'] : 0);

if ($user==1 && isset($_GET['setUser'])) {
	$user = $_GET['setUser'];
	$_SESSION['user'] = $user;
}

if (!$user && isset($_COOKIE['autologin'])) {
	$result = query("SELECT * FROM users_tokens WHERE type='autologin' AND token = '".addslashes($_COOKIE['autologin'])."'");
	if ($row = $result->fetch_object()) {
		$user = $row->id_user;
		$_SESSION['user'] = $user;
	}
}

if ($user) {
	$userInfo = query("SELECT * FROM users LEFT JOIN timezones ON users.id_timezone = timezones.id_timezone WHERE id_user=".$user)->fetch_object();
	if (!$userInfo) 
		$user=0;
}
if (!$user) {
	$userInfo = query("SELECT * FROM users LEFT JOIN timezones ON users.id_timezone = timezones.id_timezone WHERE id_user=0")->fetch_object();
} else {
	query("UPDATE users SET last_activity = ".time()." WHERE id_user = ".$user);
}

setQueries();
$showId=0;
foreach (preg_split('/\//', $_SERVER['REQUEST_URI']) as $req) {
	if ($req!="" && substr($req, 0, 1) !="?") {
		$req=urldecode($req);
		if (in_array($req, array('login', 'logout', 'logoutAndClear', 'settings', 'stats', 'list', 'week', 'backup', 'backup', 'update', 'ical'))) {
			$page = $req;
		} elseif (substr($req, 0, 6) == 'multi_') {
			$page    = 'multiclick';
			if (substr($req, 7, 1)=="_") {
				$multi   = substr($req, 6, 1);
				$service = substr($req, 8);
			} else {
				$multi   = "e";
				$service = substr($req, 6);
			}
			if (isset($services[$service]))
				$service=$services[$service];
			else
				$page='';
		} elseif (substr($req, 0, 5) == 'user_') {
			$userToComp = query("SELECT * FROM users WHERE login='".addslashes(substr($req, 5))."'")->fetch_object();
			if ($userToComp)
				$page='user compare';
		} elseif (is_numeric($req) && strlen($req)==8) {
			setQueries(mktime(1, 0, 0, substr($req, 4, 2), substr($req, 6, 2) - 1, substr($req, 0, 4)));
		} elseif (is_numeric($req) && strlen($req)==12) {
			setQueries(mktime(substr($req, 8, 2), substr($req, 10, 2), 0, substr($req, 4, 2), substr($req, 6, 2), substr($req, 0, 4)));
		} else {
//			$newCommentsSQL  = "(SELECT count(*) FROM comments_unread INNER JOIN comments ON comments_unread.id_comment = comments.id_comment INNER JOIN users_shows ON comments_unread.id_user = users_shows.id_user AND comments.id_show = users_shows.id_show AND users_shows.season*1000+users_shows.episode>=comments.season*1000+comments.episode WHERE users_shows.id_user = ".$userInfo->id_user." AND users_shows.id_show = shows.id_show)";
		 	$showInfo = query("SELECT $showInfoSQL, group_concat(distinct concat('<a href=\"".$address."user_', users.login, '\">', users.login, ' (s', substring(concat('0', IfNull(us.season,0)), -2), 'e', substring(concat('0', IfNull(us.episode,0)), -2), ')</a>') order by users.login separator ', ') AS logins FROM ((((users_shows INNER JOIN shows ON users_shows.id_show = shows.id_show) LEFT JOIN shows_episodes ON users_shows.id_show = shows_episodes.id_show) LEFT JOIN (SELECT users_shows.* FROM users_shows INNER JOIN users ON users_shows.id_user = users.id_user WHERE NOT users_shows.is_deleted AND users.id_user<>".$user." AND users.last_activity>".(time() - 30*24*3600).") AS us ON users_shows.id_show = us.id_show AND users_shows.id_user <> us.id_show) LEFT JOIN users ON us.id_user = users.id_user) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone WHERE shows.n_show = '".showNameFromUrl($req)."' AND users_shows.id_user = ".$user)->fetch_object();
//		 	var_dump(showNameFromUrl($req));
			if ($showInfo) {
				$showId = $showInfo->id_show;
			}
		}
	}
}
if (substr($_SERVER['SERVER_NAME'], 0, 4)=='www.') {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: http://seriousseri.es".$_SERVER['REQUEST_URI']);
	exit;
}

$postSlashes = array();
foreach ($_POST as $k=>$v) {
	$postSlashes[$k]=addslashes($v);
}
if (($page=='' && !$showId) || ($page=='login' && $user))
	$page = ($userInfo->show_week ? 'week' : 'list');

if ($user && $page=='multiclick') {
	$scriptT = '';
	if ($multi=='n') {
		$result = query("SELECT n_show, If(show_links, n_search, 'DONT') AS n_search, c_season as season, c_episode as episode FROM (".$userEpisodesSQL."WHERE NOT users_shows.is_deleted AND users_shows.id_user = ".$user." AND Not users_shows.is_hidden_from_brand_new GROUP BY shows.id_show HAVING $newEpisodesSQL=1 ORDER BY shows.n_show) D");
	} elseif ($multi=='e') {
		$result = query($queryForMailSQL.$user.($userInfo->email_only_new ? " GROUP BY shows.n_show, shows_episodes.id_show HAVING Count(*)=1 AND ".$timeToComp."<Max(shows_episodes.date)+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY : " AND ".$timeToComp."<shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY));
	} elseif ($multi=='t') {
		$result = query($queryForMailSQL.$user." AND ".$timeToComp."<shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY);
	} elseif ($multi=='s') {
		$result = query("SELECT n_show, If(users_shows.show_links, shows.n_search, 'DONT') AS n_search, shows_episodes.* FROM (users_shows INNER JOIN (shows INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone) ON users_shows.id_show = shows.id_show) INNER JOIN shows_episodes ON shows.id_show = shows_episodes.id_show WHERE users_shows.id_show=".$showId." AND users_shows.id_user=".$user." AND date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp AND 1000*shows_episodes.season+shows_episodes.episode>1000*IfNull(users_shows.season,0)+IfNull(users_shows.episode,0)");
		$showId = 0;
	} else {
		die('unknown multilink');
	}
	while ($row = $result->fetch_object()) {
		$link = serviceLink($service, $row->n_search, $row->season, $row->episode);
		if ($link!="")
			$scriptT.=",".$link;
	}
	$page='';
	if ($scriptT!='')
		$script.="\$(function(){openWindows(\"".substr($scriptT, 1)."\");});";
	else
		$page = ($userInfo->show_week ? 'week' : 'list');
}

if ($user) {
	if (isset($postSlashes['formType'])) {
		switch ($postSlashes['formType']) {
			case "show":
				$labels = '';
				foreach ($allLabels as $label) {
					$labels.=", is_".$label."=".(isset($postSlashes['is_'.$label]) ? "1" : "0");
				}
				query("UPDATE users_shows SET season='".$postSlashes['season']."', episode='".$postSlashes['episode']."', note='".$postSlashes['note']."', is_for_later=".(isset($postSlashes['is_for_later']) ? "1" : "0").", is_full_season=".(isset($postSlashes['is_full_season']) ? "1" : "0").", is_hidden_from_brand_new=".(isset($postSlashes['is_hidden_from_brand_new']) ? "1" : "0").", show_links=".(isset($postSlashes['show_links']) ? "1" : "0").", is_deleted=".$postSlashes['is_deleted'].$labels." WHERE id_user=".$user." AND id_show=".$postSlashes['id_show']);
				break;
			case "show undelete":
				query("UPDATE users_shows SET is_deleted=0 WHERE id_user=".$user." AND id_show=".$postSlashes['id_show']);
				break;
			case "shows":
		  	$result = query("SELECT shows.id_show, users_shows.id_user, users_shows.is_deleted FROM (SELECT * FROM users_shows WHERE id_user = ".$user.") AS users_shows RIGHT JOIN shows ON users_shows.id_show = shows.id_show");
    		while ($row = $result->fetch_object()) {
					if ($row->id_user && !$row->is_deleted && !isset($postSlashes[$row->id_show])) {
						query("UPDATE users_shows SET is_deleted = 1 WHERE id_user=".$user." AND id_show=".$row->id_show);
					} else if (!$row->id_user && isset($postSlashes[$row->id_show])) {
						query("INSERT INTO users_shows (id_user, id_show) VALUES (".$user.", ".$row->id_show.")");
					} else if ($row->is_deleted && isset($postSlashes[$row->id_show])) {
						query("UPDATE users_shows SET is_deleted = 0 WHERE id_user=".$user." AND id_show=".$row->id_show);
					}
				}
				$result->close();
				break;
			case "settings":
				$servicesOff = "";
				foreach ($services as $key => $service) {
					if (!isset($postSlashes['service_'.$key]))
						$servicesOff.=($servicesOff ? "|" : "").$key;
				}
				if (isset($postSlashes['ical_on']))
					if ($userInfo->ical_key)
						$ical_key = $userInfo->ical_key;
					else
						$ical_key = md5(time()."ical_key".$user);
				else
					$ical_key="";
				query("UPDATE users SET email_address='".$postSlashes['email_address']."', email_auto_update=".(isset($postSlashes['email_auto_update']) ? "1" : "0").", email_only_new=".(isset($postSlashes['email_only_new']) ? "1" : "0").
					", list_days='".$postSlashes['list_days']."', list_day_margin='".$postSlashes['list_day_margin']."', list_show_titles=".(isset($postSlashes['list_show_titles']) ? "1" : "0").", list_sort_waiting_by_episodes_no=".(isset($postSlashes['list_sort_waiting_by_episodes_no']) ? "1" : "0").", list_show_last_episode=".(isset($postSlashes['list_show_last_episode']) ? "1" : "0").", show_week=".(isset($postSlashes['show_week']) ? "1" : "0").", week_new_limit='".$postSlashes['week_new_limit'].
					"', week_show_titles='".$postSlashes['week_show_titles']."', week_labels_left=".(isset($postSlashes['week_labels_left']) ? "1" : "0").", week_labels_top=".(isset($postSlashes['week_labels_top']) ? "1" : "0").
					", week_labels_center=".(isset($postSlashes['week_labels_center']) ? "1" : "0").", week_hide_cols=".(isset($postSlashes['week_hide_cols']) ? "1" : "0").
					", ical_key = '".$ical_key."', ical_only_first_episode = ".(isset($postSlashes['ical_only_first_episode']) ? "1" : "0").", ical_hours = ".(isset($postSlashes['ical_hours']) ? "1" : "0").", services_off='".$servicesOff."', show_flags=".(isset($postSlashes['show_flags']) ? "1" : "0")." WHERE id_user = ".$user);
				if (isset($postSlashes['login']))
					if ($postSlashes['login']!='' && $userInfo->login!=$postSlashes['login'])
						if (!query("SELECT * FROM users WHERE login='".$postSlashes['login']."'")->fetch_object())
							query("UPDATE users SET login='".$postSlashes['login']."' WHERE id_user=".$user);
				if (isset($postSlashes['pass1']) && isset($postSlashes['pass2']))
					if ($postSlashes['pass1']==$postSlashes['pass2'] && $postSlashes['pass1']!='') {
						$salt = time().md5(time())."secret";
						query("UPDATE users SET password = '".md5($postSlashes['pass1'].$salt)."', salt='".$salt."' WHERE id_user=".$user);
					}
				break;
			case "add show":
				query("INSERT INTO shows (n_show, id_show_tvmaze) VALUES ('".$postSlashes['n_show']."', '".$postSlashes['id_show_tvmaze']."')");
				break;
			case "comment":
				$cTime = time();
				query("INSERT INTO comments (id_user, id_show, date, season, episode, comment) VALUES (".$user.", ".$showInfo->id_show.", ".$cTime.", ".$showInfo->season.", ".$showInfo->episode.", '".$postSlashes['comment']."')");
				query("INSERT INTO comments_unread (id_comment, id_user) SELECT comments.id_comment, users_shows.id_user FROM comments INNER JOIN users_shows ON comments.id_show = users_shows.id_show WHERE comments.id_show = ".$showInfo->id_show." AND comments.id_user = ".$user." AND comments.date = ".$cTime." AND users_shows.id_user <> ".$user);
				break;
			default:
				echo "Error: unknown form ".$postSlashes['formType'].".";
				exit;
		}
	}
	if ($page=='update') {
		if (!$showId) {
			die("this option is turned off");
		}
		$result = query($userEpisodesSQL."WHERE users_shows.id_user = ".$user." AND shows.id_show = ".$showId." GROUP BY shows.id_show");
		while ($row = $result->fetch_object()) {
			query("UPDATE users_shows SET season=".$row->c_season.", episode=".$row->c_episode." WHERE id_show = ".$row->id_show." AND id_user = ".$user);
		}
		$page='reload';
	}
} else {
	if (isset($postSlashes['login'])) {
		$result = query("SELECT * FROM users WHERE login = '".$postSlashes['login']."'");
		if ($result->num_rows>0) {
			$row = $result->fetch_object();
			if ($row->password==md5($_POST['password'].$row->salt)) {
				$_SESSION['user'] = $row->id_user;
				if (isset($_POST['autologin'])) {
					$key = md5(session_id().time());
					setcookie('autologin', $key, time()+DAY*100, "/");
					query("INSERT INTO users_tokens (token, id_user, type) VALUES ('".$key."', ".$row->id_user.", 'autologin')");
				}
				$ret.= "logged in. <a href='".$address."'>continue.</a><script>document.location='".$address."';</script>";
				setcookie('waiting', 0, 1);
				setcookie('upcoming', 0, 1);
				setcookie('forlater', 0, 1);
				setcookie('unknown', 0, 1);
    	} else {
				$ret.="wrong password.<br />".$loginForm;
			}
		} elseif (isset($_POST['test'])) {
			if ((strtolower($_POST['test'])=='seriousseries' || strtolower($_POST['test'])=='seriousseri.es') && $postSlashes['login']!="") {
				$salt = time().md5(time())."secret";
    		query("INSERT INTO users (login, password, salt) values ('".$postSlashes['login']."', '".md5($_POST['password'].$salt)."', '".$salt."')");
	    	$ret.="Account created. Please login <a href='".$address."'>here</a>.";
	    } else {
				$ret.="No such account.";
	    }
		} else {
			$ret.="Error. If you're not a bot, contact the page's author.";
		}
		$result->close();
	}
}
if (count($_POST))
	$page='nothing';

foreach (array('waiting', 'upcoming', 'forlater', 'unknown') as $name) {
	$sname = 'show_'.$name;
	if (!isset($_COOKIE[$name])) {
		$_COOKIE[$name] = ($userInfo->$sname ? 'on' : 'off');
		setcookie($name, $_COOKIE[$name], time()+DAY*10, "/");
	} else if ($userInfo->id_user && $_COOKIE[$name] != ($userInfo->$sname ? 'on' : 'off')) {
		query("UPDATE users SET $sname = 1 - $sname WHERE id_user = ".$userInfo->id_user);
	}
}

if (($page=='logout' || $page=='logoutAndClear') && $user!=0) {
	if ($page=='logoutAndClear')
		query("DELETE FROM users_tokens WHERE type='autologin' AND id_user = ".$user);
	$_SESSION['user']=0;
	$ret.="goodbye.";
} else if ($page=='settings' && $user!=0) {
//najpopularniejsze: SELECT shows.*, Count(*) AS C, group_concat(users.login order by users.login) AS users FROM ((shows INNER JOIN users_shows ON shows.id_show = users_shows.id_show) INNER JOIN users ON users_shows.id_user = users.id_user) LEFT JOIN (SELECT id_show FROM users_shows WHERE id_user=3) AS a ON shows.id_show = a.id_show WHERE a.id_show Is Null AND NOT users_shows.is_for_later AND NOT users_shows.is_deleted GROUP BY shows.id_show ORDER BY Count(*) DESC, shows.n_show
	$retTemp = ""; $retTemp1 = ""; $retTemp2 = ""; $retTemp3 = ""; 
	$maxRows = 9; $rows = 0; $list = "";
	$lastShow = ""; $firstShow = "";
	$result = query("SELECT shows.*, my_shows.id_user, count(users_shows.id_show) AS i, group_concat(concat('<a href=\"".$address."user_',users.login,'\">',users.login,'</a>') order by users.login separator ', ') AS logins FROM (((SELECT * FROM users_shows WHERE id_user = ".$user." AND NOT is_deleted) AS my_shows RIGHT JOIN (SELECT shows.*, Sum(If(IfNull(shows_episodes.date,".BIG_NUMBER_1.")+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",0,1)) AS no_eps, Sum(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",1,0)) AS no_eps_announced FROM (shows LEFT JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) LEFT JOIN timezones ON shows.id_timezone = timezones.id_timezone GROUP BY shows.id_show) AS shows ON my_shows.id_show = shows.id_show) LEFT JOIN (SELECT users_shows.id_user, users_shows.id_show FROM ((shows INNER JOIN users_shows ON shows.id_show = users_shows.id_show) INNER JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone WHERE NOT is_deleted GROUP BY users_shows.id_user, users_shows.id_show, users_shows.season, users_shows.episode, shows.canceled HAVING NOT shows.canceled OR users_shows.season*1000+users_shows.episode<>Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",0,shows_episodes.season*1000+shows_episodes.episode))) AS users_shows ON shows.id_show = users_shows.id_show) LEFT JOIN users ON users_shows.id_user = users.id_user GROUP BY shows.id_show ORDER BY shows.".(isset($_GET['orderById']) ? "id" : "n")."_show");
	while ($row = $result->fetch_object()) {
		if ($firstShow=="")
			$firstShow = $row->n_show;
		if ($maxRows*3==$rows) {
			$retTemp.="<div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#showAccordion' href='#collapse".$row->id_show."'>".$firstShow." - ".$lastShow."</a></h4></div><div id='collapse".$row->id_show."' class='panel-collapse collapse'><div class='panel-body'><div class='control-group'><div class='controls one-third'>".$retTemp1."</div><div class='controls one-third'>".$retTemp2."</div><div class='controls one-third'>".$retTemp3."</div></div></div></div></div>";
			$firstShow = $row->n_show;
			$retTemp1="";
			$retTemp2="";
			$retTemp3="";
			$rows=0;
		}
		$str = "";
		if ($row->i==1) {
			$str = "person watching this show:<br />".$row->logins;
		} elseif ($row->i>0) {
			$str = "people watching this show:<br />".$row->logins;
		} else {
			$str = "nobody is watching this show :(";
		}
		$str.="<br />episodes: ".$row->no_eps.($row->no_eps_announced ? " + ".$row->no_eps_announced." announced" : "")."<br />1st episode ".str_replace("'", "\"", serviceLinks($row->n_search, 1, 1));
		$str ="<label class='checkbox'><input type='checkbox' name='".$row->id_show.($row->id_user ? "' checked='checked" : "")."' class='show".$row->id_show."' /> <span class='tt' title='<a target=\"_blank\" href=\"http://www.tvmaze.com/shows/".$row->id_show_tvmaze."/s\">info</a>' data-content='".$str."'>".$row->n_show.($userInfo->show_flags && $row->country ? " <img src='".$cdnaddress."flags/".strtolower(substr($row->country, 0, 2)).".png' alt='".$row->country."' /> " : "").($row->canceled ? " <i class='glyphicon glyphicon-remove'></i>" : "")."</span></label>";
		if ($rows<$maxRows)
	   	$retTemp1.=$str;
		elseif ($rows<$maxRows*2)
	   	$retTemp2.=$str;
	  else
	   	$retTemp3.=$str;
	  $list.="<li>".$str."</li>";
		$lastShow=$row->n_show;
		$rows++;
	}
	$ret.="<h4>shows:&nbsp;&nbsp;&nbsp;&nbsp;<input type='text' id='search' placeholder='search...' autocomplete='off' /></h4><form action='".$address."' method='post' id='shows'><div class='panel-group' id='showResults'>";
	$ret.="<div class='panel panel-default showList'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#showResults' href='#collapseResults'>search results</a></h4></div><div id='collapseResults' class='panel-collapse collapse in'><div class='panel-body'><ul>".$list."<li id='more'>load more results...</li></ul></div></div></div></div><div class='panel-group' id='showAccordion'>".$retTemp;
	if ($rows)
		$ret.="<div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#showAccordion' href='#collapseLast'>".$firstShow." - ".$lastShow."</a></h4></div><div id='collapseLast' class='panel-collapse collapse'><div class='panel-body'><div class='control-group'><div class='constrols one-third'>".$retTemp1."</div><div class='constrols one-third'>".$retTemp2."</div><div class='constrols one-third'>".$retTemp3."</div></div></div></div></div>";
	if ($user==1) {
 		$ret.="<div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#showAccordion' href='#collapseAdd'>add show</a></h4></div><div id='collapseAdd' class='panel-collapse collapse'><div class='panel-body'>name:&nbsp;&nbsp;&nbsp;&nbsp;<input type='text' name='n_show' id='n_show' /><br />";
	  $ret.="<a href='' onClick='this.href=\"//api.tvmaze.com/search/shows?q=\"+$(\"#n_show\").val()' target='_blank'>";
 		$ret.="show id:</a><input type='text' name='id_show_tvmaze' /><br /><input type='submit' value='add show' class='btn btn-success' onClick='$(\"#formTypeSettings\").val(\"add show\")' /></div></div>";
	}
	$ret.="</div></div><input type='hidden' name='formType' value='shows' id='formTypeSettings' /><input type='submit' value='save shows' class='btn btn-success' /></form>";
	if ($user!=1) {
		$ret.="show missing? post at <a href='//www.facebook.com/the.seriousseri.es'>our facebook page</a>.";
	}
	$ret.="<form action='".$address."' method='post'><h4>settings:</h4><div class='panel-group' id='settingsAccordion'>";
	$ret.="<div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#settingsAccordion' href='#collapseEmail'>email</a></h4></div><div id='collapseEmail' class='panel-collapse collapse'><div class='panel-body'>";
	$ret.="<table class='table table-striped'><thead><tr><td>address</td><td><input name='email_address' value='".$userInfo->email_address."' /></td></tr></thead>";
	$ret.="<tr><td>set episode as seen after sending email</td><td><input type='checkbox' name='email_auto_update' ".($userInfo->email_auto_update ? "checked='checked'" : "")." /></td></tr>";
	$ret.="<tr><td>only inform about brand new episodes</td><td><input type='checkbox' name='email_only_new' ".($userInfo->email_only_new ? "checked='checked'" : "")." /></tr></table>";
	$ret.="</div></div></div><div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#settingsAccordion' href='#collapseList'>list view</a></h4></div><div id='collapseList' class='panel-collapse collapse'><div class='panel-body'>";
	$ret.="<table class='table table-striped'><thead><tr><td>number of days with weekday</td><td><input name='list_days' value='".$userInfo->list_days."' class='number' /></td></tr></thead>";
	$ret.="<tr><td>show titles</td><td><input type='checkbox' name='list_show_titles' ".($userInfo->list_show_titles ? "checked='checked'" : "")." /></td></tr><tr><td>space between days</td><td><input type='text' name='list_day_margin' value='".$userInfo->list_day_margin."' class='number' /> pixels</td></tr>";
	$ret.="<tr><td>show <i class='glyphicon glyphicon-step-forward'></i> if last episode of season (might not always work)</td><td><input type='checkbox' name='list_show_last_episode' ".($userInfo->list_show_last_episode ? "checked='checked'" : "")." /></td></tr>";
	$ret.="<tr><td>sort waiting shows by no of episodes left</td><td><input type='checkbox' name='list_sort_waiting_by_episodes_no' ".($userInfo->list_sort_waiting_by_episodes_no ? "checked='checked'" : "")." /></td></tr></table>";
	$ret.="</div></div></div><div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#settingsAccordion' href='#collapseWeek'>week view</a></h4></div><div id='collapseWeek' class='panel-collapse collapse'><div class='panel-body'>";
	$ret.="<div><table class='table table-striped'><thead><tr><td>show by default</td><td><input type='checkbox' name='show_week' ".($userInfo->show_week ? "checked='checked'" : "")." /></td></tr></thead>";
	$ret.="<tr><td>max new episodes shows (0 for all)</td><td><input name='week_new_limit' class='number' value='".$userInfo->week_new_limit."' /></td></tr>";
	$ret.="<tr><td>number of letters of title to show</td><td><input name='week_show_titles' class='number' value='".$userInfo->week_show_titles."' /></td></tr>";
	$ret.="<tr><td>show show labels</td><td>left: <input type='checkbox' name='week_labels_left' ".($userInfo->week_labels_left ? "checked='checked'" : "")." /> top: <input type='checkbox' name='week_labels_top' ".($userInfo->week_labels_top ? "checked='checked'" : "")." /> center: <input type='checkbox' name='week_labels_center' ".($userInfo->week_labels_center ? "checked='checked'" : "")." /></td></tr>";
	$ret.="<tr><td>hide empty columns</td><td><input type='checkbox' name='week_hide_cols' ".($userInfo->week_hide_cols ? "checked='checked'" : "")." /></td></tr></table></div>";
	$ret.="</div></div></div><div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#settingsAccordion' href='#collapseIcal'>iCal</a></h4></div><div id='collapseIcal' class='panel-collapse collapse'><div class='panel-body'>";
	$ret.="<div><table class='table table-striped'><thead><tr><td>ical on</td><td><input type='checkbox' name='ical_on'".($userInfo->ical_key ? " checked='checked'" : "")." /></td></tr></thead>";
	$ret.="<tr><td>ical link</td><td>".($userInfo->ical_key ? "http:".$address."ical/?key=".$userInfo->ical_key : "set ical on above")."</td></tr>";
	$ret.="<tr><td>show only first episodes</td><td><input type='checkbox' name='ical_only_first_episode' ".($userInfo->ical_only_first_episode ? "checked='checked'" : "")." /></td></tr>";
	$ret.="<tr><td>show episode hours in calendar</td><td><input type='checkbox' name='ical_hours' ".($userInfo->ical_hours ? "checked='checked'" : "")." /></td></tr></table></div>";
	$ret.="</div></div></div><div class='panel panel-default'><div class='panel-heading'><h4 class='panel-title'><a data-toggle='collapse' data-parent='#settingsAccordion' href='#collapseOther'>other</a></h4></div><div id='collapseOther' class='panel-collapse collapse'><div class='panel-body'>";
	$ret.="<div><table class='table table-striped'><thead><tr><td>login</td><td><input type='text' name='login' value='".$userInfo->login."' /></td></tr></thead>";
	$ret.="<tr><td>password</td><td><input type='password' name='pass1' /></td></tr><tr><td>password repeat</td><td><input type='password' name='pass2' /></td></tr><tr><td>services:</td><td>";
	foreach ($services as $key => $service) {
		$ret.="<label class='checkbox'><input type='checkbox' name='service_".$key.(strpos("|".$userInfo->services_off."|", "|".$key."|")===false ? "' checked='checked" : "")."' />".$service['name']."</label>";
	}
	$ret.="</td></tr><tr><td>show flags  for shows</td><td><input type='checkbox' name='show_flags' ".($userInfo->show_flags ? "checked='checked'" : "")." /></td></tr></table></div></div></div></div></div>";
	$ret.="<input type='hidden' name='formType' value='settings' /><input type='submit' value='save settings' class='btn btn-success' /></form><br /><br />";
	$ret.="<a href='".$address."backup/SeriousShowsBackup".date("YmdHi", time()).".csv' class='btn btn-default'>download your data</a>";
} else if ($showId!=0 && $user!=0) {
	$pageTitle = " - ".$showInfo->n_show;
	if ($showInfo->is_deleted) {
		$ret.="You have stopped watching this show.<br /><form method='post' action=''><input type='hidden' name='id_show' value='".$showId."' /><input type='hidden' name='formType' value='show undelete' /><button type='submit' class='btn btn-success'><i class='glyphicon glyphicon-refresh icon-grey'></i> undelete</button></form>";
	} else {
 		$row = query("SELECT Min(If(shows_episodes.season=".($showInfo->c_season ? $showInfo->c_season : "0")." AND shows_episodes.episode=".($showInfo->c_episode ? $showInfo->c_episode : "0").",title,Null)) AS title, Min(If(shows_episodes.season=".($showInfo->c_season ? $showInfo->c_season : "0")." AND shows_episodes.episode=".($showInfo->c_episode ? $showInfo->c_episode : "0").",id_episode_tvmaze,Null)) AS id_episode_tvmaze".($showInfo->next_episode ? ", Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff=".$showInfo->next_episode.", shows_episodes.season, Null)) AS season, Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff=".$showInfo->next_episode.", title, Null)) AS title_next, Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff=".$showInfo->next_episode.", id_episode_tvmaze, Null)) AS id_episode_tvmaze_next, Sum(If($timeToComp<=shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,1,0)) AS left_episodes, Sum(If(shows_episodes.season=".($showInfo->c_season ? $showInfo->c_season : "0")." AND $timeToComp<=shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,1,0)) AS left_this_season" : "")." FROM (shows INNER JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone WHERE shows.id_show = ".$showInfo->id_show." GROUP BY shows.id_show")->fetch_object();
 		$episodes = query("SELECT * FROM shows_episodes WHERE id_show = ".$showInfo->id_show.($showInfo->season || $showInfo->episode ? " AND (season>".$showInfo->season." OR (season=".$showInfo->season." AND episode>".$showInfo->episode.")) ORDER BY season, episode" : ""));
		$special_episodes = query("SELECT * FROM shows_episodes_special WHERE id_show = ".$showInfo->id_show." ORDER BY date");
//		$row = query("SELECT * FROM shows_episodes WHERE id_show = ".$showInfo->id_show." AND season = ".$showInfo->c_season." AND episode = ".$showInfo->c_episode)->fetch_object();
		if ($showInfo->c_season) $title = " - \"<a href='http://www.tvmaze.com/episodes/".$row->id_episode_tvmaze."/d' target='_blank'>".$row->title."</a>\""; else $title="";
 		$ret.="<h2>".$showInfo->n_show."</h2>".$showInfo->full_status.", <a target='_blank' href='http://www.tvmaze.com/shows/".$showInfo->id_show_tvmaze."/d'>more info</a><br /><br />newest season: ".$showInfo->c_season.", episode: ".$showInfo->c_episode.$title;
 		if ($showInfo->next_episode) {
 			$ret.="<br />next ".($showInfo->c_season==$row->season ? "episode" : "season").": ".date("j-n-Y H:i", $showInfo->next_episode-$showInfo->episode_length+$userInfo->gmt_diff)."-".date("H:i", $showInfo->next_episode+$userInfo->gmt_diff)." - \"<a href='http://www.tvmaze.com/episodes/".$row->id_episode_tvmaze_next."/d' target='_blank'>".$row->title_next."\"</a><br />total announced episodes: ".$row->left_episodes.($row->left_this_season ? " (this season: ".$row->left_this_season.")" : "");
 		}
 		$ret.="<br /><form method='post' action='".$address."'> your season: <input type='text' name='season' id='season' value='".$showInfo->season."' autocomplete='off' class='number' />, episode: <input type='text' name='episode' id='episode' value='".$showInfo->episode."' autocomplete='off'  class='number' /><br /><br /><div>".
 				"<button type='submit' class='btn btn-success'><i class='glyphicon glyphicon-ok'></i> save</button>";
 		if (($showInfo->season!=$showInfo->c_season || $showInfo->episode!=$showInfo->c_episode) && $showInfo->c_season && $showInfo->c_episode) {
 			$ret.=" <button type='submit' class='btn btn-warning' onClick='$(\"#season\").val(".$showInfo->c_season.");$(\"#episode\").val(".$showInfo->c_episode.");'><i class='glyphicon glyphicon-fast-forward icon-grey'></i> copy & save</button>";
 		}
		$ret.="<input type='hidden' name='is_deleted' id='delete_show' value='0' /> <button type='submit' class='btn btn-danger' onClick='if(!confirm(\"Are you sure you want to stop watching this show?\"))return false;$(\"#delete_show\").val(\"1\")'><i class='glyphicon glyphicon-trash icon-grey'></i> delete</button><input type='hidden' name='formType' value='show' /><br /><br /><div class='btn-group'>".
 				"<a href='#' onclick='return showTab(\"settings\")' class='btn btn-default' id='settingsTab'>settings</a><a href='#' onclick='return showTab(\"notepad\")' class='btn btn-default' id='notepadTab'>notepad</a><a href='#' onclick='return showTab(\"comments\")' class='btn btn-default' id='commentsTab'>comments</a>".
 				($episodes->num_rows ? "<a href='#' onclick='return showTab(\"episodes\")' class='btn btn-default' id='episodesTab'>episodes</a>" : "").
 				($special_episodes->num_rows ? "<a href='#' onclick='return showTab(\"specialEpisodes\")' class='btn btn-default' id='specialEpisodesTab'>special episodes</a>" : "").
 				"</div> <br /><div id='settingsDiv'><label class='checkbox'><input type='checkbox' name='is_for_later' ".($showInfo->is_for_later==1 ? "checked='checked'" : "")." />is left for later</label>".
 				"<label class='checkbox'><input type='checkbox' name='is_full_season' ".($showInfo->is_full_season==1 ? "checked='checked'" : "")." />wait for full season</label><label class='checkbox'><input type='checkbox' name='is_hidden_from_brand_new' ".($showInfo->is_hidden_from_brand_new==1 ? "checked='checked'" : "")." />show in waiting instead of brand new</label><label class='checkbox'><input type='checkbox' name='show_links' ".($showInfo->show_links==1 ? "checked='checked'" : "")." />show download links for this show</label>";
 		foreach ($allLabels as $label) {
 			$slabel="is_".$label;
 			$ret.="<label class='checkbox'><input type='checkbox' name='is_".$label."' ".($showInfo->$slabel==1 ? "checked='checked'" : "")." /><i class='glyphicon glyphicon-".$label."'></i></label>";
 		}
 		$ret.="</div><div id='notepadDiv'><textarea class='note' name='note'>".$showInfo->note."</textarea></div><input type='hidden' name='id_show' value='".$showInfo->id_show."' /></div></form><div id='episodesDiv'>";
 		$status=0;
 		$lastSeason=1000;
 		$epsToWatch = false;
 		while ($row = $episodes->fetch_object()) {
 			if ($row->season<$showInfo->c_season || ($row->season==$showInfo->c_season && $row->episode<=$showInfo->c_episode)) {
 				if ($status<1) {
 					$status=1;
			 		$epsToWatch = true;
		 			$ret.="<h5>episodes to watch:</h5>";
			 		$lastSeason=1000;
 				}
 			} else {
 				if ($status<2) {
					$status=2;
		 			$ret.="<h5>announced episodes:</h5>";
			 		$lastSeason=1000;
				}
			}
			if ($lastSeason<$row->season) $ret.="<br />";
			$ret.="Season ".substr("0".$row->season,-2)." Episode ".substr("0".$row->episode,-2)." (".date("d-m-Y", $row->date+$showInfo->ep_diff+$userInfo->gmt_diff)
					.($row->season<$showInfo->c_season || ($row->season==$showInfo->c_season && $row->episode<=$showInfo->c_episode) ? ", <a href='#' onClick='return saveShow(".$row->season.", ".$row->episode.");'>viewed</a>".($showInfo->n_search=='DONT' ? "" : ", ").serviceLinks($showInfo->n_search, $row->season, $row->episode) : "")
					."): <a href='http://www.tvmaze.com/episodes/".$row->id_episode_tvmaze."/d' target='_blank'>".$row->title."</a><br />";
			$lastSeason=$row->season;
		}
		$status = 0;
		$ret.="</div><div id='specialEpisodesDiv'>";
		while ($row = $special_episodes->fetch_object()) {
			if ($row->date<$timeToComp) {
				if ($status<1) {
					$status=1;
					$ret.="<h5>special episodes:</h5>";
				}
			} else {
				if ($status<2) {
					$status=2;
					$ret.="<h5>announced special episodes:</h5>";
				}
			}
			$ret.="Season ".substr("0".$row->season,-2)." (".date("d-m-Y", $row->date+$showInfo->ep_diff+$userInfo->gmt_diff)
					."): <a href='http://www.tvmaze.com/episodes/".$row->id_episode_tvmaze."/d' target='_blank'>".$row->title."</a><br />";
		}
		$ret.="</div><div id='commentsDiv'><br />";
 		$comments = false;
		$ret.="<form action='' method='post' id='shows'><input type='hidden' name='formType' value='comment' /><input type='text' name='comment' placeholder='write a comment' /></form>";
 		$result = query("SELECT comments.*, users.login, users_shows.season as u_season, users_shows.episode as u_episode, comments_unread.id_comment AS unread FROM ((comments INNER JOIN users ON comments.id_user = users.id_user) LEFT JOIN users_shows ON comments.id_user = users_shows.id_user AND comments.id_show = users_shows.id_show) LEFT JOIN (SELECT * FROM comments_unread WHERE id_user = ".$userInfo->id_user.") AS comments_unread ON comments.id_comment = comments_unread.id_comment WHERE comments.id_show = ".$showInfo->id_show." AND comments.season * 1000 + comments.episode <= ".($showInfo->season*1000+$showInfo->episode)." ORDER BY comments.date DESC");
 		while ($row = $result->fetch_object()) {
 			$comments = true;
 			$ret.="<span class='po' title='".strftime("%H:%M %d/%m/%Y", $row->date)."' data-content='comment for S".substr('0'.$row->season, -2)."E".substr('0'.$row->episode, -2)."<br />user on S".substr('0'.$row->u_season, -2)."E".substr('0'.$row->u_episode, -2).($row->u_season*1000+$row->u_episode<$showInfo->season*1000+$showInfo->episode ? "<br />this user will not see your comment" : "")."'><b>".$row->login.($row->unread ? "" : "</b>").": ".$row->comment.($row->unread ? "</b>" : "")."</span><br />";
 			if ($row->unread) {
 				query("DELETE FROM comments_unread WHERE id_comment = ".$row->id_comment." AND id_user = ".$userInfo->id_user);
// 				echo "DELETE * FROM comments_unread WHERE id_comment = ".$row->id_comment." AND id_user = ".$userInfo->id_user;
 			}
 		}
 		if ($showInfo->logins!="")
 			$ret.="<h6>Other people watching this show:</h6>".str_replace(" (s00e00)", "", str_replace("s".substr('0'.$showInfo->c_season, -2)."e".substr('0'.$showInfo->c_episode, -2), "current", $showInfo->logins));
 		$ret.="</div>";
 		$script.="showTab('".($showInfo->note!='' ? "notepad" : ($epsToWatch ? "episodes" : ($comments ? "comments" : "")))."');"; //episodes
 	}
} else if ($page=='backup' && $user!=0) {
	header("Content-type: application/csv");
	echo "show;season;episode;deleted;notepad;is left for later;wait for full season;is hidden from brand new;";
	foreach($allLabels as $label) {
		echo $label.";";
	}
	echo "\n";
	$result = query("SELECT shows.n_show, users_shows.* FROM shows INNER JOIN users_shows on shows.id_show = users_shows.id_show WHERE users_shows.id_user = ".$user." ORDER BY shows.n_show");
	while ($row = $result->fetch_object()) {
		echo $row->n_show.";".$row->season.";".$row->episode.";".$row->is_deleted.";".str_replace(";", ",", str_replace("\n", " ", $row->note)).";".$row->is_for_later.";".$row->is_full_season.";".$row->is_hidden_from_brand_new.";";
		foreach($allLabels as $label) {
 			$slabel="is_".$label;
			echo $row->$slabel.";";
		}
		echo "\n";
	}
	exit;
} else if ($page=='stats' && ($user==1 || $user==6 || $user==36)) {
	$labels = "";
	foreach ($allLabels as $label) {
		$labels.=", SUM(is_".$label.") AS d_".$label;
	}
	$shows       = query("SELECT Count(*) AS i FROM shows         ")->fetch_object()->i;
	$episodes    = query("SELECT Count(*) AS i FROM shows_episodes")->fetch_object()->i;
	$users       = query("SELECT Count(*) AS i FROM users         ")->fetch_object()->i;
	$users_shows = query("SELECT Count(*) AS i".$labels." FROM users_shows WHERE NOT is_deleted")->fetch_object();
	$seasons     = query("SELECT Count(*) AS i FROM (SELECT id_show, season FROM shows_episodes GROUP BY id_show, season) AS d")->fetch_object()->i;
	$ret.="shows: ".$shows.", seasons per show: ".(floor(100*$seasons/$shows)/100).", episodes per show: ".(floor(100*$episodes/$shows)/100).", episodes per season: ".(floor(100*$episodes/$seasons)/100).", users: ".$users.":<br /><br />";
	$ret.="user activity:<table class='table table-hover table-striped'><thead><tr><th>login</th><th>id</th><th>shows</th><th>last activity</th><th>title</th>";
	foreach ($allLabels as $label) {
		$ret.="<th><i class='glyphicon glyphicon-".$label."'></i> (";
		$label="d_".$label;
		$ret.=$users_shows->$label.")</th>";
	}
	$ret.="</tr></thead>";
	$result = query("SELECT users.*, count(users_shows.id_show) as s".$labels." FROM users LEFT JOIN (SELECT users_shows.* FROM (users_shows INNER JOIN shows ON users_shows.id_show = shows.id_show) INNER JOIN shows_episodes ON shows.id_show = shows_episodes.id_show WHERE NOT is_deleted GROUP BY users_shows.id_user, users_shows.id_show, shows.canceled HAVING NOT shows.canceled OR Max(1000*shows_episodes.season+shows_episodes.episode)<>1000*users_shows.season+users_shows.episode) AS users_shows ON users.id_user = users_shows.id_user WHERE users.id_user<>0 GROUP BY users.login ORDER BY users.last_activity DESC");
	while ($row = $result->fetch_object()) {
		$ret.="<tr><td><a href='/user_".$row->login."'>".$row->login."</a></td><td>".$row->id_user."</td><td>".$row->s."</td><td>".date("G:i j-n-Y",$row->last_activity+$userInfo->gmt_diff)."</td><td>".$row->week_show_titles."</td>";
		foreach ($allLabels as $label) {
			$label="d_".$label;
			$ret.="<td>".($row->$label ? $row->$label : "")."</td>";
		}
		$ret.="</tr>";
	}
	$ret.="</table>";
} else if ($page=='user compare') {
	$ret.="<table class='table table-striped'><thead><tr><td>show</td><td>me</td><td>".$userToComp->login."</td></tr></thead><tbody>";
	$labels="";
	foreach ($allLabels as $label) {
		$labels.=", me_shows.is_".$label." as me_".$label.", he_shows.is_".$label." as he_".$label;
	}
	$result = query("SELECT shows.*".$labels.", me_shows.id_show as me_show, me_shows.season AS me_season, me_shows.episode AS me_episode, he_shows.id_show as he_show, he_shows.season AS he_season, he_shows.episode AS he_episode, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,1000*shows_episodes.season+shows_episodes.episode,Null)) DIV 1000 AS c_season, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,1000*shows_episodes.season+shows_episodes.episode,Null)) MOD 1000 AS c_episode, me_shows.is_for_later AS me_for_later, he_shows.is_for_later as he_for_later FROM ".
		"(((shows INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone) LEFT JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) LEFT JOIN (SELECT * FROM users_shows WHERE id_user = ".$user." AND Not is_deleted) AS me_shows ON shows.id_show = me_shows.id_show) INNER JOIN (SELECT * FROM users_shows WHERE id_user = ".$userToComp->id_user." AND Not is_deleted) AS he_shows ON shows.id_show = he_shows.id_show GROUP BY shows.id_show ORDER BY shows.n_show");
	while ($row = $result->fetch_object()) {
//		$me = ($row->me_season && $row->me_episode ? ($row->me_season==$row->c_season && $row->me_episode==$row->c_episode ? 2 : 1) : 0);
//		$he = ($row->he_season && $row->he_episode ? ($row->he_season==$row->c_season && $row->he_episode==$row->c_episode ? 2 : 1) : 0);
		if (isset($_GET['all']) || (($row->me_season!=$row->he_season || $row->me_episode!=$row->he_episode) && $row->he_show))
			$ret.="<tr><td>".$row->n_show."</td><td>".
				($row->me_show ? ($row->me_season==$row->c_season && $row->me_episode==$row->c_episode ? ($row->canceled ? "<span class='label label-success'>finished</span>" : "<span class='label label-success'>current</span>") : ($row->me_for_later ? "<span class='label label-info'>left for later</span>" : "<span class='label label-warning'>season ".($row->me_season ? $row->me_season : "0")." episode ".($row->me_episode ? $row->me_episode : "0")."</span>")) : "<span class='label label-danger'>not watching</span>").getLabels($row, "me_")."</td><td>".
				($row->he_show ? ($row->he_season==$row->c_season && $row->he_episode==$row->c_episode ? ($row->canceled ? "<span class='label label-success'>finished</span>" : "<span class='label label-success'>current</span>") : ($row->he_for_later ? "<span class='label label-info'>left for later</span>" : "<span class='label label-warning'>season ".($row->he_season ? $row->he_season : "0")." episode ".($row->he_episode ? $row->he_episode : "0")."</span>")) : "<span class='label label-danger'>not watching</span>").getLabels($row, "he_")."</td></tr>";
	}
	$ret.="</tbody></table>";
	if (!isset($_GET['all']))
		$ret.="<a href='".$address."user_".$userToComp->login."/?all' class='btn btn-success'>show all shows</a>";
} else if ($page=='ical') {
	if (!isset($_GET['key']))
		exit;
	$result = query("SELECT users.*, timezones.gmt_diff FROM users INNER JOIN timezones ON users.id_timezone = timezones.id_timezone WHERE ical_key='".addslashes($_GET['key'])."'");
	if (!$userInfo = $result->fetch_object())
		exit;
	header('Content-Type: text/html; charset=utf-8');
	header('Content-Disposition: inline; filename="calendar.ics"');
	echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//seriousseri.es//NONSGML v1.0//EN\r\nX-WR-CALNAME:SeriousSeri.es\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
	$result = query("SELECT shows.n_show, If(users_shows.show_links, shows.n_search, 'DONT') AS n_search, shows_episodes.*, shows.runtime, shows.airtime, timezones.gmt_diff FROM ((users_shows INNER JOIN shows ON users_shows.id_show = shows.id_show) INNER JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone WHERE NOT (users_shows.is_deleted OR users_shows.is_for_later OR users_shows.is_full_season) AND users_shows.id_user=".$userInfo->id_user." AND shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".(31*DAY).">".$timeToComp.($userInfo->ical_only_first_episode ? " AND shows_episodes.episode=1" : "")." ORDER BY shows_episodes.date+shows.airtime-timezones.gmt_diff, shows_episodes.season, shows_episodes.episode ASC");
	while ($row = $result->fetch_object()) {
		$dst = (date('I', $row->date+$row->airtime-$row->gmt_diff) ? 3600 : 0);
		if ($userInfo->ical_hours) {
			echo "BEGIN:VEVENT\r\nUID:".$row->id_show."S".$row->season."E".$row->episode."@seriousseri.es\r\nDTSTART:".date("Ymd\THis", $row->date+$row->airtime-$row->gmt_diff-$dst)."Z\r\nDTEND:".date("Ymd\THis", $row->date+$row->airtime-$row->gmt_diff+$row->runtime-$dst)."Z\r\nSUMMARY:".str_replace(",", "\,", $row->n_show).
				"\r\nDESCRIPTION:Season ".$row->season." Episode ".$row->episode.": '".str_replace(",", "\,", str_replace(";", "\;", $row->title."'\\n\\nVisit the site: http:".$address."\\nView the show: http:".$address.showNameForUrl($row->n_show)."\\nSet show as viewed: http:".$address.showNameForUrl($row->n_show)))."/update";
			if ($row->n_search!='DONT') {
				foreach ($services as $key => $service) {
					if (strpos("|".$userInfo->services_off."|", "|".$key."|")===false)
						echo str_replace(",", "\,", str_replace(";", "\;", "\\n\\n".$service['name'].": ".str_replace(" ", "%20", serviceLink($service, $row->n_search, $row->season, $row->episode))));
				}
			}
			echo "\r\nEND:VEVENT\r\n";
		} else {
			echo "BEGIN:VEVENT\r\nUID:".$row->id_show."S".$row->season."E".$row->episode."@seriousseri.es\r\nDTSTART;VALUE=DATE:".date("Ymd", $row->date+$row->airtime-$row->gmt_diff+$row->runtime-$dst)."\r\nSUMMARY:".str_replace(",", "\,", $row->n_show).
				"\r\nDESCRIPTION:Season ".$row->season." Episode ".$row->episode.": '".str_replace(",", "\,", str_replace(";", "\;", $row->title."'\\n\\nVisit the site: http:".$address."\\nView the show: http:".$address.showNameForUrl($row->n_show)."\\nSet show as viewed: http:".$address.showNameForUrl($row->n_show)))."/update";
			if ($row->n_search!='DONT') {
				foreach ($services as $key => $service) {
					if (strpos("|".$userInfo->services_off."|", "|".$key."|")===false)
						echo str_replace(",", "\,", str_replace(";", "\;", "\\n\\n".$service['name'].": ".str_replace(" ", "%20", serviceLink($service, $row->n_search, $row->season, $row->episode))));
				}
			}
			echo "\r\nDURATION:P1D\r\nEND:VEVENT\r\n";
		}
	}
	echo "END:VCALENDAR";
	exit;
} else if ($page=='week') {
	$days = DAYS_TOTAL;
	$colIsLabeled=array();
	if ($user) {
		$result = query($userEpisodesSQL."WHERE NOT users_shows.is_deleted AND users_shows.id_user = ".$user." GROUP BY shows.id_show, If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,-1,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff) HAVING ($newEpisodesSQL>0".($userInfo->week_new_limit ? " AND $newEpisodesSQL<=".$userInfo->week_new_limit : "").") OR ($newEpisodesSQL=0 AND Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp OR shows_episodes.season*1000+shows_episodes.episode<=users_shows.season*1000+users_shows.episode,Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)) Is Not Null AND Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp OR shows_episodes.season*1000+shows_episodes.episode<=users_shows.season*1000+users_shows.episode,Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff))-".(($days-1)*DAY)."<".$timeToComp.") ORDER BY shows.n_show, shows_episodes.date");
	} else {
		$result = query("SELECT shows.*, shows.airtime-timezones.gmt_diff AS episode_start, shows.runtime AS episode_length, Null AS c_season, Null AS c_episode, shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff AS next_episode, 0 as new_episodes, 0 as real_for_later, sum(1) AS no_of_eps, group_concat(shows_episodes.title ORDER BY shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff, shows_episodes.season, shows_episodes.episode separator '; ') as title FROM (shows INNER JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone WHERE shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY.">".$timeToComp." AND shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff-".(($days-1)*DAY)."<".$timeToComp." GROUP BY shows.id_show, shows_episodes.date ORDER BY shows.n_show, shows_episodes.date");
	}
	$show = 0; $retT="";
	while ($row = $result->fetch_object()) {
		if ($show!=$row->id_show) {
			if ($show!=0)
				$retT.=drawCols($col, $days - 1)."</tr>";
			$class='';
			if ($row->real_for_later)
				$class.=" forlater";
			elseif ($row->new_episodes>1)
				$class.=" waiting";
			if ($row->new_episodes==0 && ($row->next_episode - $timeToComp + $userInfo->gmt_diff) > DAY * DAYS_TO_SHOW)
				$class.=" hiddenLate";
			$retT.="<tr id='showW".$row->id_show."' class='".($row->real_for_later && (isset($_COOKIE['forlater']) ? $_COOKIE['forlater'] : 'on') == 'off' ? "hRow " :
				($row->new_episodes>1 && (isset($_COOKIE['waiting']) ? $_COOKIE['waiting'] : 'on') == 'off' ? "hRow " : "")).($class ? substr($class, 1) : "").
				"' ".($user ? "onClick='window.location=\"".$address.showNameForUrl($row->n_show)."\"'" : "").
				"><td style='text-align:center;white-space:nowrap'><div><a href='".($user ? $address.showNameForUrl($row->n_show) : "")."'>".
				$row->n_show.($userInfo->show_flags && $row->country ? " <img src='".$cdnaddress."flags/".strtolower(substr($row->country, 0, 2)).".png' alt='".$row->country."' /> " : "").($userInfo->week_labels_left ? getLabels($row) : "")."</a></div></td>";
			$script.=createShowArray($row);
			$col = 0;
			$show=$row->id_show;
			$forLaterRow = $row->real_for_later;
			$waitingRow = $row->new_episodes > 1;
		}
		if ($userInfo->week_labels_center)
			$labels = getLabels($row);
		else
			$labels = "";
		if ($row->new_episodes>0) {
			$title = strtolower((strlen($row->next_title)>$userInfo->week_show_titles ? substr($row->next_title, 0, $userInfo->week_show_titles-1)."..." : $row->next_title));
			if ($row->new_episodes>1 || $row->is_hidden_from_brand_new) {
				$class="danger";
				$val1=($userInfo->week_show_titles ? $title." (x".$row->new_episodes.")" : "new episodes: ".$row->new_episodes);
				$val2=$row->new_episodes."<i class='glyphicon glyphicon-film'></i>";
			} else if ($timeToComp-DAY<$row->prev_episode) {
				$class="success";
				$val1=($userInfo->week_show_titles ? $title : "new episode!!!");
				$val2="<i class='glyphicon glyphicon-film'></i>";
			} else {
				$class="warning";
				$val1=($userInfo->week_show_titles ? $title : "new episode");
				$val2="<i class='glyphicon glyphicon-film'></i>";
			}
			$retT.="<td class='col col0 notempty'><div title='".strtolower(str_replace("'", "\"", $row->next_title))."'>";
			$retT.="<span class='visible-lg label label-".$class."'>".$val1.$labels."</span>";
			$retT.="<span class='hidden-lg label label-".$class."'>".$val2.$labels."</span>";
			$retT.="</div></td>";
			$col=1;
		} else {
			$day = floor(($row->next_episode+$userInfo->gmt_diff+7200)/DAY) - floor(($timeToComp+$userInfo->gmt_diff)/DAY);
//			echo $row->n_show." ".$col." ".$day."\n";
			if ($col>$day) $day=$col;
			$retT.=drawCols($col, $day - 1)."<td class='col col".$day." notempty'><div title='".strtolower(str_replace("'", "\"", $row->title))."'>";
			$retT.="<span class='label label-default visible-lg'>".($userInfo->week_show_titles ? strtolower((strlen($row->title)>$userInfo->week_show_titles ? substr($row->title, 0, $userInfo->week_show_titles-1)."...".($row->no_of_eps>1 ? "(x".$row->no_of_eps.")" : "") : $row->title)) : "episode".($row->no_of_eps>1 ? "s: ".$row->no_of_eps : "")).$labels."</span>";
			$retT.="<span class='label label-default hidden-lg'>".($row->no_of_eps>1 ? $row->no_of_eps : "")."<i class='glyphicon glyphicon-film'></i>".$labels."</span>";
			$retT.="</div></td>";
			$col=$day+1;
		}
		if ($user) {
			foreach ($allLabels as $label) {
				$slabel = "is_".$label;
				if ($row->$slabel) {
					$colIsLabeled[$label."_".($col-1)]=true;
				}
			}
		}
	}
	if ($show!=0)
		$retT.=drawCols($col, $days - 1)."</tr>";
	$ret.="<table id='week-table' class='table table-hover'><thead><tr><td>&nbsp;</td>";
	$ret.="<td class='col col0'>Today".($userInfo->week_labels_top ? getLabelsCol($colIsLabeled, 0) : "")."</td>";
	if ($userInfo->week_labels_top) {
		for ($i=0;$i<$days-1;$i++) {
			$ret.="<td class='col col".($col+1)."'>";
			$dateLab=date("j/n", $timeToComp+($i+1)*DAY).($userInfo->week_labels_top ? getLabelsCol($colIsLabeled, $i+1) : "");
			$ret.="<span style='white-space:nowrap' class='visible-lg'>".substr(date("l", $timeToComp+($i+1)*DAY),0,3)." ".$dateLab."</span>";
			$ret.="<span style='white-space:nowrap' class='hidden-lg'>".$dateLab."</span></td>";
		}
	} else {
		$ret.="<script>var days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];for (i=0;i<".($days-1).";i++){d=new Date((".$timeToComp."+(i+1)*".DAY.")*1000);document.write(\"<td class='col col\"+(i+1)+\"'>";
		$ret.="<span style='white-space:nowrap' class='visible-lg'>\"+days[d.getDay()]+\" \"+d.getDate()+\"/\"+(d.getMonth()+1)+\"</span>";
		$ret.="<span style='white-space:nowrap' class='hidden-lg'>\"+d.getDate()+\"/\"+(d.getMonth()+1)+\"</span>";
		$ret.="</td>\")}</script>";
	}
	$ret.="</tr></thead><tbody>".$retT."</tbody></table>";
	$script.="var week_hide_cols=".($userInfo->week_hide_cols ? "true" : "false").";";
	if ($userInfo->show_week)
		$menu.="<li style='width:100px'><a href='".$address."list/'>list view</a></li>";
	else
		$menu.="<li style='width:100px'><a href='".$address."'>list view</a></li>";
	if ($user) {
		$menu.="<li class='dropdown'><a href='#' onClick='return false' id='toggle'>toggle lists<b class='caret'></b></a><ul class='dropdown-menu'>";
		$menu.="<li ".((isset($_COOKIE['waiting'])  ? $_COOKIE['waiting']  : 'on') == 'off' ? "" : "class='active'")." id='waitingLabel'>  <a href='#' onClick='return showHide(\"waiting\");'> waiting shows  </a></li>";
		$menu.="<li ".((isset($_COOKIE['forlater'])  ? $_COOKIE['forlater']  : 'on') == 'off' ? "" : "class='active'")." id='forlaterLabel'>  <a href='#' onClick='return showHide(\"forlater\");'> shows for later </a></li>";
		$menu.="</ul></li>";
	}
} else if ($page=='list') {
	if ($user) {
		$result = query($userEpisodesSQL."WHERE NOT users_shows.is_deleted AND users_shows.id_user = ".$user." GROUP BY shows.id_show ORDER BY If($isForLaterSQL,".BIG_NUMBER_1.",If($newEpisodesSQL>0,If($newEpisodesSQL=1 AND Not users_shows.is_hidden_from_brand_new,-".BIG_NUMBER_1.",".($userInfo->list_sort_waiting_by_episodes_no ? "$newEpisodesSQL-".BIG_NUMBER_1 : "-1")."),IfNull(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".$userInfo->gmt_diff."<$timeToComp OR shows_episodes.season*1000+shows_episodes.episode<=users_shows.season*1000+users_shows.episode,Null,floor((shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".$userInfo->gmt_diff."+7200)/".DAY."))),If(shows.canceled,".BIG_NUMBER_3."-Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".$userInfo->gmt_diff.">".$timeToComp.",Null,floor((IfNull(shows_episodes.date,0)+shows.airtime+shows.runtime-timezones.gmt_diff+".$userInfo->gmt_diff.")/".DAY."))),".BIG_NUMBER_2.")))), shows.n_show");
	} else {
		$result = query("SELECT shows.*, 0 as new_comments, 0 as is_hidden_from_brand_new, shows.airtime-timezones.gmt_diff AS episode_start, shows.runtime AS episode_length, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)) AS last_episode, Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null)) AS next_episode, shows.airtime-timezones.gmt_diff AS episode_start, shows.runtime-timezones.gmt_diff AS episode_length, '' AS next_title, IF(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY.">".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null))<$timeToComp,1,0) as new_episodes, Null AS c_season, Null AS c_episode, 0 as real_for_later FROM (shows LEFT JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone GROUP BY shows.id_show ORDER BY If(IF(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY.">".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null))<$timeToComp,1,0)=1, -1, IfNull(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null)), If(shows.canceled,".BIG_NUMBER_2."-IfNull(Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)),0),".BIG_NUMBER_1."))), shows.n_show");
//		echo "SELECT shows.*, 0 as new_comments, 0 as is_hidden_from_brand_new, shows.airtime-timezones.gmt_diff AS episode_start, shows.runtime AS episode_length, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)) AS last_episode, Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null)) AS next_episode, shows.airtime-timezones.gmt_diff AS episode_start, shows.runtime-timezones.gmt_diff AS episode_length, '' AS next_title, IF(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY.">".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null))<$timeToComp,1,0) as new_episodes, Null AS c_season, Null AS c_episode, 0 as real_for_later FROM (shows LEFT JOIN shows_episodes ON shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone GROUP BY shows.id_show ORDER BY If(IF(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff+".DAY.">".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null))<$timeToComp,1,0)=1, -1, IfNull(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null)), If(shows.canceled,".BIG_NUMBER_2."-IfNull(Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)),0),".BIG_NUMBER_1."))), shows.n_show";
	}
	$status = 0; $list = ""; $forms = ""; $lastDate = 0;
	$newComments = array(0, 0, 0, 0, 0, 0, 0);
	while ($row = $result->fetch_object()) {
		if ($status<=1 && $row->new_episodes==1 && !$row->is_hidden_from_brand_new) {
			if ($status<1)
				$list.="<div><h4>brand new shows:</h4>".($user ? "<h5 id='brand_new'>&nbsp;</h5>" : "")."<ul>";
			$status=1;
		} else if ($status<=4 && $row->real_for_later) {
			if ($status>0 && $status<4) $list.="</ul></div>";
			if ($status<4) $list.="<div class='forlater'".((isset($_COOKIE['forlater']) ? $_COOKIE['forlater'] : 'on') == 'off' ? " style='display:none'" : ""). "><h4>shows for later:</h4><ul>";
			$status = 4;
		} else if ($status<=2 && $row->new_episodes) {
			if ($status>0 && $status<2) $list.="</ul></div>";
			if ($status<2) $list.="<div class='waiting'".((isset($_COOKIE['waiting']) ? $_COOKIE['waiting'] : 'on') == 'off' ? " style='display:none'" : ""). "><h4>waiting shows:</h4><ul>";
			$status = 2;
		} else if ($status<=3 && $row->next_episode) {
			if ($status>0 && $status<3) $list.="</ul></div>";
			if ($status<3) $list.="<div class='upcoming'".((isset($_COOKIE['upcoming']) ? $_COOKIE['upcoming'] : 'on') == 'off' ? " style='display:none'" : ""). "><h4>upcoming shows:</h4><ul>";
			$status = 3;
		} else {
			$showUnknown=true;
			if ($status>0 && $status<5) $list.="</ul></div>";
			if ($status<5) $list.="<div class='unknown'".((isset($_COOKIE['unknown']) ? $_COOKIE['unknown'] : 'on') == 'off' ? " style='display:none'" : ""). ">";
			if ($status<5 && !$row->canceled) {
				$list.="<h4>unknown shows:</h4><ul>";
				$status = 5;
			} elseif ($status<6 && $row->canceled) {
				if ($status==5)
					$list.="</ul>";
				$list.="<h4>canceled shows (by last episode):</h4><ul>";
				$status = 6;
			}
		}
		$list.="<li".($status==3 && $lastDate && date("d.m.Y", ($lastDate+$userInfo->gmt_diff))!=date("d.m.Y", ($row->next_episode+$userInfo->gmt_diff)) && $userInfo->list_day_margin ? " style='margin-top:".$userInfo->list_day_margin."px'" : "").">".
					($row->next_episode && !$row->new_episodes ? date("d.m".($row->next_episode>$timeToComp+300*DAY ? ".Y" : ""), $row->next_episode+$userInfo->gmt_diff).
					($row->next_episode<$timeToComp+$userInfo->list_days*DAY ? ", ".substr(date("l", $row->next_episode+$userInfo->gmt_diff),0,100) : "").": " : "").
					($status==6 ? ($row->last_episode ? date("d.m.Y", $row->last_episode+$userInfo->gmt_diff) : "never aired").": " : "").
					"<a href='".($user ? $address.showNameForUrl($row->n_show) : "")."' id='showL".$row->id_show."'>".$row->n_show.($userInfo->show_flags && $row->country ? " <img src='".$cdnaddress."flags/".strtolower(substr($row->country, 0, 2)).".png' alt='".$row->country."' /> " : "").($user ? getLabels($row) : "");
		$newComments[0] += $row->new_comments;
		$newComments[$status] += $row->new_comments;
		if ($userInfo->list_show_last_episode && !$row->canceled && $status<5 && (!$row->next_episode || ($row->left_episodes ? $row->left_episodes : 0)==1)) {
			if ($status!=3 || $row->next_episode<time()+3*DAY)
				$list.=" <i class='glyphicon glyphicon-step-forward'></i>";
			else {
				$q = query("SELECT Count(*)/".($row->c_season-1)." as c FROM shows_episodes WHERE id_show=".$row->id_show." AND season<".$row->c_season." GROUP BY id_show")->fetch_object();
				if ($q) {
					if ($q->c-1<=$row->c_episode)
						$list.=" <i class='glyphicon glyphicon-step-forward'></i>";
				}
			}
		}
		$list.=($row->id_show_tvmaze==0 ? " <i class='glyphicon glyphicon-alert'></i>" : "").($row->canceled && $status!=6 ? " <i class='glyphicon glyphicon-remove'></i>" : "")."</a>".
					(($status==2 || $status==4) && $row->new_episodes>1 ? " (".$row->new_episodes." episodes)" : "").
					((($row->new_episodes==1 && $status < 3) || $status==3) && $userInfo->list_show_titles && $row->next_title ? " (".$row->next_title.")" : "").
					"</li>";
		$lastDate=$row->next_episode;//($row->left_episodes==0 ? "new season, " : ($row->left_episodes==1 ? "season finale, " : "")).
		$script.=createShowArray($row);
	}
	$result->close();
	if ($status) $list.="</ul></div>";
	if (!$userInfo->show_week)
		$menu.="<li style='width:100px'><a href='".$address."week/'>week view</a></li>";
	else
		$menu.="<li style='width:100px'><a href='".$address."'>week view</a></li>";
	$menu.="<li class='dropdown'><a href='#' onClick='return false' id='toggle'>toggle lists<b class='caret'></b></a><ul class='dropdown-menu'>";
	if ($user)
		$menu.="<li ".((isset($_COOKIE['waiting'])  ? $_COOKIE['waiting']  : 'on') == 'off' ? "" : "class='active'")." id='waitingLabel'>  <a href='#' onClick='return showHide(\"waiting\");'>waiting shows".($newComments[2]>0 ? " <span class='label label-info label-large po' data-content='new comments'>".$newComments[2]."</span>" : "")."</a></li>";
	$menu.="<li ".((isset($_COOKIE['upcoming']) ? $_COOKIE['upcoming'] : 'on') == 'off' ? "" : "class='active'")." id='upcomingLabel'> <a href='#' onClick='return showHide(\"upcoming\");'>upcoming shows".($newComments[3]>0 ? " <span class='label label-info label-large po' data-content='new comments'>".$newComments[3]."</span>" : "")."</a></li>";
	if ($user)
		$menu.="<li ".((isset($_COOKIE['forlater']) ? $_COOKIE['forlater'] : 'on') == 'off' ? "" : "class='active'")." id='forlaterLabel'> <a href='#' onClick='return showHide(\"forlater\");'>shows for later".($newComments[4]>0 ? " <span class='label label-info label-large po' data-content='new comments'>".$newComments[4]."</span>" : "")."</a></li>";
	$menu.="<li ".((isset($_COOKIE['unknown'])  ? $_COOKIE['unknown']  : 'on') == 'off' ? "" : "class='active'")." id='unknownLabel'>  <a href='#' onClick='return showHide(\"unknown\");'>unknown shows".($newComments[5]+$newComments[6]>0 ? " <span class='label label-info label-large po' data-content='new comments'>".($newComments[5]+$newComments[6])."</span>" : "")."</a></li>";
	$menu.="</ul></li>";
	$ret.=$list.$forms;
	if ($newComments[0]>0)
		$menuC=" <span class='label label-info label-large po' data-content='new comments'>".$newComments[0]."</span>";
} else if ($page=='login') {
	$ret.=$loginForm;
}
if ($page!="logout" && $page!="logoutAndClear" && $user!=0) {
	if ($page!="settings")
		$menu.="<li style='width:75px'><a href='".$address."settings'>settings</a></li>";
	$menu.="<li class='dropdown'><a href='".$address."logout'>logout (".$userInfo->login.")</a><ul class='dropdown-menu'><li><a href='".$address."logoutAndClear'>clear autologin</a></li></ul></li></ul>";
} else {//<li class='divider-vertical'></li>
	$menu.=$shortLoginForm;
}

function createShowArray($row) {
global $idShowArray, $userInfo;
	return "showArray[".$idShowArray++."]=[".$row->id_show.",".$row->id_show_tvmaze.",".($row->new_episodes ? $row->new_episodes : 0).
				",'".str_replace("&", "and", str_replace("'", "\\'", $row->n_search))."',".($row->c_season ? $row->c_season : 0).
				",".($row->c_episode ? $row->c_episode : 0).",'".showNameForUrl($row->n_show)."', '".
				substr("0".(floor(($row->episode_start+$userInfo->gmt_diff)/3600)%24),-2).":".substr("0".(floor(($row->episode_start+$userInfo->gmt_diff)/60)%60),-2)."-".
				substr("0".(floor(($row->episode_start+$row->episode_length+$userInfo->gmt_diff)/3600)%24),-2).":".substr("0".(floor(($row->episode_start+$row->episode_length+$userInfo->gmt_diff)/60)%60),-2)."'];";
}

if (($page=='reload' || count($_POST)) && $user) {
	echo "<html><head><title>SeriousSeri.es</title><meta http-equiv='refresh' content='0'></head><body>";
	if ($page=='reload')
		echo "please wait a sec...<script type='text/javascript'>document.location = '".$address."';</script>";
	else
		echo "please wait a sec...<script type='text/javascript'>document.location = document.location;</script>";//.reload(true);
	echo "</body></html>";
	exit;
}

if ($user==1) {
	$last = query("SELECT Min(last_update) AS last_check FROM shows".($showId==0 ? " WHERE id_show_tvmaze>0" : " WHERE id_show = ".$showId))->fetch_object()->last_check;
	$ret.=($last<time()-DAY ? "<h1>ERROR?</h1>" : "")."<!--last update: ".date("G:i j-n-Y", $last+$userInfo->gmt_diff)."<br />time generated: ".substr(microtime(true)-$timer, 0, 5)." sek<br />queries: ".$queryNum."--><!-- $debug -->";
}
echo "<!DOCTYPE html><html lang='en'><head><title>SeriousSeri.es".$pageTitle."</title><script>var showArray=new Array();var user_id=".$userInfo->id_user.";function drawCols(s,e){for(i=s;i<=e;i++)document.write(\"<td class='col col\"+i+\"'><div>&nbsp;</div></td>\");}</script>".
"<link rel='shortcut icon' href='".$cdnaddress."favicon.ico' type='image/x-icon' /><meta charset='utf-8' /><meta name='viewport' content='width=device-width, initial-scale=1.0' />".
"<style>body{padding:60px 0px 10px;}</style><link  href='//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/".bootstrapVersion."/css/bootstrap.min.css' rel='stylesheet' /><meta name='theme-color' content='#BBBB00'>".
"<link  href='".$cdnaddress."style.css' rel='stylesheet' /><meta http-equiv='refresh' content='3600' /></head><body>".($page == "week" ? "<div class='loading_wrap'><div>Loading, please wait...</div></div>" : "").
"<nav class='navbar navbar-inverse navbar-fixed-top' role='navigation'><div class='container'>".
"<div class='navbar-header'><button type='button' class='navbar-toggle' data-toggle='collapse' data-target='.navbar-responsive-collapse'><span class='icon-bar'></span><span class='icon-bar'></span><span class='icon-bar'></span>".
"</button><a class='navbar-brand' href='".$address."'>SeriousSeri<span style='font-size:50%'>.</span>es".$menuC."</a></div><div class='navbar-collapse collapse navbar-responsive-collapse'><ul class='nav navbar-nav menu'>".$menu."</div></div></nav><div class='container".($page=='week' ? "-fluid" : "")."'>".$ret."</div>".
"<script src='//cdnjs.cloudflare.com/ajax/libs/jquery/".jQueryVersion."/jquery.min.js'></script><script src='//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/".bootstrapVersion."/js/bootstrap.min.js'></script>".
"<script src='".$cdnaddress."script.js'></script><script type='text/javascript'>".arrayToJs().$script."</script>".
"</body></html>";
$mysql->close();

//istnieją i zostają w miejscu: -&:
//jeszcze do użytku mamy: .$, (do sprawdzenia)
//w razie co można podwójne...
function showNameForUrl($str) {
	return str_replace(" ", "_", str_replace("/", "~", str_replace("'", ";", str_replace("&", "&amp;", $str))));
}
function showNameFromUrl($str) {
	return str_replace("_", " ", str_replace("~", "/", str_replace(";", "\'", $str)));
}
//SELECT replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(replace(group_concat(n_show separator ''), 'a', ''), 'b', ''), 'c', ''), 'd', ''), 'e', ''), 'f', ''), 'g', ''), 'h', ''), 'i', ''), 'j', ''), 'k', ''), 'l', ''), 'm', ''), 'n', ''), 'o', ''), 'p', ''), 'q', ''), 'r', ''), 's', ''), 't', ''), 'u', ''), 'v', ''), 'w', ''), 'x', ''), 'y', ''), 'z', '') FROM `shows`
function check($row) {
global $mysql;
//	date_default_timezone_set('UTC');
	try {
		$canceled=0;$deleted=false;$airtimeCount=0;
		$info = json_decode(file_get_contents("http://api.tvmaze.com/shows/".$row->id_show_tvmaze));
		$episodes = json_decode(file_get_contents("http://api.tvmaze.com/shows/".$row->id_show_tvmaze."/episodes?specials=1"));
		foreach ($episodes as $episode) {
			$season 	 = $episode->season;
			$number    = $episode->number;
			$airdate   = $episode->airdate;
			$title     = $episode->name;
			$id_episode= $episode->id;
			$runtime   = $episode->runtime;
			if ($airtimeCount == 0)
				$airtime = $episode->airtime;
			$airtimeCount += ($airtime == $episode->airtime ? 1 : -1);
			if (substr($airdate, -2)!="00") {
				$time=strtotime($airdate);
				if (!$deleted) {
					query("DELETE FROM shows_episodes WHERE id_show=".$row->id_show);
					query("DELETE FROM shows_episodes_special WHERE id_show=".$row->id_show);
					$deleted=true;
				}
				if ($number)
					query("INSERT INTO shows_episodes (id_show, season, episode, date, title, id_episode_tvmaze) VALUES (".$row->id_show.", ".$season.", ".$number.", ".$time.", '".addslashes($title)."', ".$id_episode.")");
				else
					query("INSERT INTO shows_episodes_special (id_show, season, date, runtime, title, id_episode_tvmaze) VALUES (".$row->id_show.", ".$season.", ".$time.", ".$runtime.", '".addslashes($title)."', ".$id_episode.")");
			}
		}
		$name     = strtolower($info->name);
		//echo $name."\n";
		$status   = strtolower($info->status);
		if ($status == "ended") // || $status == "canceled/ended" || $status == "canceled" || $status == "pilot rejected")
			$canceled = 1;
		elseif ($status == "running" || $status == "to be determined" || "in development") // "returning series" || $status == "in development" || $status == "tpb/on the bubble" || $status == "tbd/on the bubble" || $status == "new series" || $status == "pilot ordered" || $status == "on hiatus" || $status == "final season")
			$canceled = 0;
		else {
			echo "unknown status: '".$status."'. contact kuba.\n";
			return false;
		}
		$runtime = $info->runtime*60;
		$network = $info->network->name;
		$country = $info->network->country->code;
		if ($airtime) {
			$airtime = preg_split("/:/", $airtime);
			$airtime = 60*(60*$airtime[0]+$airtime[1]);
		} else {
			$airtime = 0;
		}
		$timezone= $info->network->country->timezone;
		$timezoneid = query("SELECT * FROM timezones WHERE n_timezone = '".$timezone."'")->fetch_object();
		if (!$timezoneid) {
			query("INSERT INTO timezones (n_timezone) VALUES ('".$timezone."')");
			$timezoneid = query("SELECT * FROM timezones WHERE n_timezone = '".$timezone."'")->fetch_object();
		}
		$timezoneid = $timezoneid->id_timezone;
		if ($timezone) query("UPDATE timezones SET gmt_diff = ".(new DateTimeZone($timezone))->getOffset(new DateTime())." WHERE id_timezone = ".$timezoneid);
		query("UPDATE shows SET last_update = ".time().", canceled=".$canceled.", full_status='".$status."', n_tvmaze='".addslashes($name).($row->n_search ? "" : "', n_search='".addslashes($name))."', airtime=".$airtime.", runtime=".$runtime.", id_timezone=".$timezoneid.", country='".$country."', network='".$network."' WHERE id_show=".$row->id_show);
		return true;
	} catch(Exception $e) {
//		var_dump($e);
		echo $e->getMessage()."\n";
		return false;
	}
}

function setQueries($time = 0) {
global $newEpisodesSQL, $isForLaterSQL, $showInfoSQL, $userEpisodesSQL, $queryForMailSQL, $timeToComp, $userInfo;
//	if (!$time)$time = time();
	$timeToComp      = ($time ? $time : time())-$userInfo->gmt_diff;
	$newEpisodesSQL  = "Sum(If((users_shows.season<shows_episodes.season OR (users_shows.season=shows_episodes.season AND users_shows.episode<shows_episodes.episode) OR users_shows.season Is Null OR users_shows.episode Is Null) AND shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,1,0))";
	$isForLaterSQL   = "(users_shows.is_for_later OR (users_shows.is_full_season AND users_shows.season+2>IfNull(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,Null,shows_episodes.season)),0) AND (users_shows.season+1<>Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,shows_episodes.season,Null)) OR IfNull(Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)),-1)<>-1)))";
	$showInfoSQL     = "shows.n_show, shows.country, shows.airtime+shows.runtime-timezones.gmt_diff AS ep_diff, If(users_shows.show_links, shows.n_search, 'DONT') AS n_search, shows.id_show_tvmaze, shows.canceled, shows.full_status, users_shows.*, $isForLaterSQL AS real_for_later, $newEpisodesSQL AS new_episodes, Sum(If(users_shows.season=shows_episodes.season AND users_shows.episode<shows_episodes.episode,1,0)) AS left_episodes, Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp OR shows_episodes.season*1000+shows_episodes.episode<=users_shows.season*1000+users_shows.episode,Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)) AS next_episode, shows.airtime-timezones.gmt_diff AS episode_start, shows.runtime AS episode_length, If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,-1,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff) AS date, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff,Null)) AS last_episode, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,1000*shows_episodes.season+shows_episodes.episode,Null)) DIV 1000 AS c_season, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp,1000*shows_episodes.season+shows_episodes.episode,Null)) MOD 1000 AS c_episode, Max(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff>".$timeToComp.",Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)) AS prev_episode";
	$userEpisodesSQL = "SELECT $showInfoSQL, new_comments.new_comments, sum(1) AS no_of_eps, group_concat(shows_episodes.title order by shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff, shows_episodes.season, shows_episodes.episode separator '; ') as title, If($newEpisodesSQL=0 And Min(If(shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp OR shows_episodes.season*1000+shows_episodes.episode<=users_shows.season*1000+users_shows.episode,Null,shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff)) Is Null,'',(SELECT title FROM shows_episodes s WHERE s.id_show = shows.id_show AND 1000*s.season+s.episode > 1000*IfNull(users_shows.season,0)+IfNull(users_shows.episode,0) ORDER BY date LIMIT 1)) AS next_title FROM (((users_shows INNER JOIN shows ON users_shows.id_show = shows.id_show) LEFT JOIN shows_episodes ON users_shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone) LEFT JOIN (SELECT users_shows.id_show, count(*) AS new_comments FROM comments_unread INNER JOIN comments ON comments_unread.id_comment = comments.id_comment INNER JOIN users_shows ON comments_unread.id_user = users_shows.id_user AND comments.id_show = users_shows.id_show AND users_shows.season*1000+users_shows.episode>=comments.season*1000+comments.episode WHERE users_shows.id_user = ".$userInfo->id_user." GROUP BY users_shows.id_show) AS new_comments ON shows.id_show = new_comments.id_show ";
	$queryForMailSQL = "SELECT shows.n_show, If(users_shows.show_links, shows.n_search, 'DONT') AS n_search, shows_episodes.* FROM ((users_shows INNER JOIN shows ON users_shows.id_show = shows.id_show) INNER JOIN shows_episodes ON users_shows.id_show = shows_episodes.id_show) INNER JOIN timezones ON shows.id_timezone = timezones.id_timezone WHERE NOT users_shows.is_deleted AND NOT is_for_later AND NOT is_full_season AND shows_episodes.date+shows.airtime+shows.runtime-timezones.gmt_diff<$timeToComp AND 1000*shows_episodes.season+shows_episodes.episode>1000*IfNull(users_shows.season,0)+IfNull(users_shows.episode,0) AND users_shows.id_user = ";
}

function serviceLinks($show, $season, $episode, $quote="\"") {
	if ($show=='DONT')
		return "";
	global $services, $userInfo;$retL='';
	foreach ($services as $key => $service) {
		if (strpos("|".$userInfo->services_off."|", "|".$key."|")===false) {
			$retL.=($retL=="" ? "" : ", ")."<a target='_blank' href=".$quote.serviceLink($service, $show, $season, $episode).$quote.">".$service['name']."</a>";
		}
	}
	return "links: ".$retL;
}

function serviceLink($service, $show, $season, $episode) {
	if ($show=='DONT')
		return "";
	$show = str_replace(")", "%29", str_replace("(", "%28", str_replace("&", "and", $show)));
	$link = $service['link'];
	$link = str_replace('$name$',     $show,                        $link);
	$link = str_replace('$name_$',    str_replace(" ", "_", $show), $link);
	$link = str_replace('$season$',   $season,                      $link);
	$link = str_replace('$season0$',  substr("0".$season, -2),      $link);
	$link = str_replace('$episode$',  $episode,                     $link);
	$link = str_replace('$episode0$', substr("0".$episode, -2),     $link);
	return $link;
//	return $service['pre'].str_replace("&", "and", $show)." s".substr("0".$season, -2)."e".substr("0".$episode,-2).$service['suf'];
}

function drawCols($f, $t) {//, $daysT, $daysP
	if ($t+1<$f) return "<td>error. contact page administrator.</td>";
	if ($t<$f)   return "";
	return "<script>drawCols($f,$t)</script>";
//	return "<td>&nbsp;</td>".drawCols($f+1, $t); // class='col col$f'
}

function getLabels($row, $prefix="is_") {
global $allLabels;$ret="";
	foreach ($allLabels as $label) {
		$slabel=$prefix.$label;
		$ret.=($row->$slabel ? " <i class='glyphicon glyphicon-".$label."'></i>" : "");
	}
	if (isset($row->new_comments))
		if ($row->new_comments>0)
			$ret.=" <span class='label label-info new-comments'>new comments!";
	return $ret;
}

function getLabelsCol($colIsLabeled, $col) {
global $allLabels;$ret="";
	foreach ($allLabels as $label) {
		$slabel="is_".$label;
		$ret.=(isset($colIsLabeled[$label."_".$col]) ? " <i class='glyphicon glyphicon-".$label."'></i>" : "");
	}
	return $ret;
}
function query($q) {
global $mysql, $debug, $queryNum, $timer;
$debug.="\n[".(microtime(true)-$timer)."] ".$q;
	$queryNum++;
	if (substr($q, 0, 6) == "SELECT") {
		return $mysql->query($q);
	} else {
		$mysql->real_query($q);
	}
}
function arrayToJs() {
global $services, $userInfo;
	$ret = "";
	foreach ($services as $k => $s) {
		if (strpos("|".$userInfo->services_off."|", "|".$k."|")===false) {
			$ret.=($ret=="" ? "" : ",")."['".$k."'";
			foreach ($s as $v) {
				$ret.=",'".$v."'";
			}
			$ret.="]";
		}
	}
	return "var servicesArray=[".$ret."];";
}
