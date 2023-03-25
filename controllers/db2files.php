<?php

/**
 * Вивантаження HTML-сторінок із БД у файли
 * 
 * php index.php db2files/{ідентифікатор сайту}
 */

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
$site_id = $argc > 2 ? $argv[2] : false;
$site_id = filter_var($site_id, FILTER_VALIDATE_INT);

$site = DB::select("SELECT * FROM websites WHERE id = ?", 'i', $site_id);
if (!$site) exit('No such website'.PHP_EOL);

// ————————————————————————————————————————————————————————————————————————————————
// Директорія для результату. Якщо відсутня — створити
// ————————————————————————————————————————————————————————————————————————————————
define('DIR_FILES', 'files/'.$site->id.'-humannames');
if (!file_exists(DIR_FILES)) mkdir(DIR_FILES, true);

// ————————————————————————————————————————————————————————————————————————————————
// HTML-сторінки у БД
// ————————————————————————————————————————————————————————————————————————————————
$pages = DB::select("SELECT suburl, html FROM pages WHERE site_id = {$site->id}", true);

foreach ($pages as $page) {
	$filename = DIR_FILES.'/'.e_filename(basename($page->suburl)).'.html';
	file_put_contents($filename, $page->html);
}

