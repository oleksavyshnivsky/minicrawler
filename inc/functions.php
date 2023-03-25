<?php

// ————————————————————————————————————————————————————————————————————————————————
// Перетворення HTML-кодів на символи
// ————————————————————————————————————————————————————————————————————————————————
function d(?string $raw_input): string {
	if (!$raw_input) return '';
	return htmlspecialchars_decode(trim($raw_input));
}

// ————————————————————————————————————————————————————————————————————————————————
// Безпечне ім’я файлу
// ————————————————————————————————————————————————————————————————————————————————
function e_filename(string $file): string {
	$file = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $file);
	$file = mb_ereg_replace("([\.]{2,})", '', $file);
	return $file;
}

// ————————————————————————————————————————————————————————————————————————————————
// Скачування й парсення HTML-документа
// ————————————————————————————————————————————————————————————————————————————————
function getDoc(object &$ch, string $url) : object {
	echo $url, PHP_EOL;
	curl_setopt($ch, CURLOPT_URL, $url);

	$response = curl_exec($ch);
	if (curl_errno($ch) > 0) exit(curl_error($ch).PHP_EOL);
	
	$doc = str_get_html($response, true, true, DEFAULT_TARGET_CHARSET, false);
	if (!is_object($doc)) exit('Doc parsing error: '.$url.PHP_EOL);

	return $doc;
}