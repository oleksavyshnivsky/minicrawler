<?php

/**
 * Читання вмісту адмінки
 * Вимагає переписування під конкретну адмінку:
 * 	- логін/пароль, форма входу
 * 	- URL, які ігнорувати
 * 
 * php index.php getadmin/{ідентифікатор сайту}
 */

// ————————————————————————————————————————————————————————————————————————————————
// require
// ————————————————————————————————————————————————————————————————————————————————
require_once 'inc/simple_html_dom.php';

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
$site_id = $argc > 2 ? $argv[2] : false;
$site_id = filter_var($site_id, FILTER_VALIDATE_INT);

$site = DB::select("SELECT * FROM websites WHERE id = ?", 'i', $site_id);
if (!$site) exit('No such website'.PHP_EOL);

define('DIR_FILES', 'files/'.$site->id.'-main');
if (!file_exists(DIR_FILES)) mkdir(DIR_FILES, true);

$pages_saved = [];
$pages_queue = [$site->url.$site->suburl];

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
DB::query("UPDATE pages SET deleted = 1 WHERE site_id = {$site->id}");
$stmt = DB::prepare("INSERT INTO pages(site_id, suburl, title, html, plaintext, 
	main_html, main_text)
	VALUES({$site->id}, ?, ?, ?, ?, ?, ?)
	ON DUPLICATE KEY UPDATE
		title = VALUES(title)
		, html = VALUES(html)
		, plaintext = VALUES(plaintext)
		, main_html = VALUES(main_html)
		, main_text = VALUES(main_text)
		, deleted = 0
");
$stmt->bind_param('ssssss', $suburl, $title, $html, $plaintext, $main_html, $main_text);

// ————————————————————————————————————————————————————————————————————————————————
// cURL
// ————————————————————————————————————————————————————————————————————————————————
$ch = curl_init();
curl_setopt_array($ch, [
	CURLOPT_FOLLOWLOCATION	=>	true,
	CURLOPT_CONNECTTIMEOUT	=>	2,
	CURLOPT_RETURNTRANSFER	=>	1,
	CURLOPT_SSL_VERIFYPEER	=>	false,
	CURLOPT_USERAGENT		=>	'Bot Comparing Websites in Different PHP Versions',
	CURLOPT_COOKIEJAR		=>	'system/cookies/'.$site->id.'.txt',
	CURLOPT_COOKIEFILE		=>	'system/cookies/'.$site->id.'.txt',
]);

// ————————————————————————————————————————————————————————————————————————————————
// Вхід
// ————————————————————————————————————————————————————————————————————————————————
$doc = getDoc($ch, $site->url.$site->suburl);

$check = $doc->find('form[action*="signin"]', 0);
if ($check) {
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, [
		'username'	=>	'...',
		'password'	=>	'...',
		'action'	=>	'signin'
	]);
	$doc = getDoc($ch, $site->url.$site->suburl.'/?url=signin');
}

// ————————————————————————————————————————————————————————————————————————————————
// Перебір сторінок
// ————————————————————————————————————————————————————————————————————————————————
curl_setopt($ch, CURLOPT_HTTPGET, true);
while ($url = array_shift($pages_queue)) {
	$doc = getDoc($ch, $url);

	$suburl = str_replace($site->url.$site->suburl, '', $url);
	$title = d($doc->find('title', 0)->plaintext);
	$html = $doc->innertext;
	$plaintext = d($doc->plaintext);

	$main = $doc->find('main', 0);
	$main_html = $main ? trim($main->innertext) : '';
	$main_text = $main ? trim($main->plaintext) : '';

	$stmt->execute();
	$pages_saved[] = $url;

	$filename = DIR_FILES.'/'.e_filename(basename($suburl)).'.html';
	file_put_contents($filename, $main_html);

	$links = $doc->find('a[href^="'.$site->url.$site->suburl.'"], a[href^="'.$site->suburl.'"]');
	foreach ($links as $a) {
		if (str_ends_with($a->href, '.docx')) continue;
		if (mb_strpos($a->href, '/download/') !== false) continue;

		$href = d($a->href[0] === '/' ? $site->url.$a->href : $a->href);
		if (!in_array($href, $pages_saved) and !in_array($href, $pages_queue)) $pages_queue[] = $href;
	}
}

// ————————————————————————————————————————————————————————————————————————————————
// Оновлення часу останнього читання
// ————————————————————————————————————————————————————————————————————————————————
DB::query("UPDATE websites SET ldatetime = NOW() WHERE id = {$site->id}");
$stmt->close();
