<?php

$site_id = $argc > 2 ? $argv[2] : false;
$site_id = filter_var($site_id, FILTER_VALIDATE_INT);

$site = DB::select("SELECT * FROM websites WHERE id = ?", 'i', $site_id);
if (!$site) exit('No such website'.PHP_EOL);

$baseurl = DB::quote($site->url);

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
$rows = DB::select("SELECT
		CONCAT({$baseurl}, a.suburl) suburl
		, a.title
		, b.title title7
		, IF(a.title = b.title, 1, 0) same_title
		-- , a.html
		-- , b.html html7
		, IF(a.html = b.html, 1, 0) same_html
		-- , a.plaintext
		-- , b.plaintext plaintext7
		, IF(a.plaintext = b.plaintext, 1, 0) same_plaintext
	FROM pages a
	LEFT JOIN pages b ON (b.site_id, b.suburl) = (1, a.suburl)
	WHERE a.site_id = 2
", true);

print_r($rows);

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
$rows = DB::select("SELECT
		CONCAT({$baseurl}, a.suburl) suburl
		, a.title
		, b.title title7
		, IF(a.title = b.title, 1, 0) same_title
		-- , a.html
		-- , b.html html7
		, IF(a.html = b.html, 1, 0) same_html
		-- , a.plaintext
		-- , b.plaintext plaintext7
		, IF(a.plaintext = b.plaintext, 1, 0) same_plaintext
	FROM pages a
	RIGHT JOIN pages b ON (b.site_id, b.suburl) = (1, a.suburl)
	WHERE a.site_id = 2 AND a.id IS NULL
", true);

print_r($rows);

