<?php

// ————————————————————————————————————————————————————————————————————————————————
// Includes
// ————————————————————————————————————————————————————————————————————————————————
require_once 'inc/simple_html_dom.php';

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
$site_id = $argc > 1 ? $argv[1] : false;
$site_id = filter_var($site_id, FILTER_VALIDATE_INT);

$site = DB::select("SELECT * FROM websites WHERE id = ?", 'i', $site_id);
if (!$site) exit('No such website'.PHP_EOL);

define('DIR_FILES', 'files/'.$site->id.'-main');

$pages_saved = [];
$pages_queue = [$site->url];

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
DB::query("UPDATE pages SET deleted = 1 WHERE site_id = {$site->id}");
$stmt = DB::prepare("INSERT INTO pages(site_id, suburl, title, html, plaintext, 
	main_html, main_text, deleted)
	VALUES({$site->id}, ?, ?, ?, ?, ?, ?, 0)
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
]);

// ————————————————————————————————————————————————————————————————————————————————
// Перебір сторінок
// ————————————————————————————————————————————————————————————————————————————————
while ($url = array_shift($pages_queue)) {
	echo $url, PHP_EOL;
	curl_setopt($ch, CURLOPT_URL, $url);
	$response = curl_exec($ch);
	if (curl_errno($ch) > 0) exit(curl_error($ch).PHP_EOL);

	$doc = str_get_html($response, true, true, DEFAULT_TARGET_CHARSET, false);
	if (!is_object($doc)) exit('Doc parsing error: '.$url.PHP_EOL);

	$suburl = str_replace($site->url, '', $url);
	$title = d($doc->find('title', 0)->plaintext);
	$html = $response;
	$plaintext = d($doc->plaintext);

	$main = $doc->find('main', 0);
	$main_html = $main ? trim($main->innertext) : '';
	$main_text = $main ? trim($main->plaintext) : '';

	$stmt->execute();
	$pages_saved[] = $url;

	$filename = DIR_FILES.'/'.e_filename(basename($suburl)).'.html';
	file_put_contents($filename, $main_html);

	$links = $doc->find('a[href^="'.$site->url.'"], a[href^="/"]');
	foreach ($links as $a) {
		if (str_ends_with($a->href, '.docx')) continue;
		if (mb_strpos($a->href, '/download/') !== false) continue;
		// ...

		// echo $a->href, PHP_EOL;

		$href = $a->href[0] === '/' ? $site->url.$a->href : $a->href;
		if (!in_array($href, $pages_saved) and !in_array($href, $pages_queue)) $pages_queue[] = $href;
	}
}

// ————————————————————————————————————————————————————————————————————————————————
// Оновлення часу останнього читання
// ————————————————————————————————————————————————————————————————————————————————
DB::query("UPDATE websites SET ldatetime = NOW() WHERE id = {$site->id}");
$stmt->close();

echo 'DONE', PHP_EOL;
