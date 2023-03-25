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

$pages = DB::column("SELECT id, suburl FROM pages WHERE site_id = {$site->id}");

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
DB::query("DELETE r FROM referers r
	INNER JOIN pages p ON (p.id = r.ref_id OR p.id = r.tar_id)
	WHERE p.site_id = {$site->id}
");
$stmt = DB::prepare("INSERT INTO referers(ref_id, tar_id) VALUES(?, ?)
	ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
");
$stmt->bind_param('ii', $ref_id, $tar_id);

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
foreach ($pages as $ref_id => $suburl) {
	$url = $site->url . $suburl;

	echo $url, PHP_EOL;
	curl_setopt($ch, CURLOPT_URL, $url);
	$response = curl_exec($ch);
	if (curl_errno($ch) > 0) exit(curl_error($ch).PHP_EOL);

	$doc = str_get_html($response);
	if (!is_object($doc)) exit('Doc parsing error: '.$url.PHP_EOL);

	$links = $doc->find('main a[href^="'.$site->url.'"], main a[href^="/"]');
	foreach ($links as $a) {
		if (str_ends_with($a->href, '.docx')) continue;
		if (mb_strpos($a->href, '/download/') !== false) continue;
		// ...

		$href = $a->href[0] === '/' ? $site->url.$a->href : $a->href;
		$href = str_replace($site->url, '', $href);

		$tar_id = array_search($href, $pages);
		if ($tar_id !== false) {
			$stmt->execute();
		}
	}
}

// ————————————————————————————————————————————————————————————————————————————————
// Оновлення часу останнього читання
// ————————————————————————————————————————————————————————————————————————————————
DB::query("UPDATE websites SET ldatetime = NOW() WHERE id = {$site->id}");
$stmt->close();

echo 'DONE', PHP_EOL;
