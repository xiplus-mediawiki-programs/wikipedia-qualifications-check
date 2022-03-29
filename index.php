<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>中文維基百科權限申請檢查</title>
	<style>
	table.bordered, .bordered th, .bordered td {
		border: 1px solid black;
		border-collapse: collapse;
		padding: 1px 5px;
	}
	</style>
</head>
<body>
<?php
date_default_timezone_set('UTC');
$api = 'https://zh.wikipedia.org/w/api.php';
$user = (isset($_GET["user"]) ? $_GET["user"] : "");
$type = (isset($_GET["type"]) ? $_GET["type"] : "");
$require = [
	"patroller" => [
		"registration" => false,
		"editcount" => 250,
		"firstedit" => strtotime("-30 days"),
		"active" => true,
		"block" => strtotime("-1 year"),
	],
	"rollbacker" => [
		"registration" => false,
		"editcount" => 1000,
		"firstedit" => strtotime("-90 days"),
		"active" => true,
		"block" => strtotime("-1 year"),
	],
	"autoconfirmed" => [
		"registration" => strtotime("-7 days"),
		"editcount" => 50,
		"firstedit" => false,
		"active" => false,
		"block" => false,
	],
	"autoconfirmed_tor" => [
		"registration" => strtotime("-90 days"),
		"editcount" => 100,
		"firstedit" => false,
		"active" => false,
		"block" => false,
	]
];
$rightname = [
	"patroller" => "巡查",
	"rollbacker" => "回退",
	"autoconfirmed" => "自動確認",
	"autoconfirmed_tor" => "自動確認（透過Tor編輯）"
];
?>
<form>
	<table>
		<tr>
			<td>現在時間：</td>
			<td><?=date('Y/m/d H:i')?></td>
		</tr>
		<tr>
			<td>使用者名稱：</td>
			<td>
				<input type="text" name="user" value="<?=htmlspecialchars($user)?>" required autofocus>
			</td>
		</tr>
		<tr>
			<td>權限：</td>
			<td>
				<select name="type">
					<option value="patroller" <?=($type=="patroller"?"selected":"")?>><?=$rightname["patroller"]?></option>
					<option value="rollbacker" <?=($type=="rollbacker"?"selected":"")?>><?=$rightname["rollbacker"]?></option>
					<option value="autoconfirmed" <?=($type=="autoconfirmed"?"selected":"")?>><?=$rightname["autoconfirmed"]?></option>
					<option value="autoconfirmed_tor" <?=($type=="autoconfirmed_tor"?"selected":"")?>><?=$rightname["autoconfirmed_tor"]?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td></td>
			<td><button type="submit">檢查</button></td>
		</tr>
	</table>
</form>
<?php
if ($user === "") {
	exit();
}

$url = $api.'?action=query&format=json&list=users&usprop=editcount%7Cregistration&ususers='.urlencode($user);
$res = file_get_contents($url);
if ($res === false) {
	exit("get user info fail");
}
$uinfo = json_decode($res, true);
$uinfo = $uinfo["query"]["users"][0];
if (isset($uinfo["missing"])) {
	exit("user not found");
}

if (!array_key_exists($type, $require)) {
	exit("type error");
}

$registration = strtotime($uinfo["registration"]);
$editcount = $uinfo["editcount"];

$url = $api.'?action=query&format=json&list=usercontribs&uclimit=1&ucdir=newer&ucuser='.urlencode($user);
$res = file_get_contents($url);
if ($res === false) {
	exit("get firstedit fail");
}

$firstedit = json_decode($res, true);
if (count($firstedit["query"]["usercontribs"]) === 0) {
	$firstedit = 0;
} else {
	$firstedit = $firstedit["query"]["usercontribs"][0];
	$firstedit = strtotime($firstedit["timestamp"]);
}

if ($registration > strtotime("-3 months")) {
	$activedays = floor((time()-$registration)/86400);
} else {
	$activedays = floor((time()-strtotime("-3 months"))/86400);
	$url = $api.'?action=query&format=json&list=usercontribs&uclimit='.$activedays.'&ucdir=older&ucuser='.urlencode($user);
	$res = file_get_contents($url);
	if ($res === false) {
		exit("get usercontribs fail");
	}
	$activeedit = json_decode($res, true);
	$activeedit = end($activeedit["query"]["usercontribs"]);
	$activeedit = strtotime($activeedit["timestamp"]);
}

$url = $api.'?action=query&format=json&list=logevents&utf8=1&letype=block&lelimit=1&letitle=User%3A'.urlencode($user);
$res = file_get_contents($url);
if ($res === false) {
	exit("get block fail");
}
$res = json_decode($res, true);
if (count($res['query']['logevents']) > 0) {
	if ($res['query']['logevents'][0]['action'] === 'unblock') {
		$block = strtotime($res['query']['logevents'][0]['timestamp']);
	} else if (!isset($res['query']['logevents'][0]['params']['expiry'])) {
		$block = true;
	} else {
		$block = strtotime($res['query']['logevents'][0]['params']['expiry']);
	}
} else {
	$block = false;
}
?>
<strong>通過本檢查表格不表示您的申請就會被通過，僅達到本頁資格而不具備申請頁上方提及的相關經驗的申請通常會被拒絕。</strong><br>
不要在申請頁貼上本頁的結果，管理員會自行檢查。<br>
檢查 <?=htmlspecialchars($user)?> 的 <?=$rightname[$type]?> 資格如下：<br>

<?php
define('PASS_TEXT', '通過');
define('FAIL_TEXT', '不通過');
?>
<table class="bordered" style="margin-top: 5px;">
	<tr>
		<th>資格</th>
		<th>用戶</th>
		<th>要求</th>
		<th>檢查結果</th>
	</tr>
	<tr>
		<td>註冊日期</td>
		<td><?=date("Y/m/d H:i", $registration)?></td>
		<td><?php
		if ($require[$type]["registration"]) {
			echo "< ".date("Y/m/d H:i", $require[$type]["registration"]);
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["registration"] && $registration > $require[$type]["registration"]) {
			echo FAIL_TEXT;
		} else {
			echo PASS_TEXT;
		}
		?></td>
	</tr>
	<tr>
		<td>編輯次數</td>
		<td><?=$editcount?></td>
		<td><?php
		if ($require[$type]["editcount"]) {
			echo ">= ".$require[$type]["editcount"];
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["editcount"] && $editcount < $require[$type]["editcount"]) {
			echo FAIL_TEXT;
		} else {
			echo PASS_TEXT;
		}
		?></td>
	</tr>
	<tr>
		<td>首次編輯</td>
		<td><?php
		if ($firstedit === 0) {
			echo "從未編輯";
		} else {
			echo date("Y/m/d H:i", $firstedit);
		}
		?></td>
		<td><?php
		if ($require[$type]["firstedit"]) {
			echo "< ".date("Y/m/d H:i", $require[$type]["firstedit"]);
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["firstedit"] && ($firstedit === 0 || $firstedit > $require[$type]["firstedit"])) {
			echo FAIL_TEXT;
		} else {
			echo PASS_TEXT;
		}
		?></td>
	</tr>
	<tr>
		<td>活躍程度</td>
		<td><?php
		if ($registration > strtotime("-3 months")) {
			echo "註冊以來 ".$editcount."編輯";
		} else {
			echo "最新第".$activedays."筆編輯在".date("Y/m/d H:i", $activeedit);;
		}
		?></td>
		<td><?php
		if ($require[$type]["active"]) {
			echo "3個月內或註冊以來(".$activedays."天)平均每日1編輯<br>";
			if ($registration > strtotime("-3 months")) {
				echo "註冊以來 > ".$activedays."編輯";
			} else {
				echo "最新第".$activedays."筆編輯 >= ".date("Y/m/d H:i", strtotime("-3 months"));
			}
		} else {
			echo "無";
		}
		?></td>
		<td><?php
		if ($require[$type]["active"] === false) {
			echo PASS_TEXT;
		} else if ($registration > strtotime("-3 months")) {
			if ($editcount > $activedays) {
				echo PASS_TEXT;
			} else {
				echo FAIL_TEXT;
			}
		} else {
			if ($activeedit > strtotime("-3 months")) {
				echo PASS_TEXT;
			} else {
				echo FAIL_TEXT;
			}
		}
		?></td>
	</tr>
	<tr>
		<td>封鎖狀態</td>
		<td><?php
		if ($block === false) {
			echo '無封鎖紀錄';
		} elseif ($block === true) {
			echo '被無限期封鎖';
		} else {
			echo '最近封鎖解除於' . date('Y/m/d H:i', $block);
		}
		?></td>
		<td><?php
		if ($require[$type]['block']) {
			echo '最近封鎖解除時間 < ' . date('Y/m/d H:i', $require[$type]['block']);
		} else {
			echo '無';
		}
		?></td>
		<td><?php
		if ($require[$type]['block'] === false || $block === false) {
			echo PASS_TEXT;
		} else if ($block === true) {
			echo FAIL_TEXT;
		} else if ($block > $require[$type]['block']) {
			echo FAIL_TEXT;
		} else {
			echo PASS_TEXT;
		}
		?></td>
	</tr>
</table>
</body>
</html>
