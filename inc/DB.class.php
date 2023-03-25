<?php
/**
 * Призначення: Клас для роботи з БД
*/

// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————
// require_once '../config/db.php';
// define('DBHOST', ...);
// define('DBNAME', ...);
// define('DBUSER', ...);
// define('DBPASS', ...);
// ————————————————————————————————————————————————————————————————————————————————
// 
// ————————————————————————————————————————————————————————————————————————————————

class DB {
	private static $instance = null;
	protected function __construct() {}
	static $query_time = 0;
	

	// ————————————————————————————————————————————————————————————————————————————————
	// Підключення
	// ————————————————————————————————————————————————————————————————————————————————
	public static function instance() {
		if (self::$instance === null) {
			self::$instance = new mysqli();
			self::$instance->connect(DBHOST, DBUSER, DBPASS, DBNAME);
			if (self::$instance->connect_errno) {
				printf(_('DB connect failed: %s') . "\n", self::$instance->connect_error);
				exit();
			}
			self::$instance->set_charset('utf8mb4');
		}
		return self::$instance;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// 
	// ————————————————————————————————————————————————————————————————————————————————
	public static function __callStatic($method, $args) {
		return call_user_func_array([self::instance(), $method], $args);
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Закриття
	// ————————————————————————————————————————————————————————————————————————————————
	public static function dbclose() {
		if (self::$instance !== null) self::$instance->close();
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Захист від ін’єкцій
	// ————————————————————————————————————————————————————————————————————————————————
	public static function escape($source)	{
		return self::instance()->real_escape_string($source);
	}

	public static function quote($source)	{
		return "'" . self::instance()->real_escape_string($source) . "'";
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Кількість рядків у результаті
	// ————————————————————————————————————————————————————————————————————————————————
	// public static function num_rows() {
	// 	return self::$result->num_rows;
	// }


	// ————————————————————————————————————————————————————————————————————————————————
	// Очистка результату
	// ————————————————————————————————————————————————————————————————————————————————
	// public static function free() {
	// 	if (self::$result) self::$result->free();
	// }


	// ————————————————————————————————————————————————————————————————————————————————
	// Ідентифікатор останнього вставленого запису
	// ————————————————————————————————————————————————————————————————————————————————
	public static function insert_id() {
		return self::instance()->insert_id;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Кількість змінених рядків
	// ————————————————————————————————————————————————————————————————————————————————
	public static function affected_rows() {
		return self::instance()->affected_rows;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Scheme.Table => Scheme`.`Table
	// ————————————————————————————————————————————————————————————————————————————————
	public static function dbPointTable($table) {
		return str_replace('.', '`.`', $table);
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Формування списку умов для запитів оновлення/видалення
	// ————————————————————————————————————————————————————————————————————————————————
	static public function makeConditionList($keyfields) {
		$conditions = [];
		foreach ($keyfields as $key => $value) 
			if ($value === false)
				continue;
			elseif ($value === NULL)
				$conditions[] = "`{$key}` IS NULL";
			else
				$conditions[] = "`{$key}` = ".self::quote($value);
		return implode(' AND ', $conditions);
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Формування списку "поле = значення" для запитів вставки/оновлення
	// ————————————————————————————————————————————————————————————————————————————————
	static public function makeSetList($data, $keyfields = array()) {
		$fields = array();
		foreach ($data as $key => $value) {
			if ($value === false)
				continue;
			elseif ($value === NULL)
				$fields[] = "`{$key}` = NULL";
			elseif ($value === 'NOW')
				$fields[] = "`{$key}` = NOW()";
			else
				$fields[] = "`{$key}` = ".self::quote($value);
		}
		return implode(', ', $fields);
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// ПРОСТИЙ ЗАПИТ
	// ————————————————————————————————————————————————————————————————————————————————
	// Варіанти виклику:
	// 	Простий:
	// 		DB::query("SELECT 1");
	// 	Параметризований:
	// 		DB::query("SELECT 1 FROM testdt WHERE status = ?", 'i', $status);
	// ————————————————————————————————————————————————————————————————————————————————
	static public function query($query, $types = MYSQLI_STORE_RESULT, $bindparams = [], $params = MYSQLI_STORE_RESULT) {
		$time_before = DB::get_real_time();

		if (is_int($types))	//	MYSQLI_STORE_RESULT
			$result = self::instance()->query($query, $types) or self::display_error($query);
		else {
			$stmt = self::instance()->prepare($query) or self::display_error($query);
			if (is_array($bindparams)) 	$stmt->bind_param($types, ...$bindparams);
			else 						$stmt->bind_param($types, $bindparams);
			$stmt->execute() or self::display_error($query);
			$result = $stmt->get_result();
		}

		self::$query_time += DB::get_real_time() - $time_before;
		return $result;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// ВИБІРКА В ОБ’ЄКТ АБО МАСИВ ОБ’ЄКТІВ
	// ————————————————————————————————————————————————————————————————————————————————
	// Варіанти виклику:
	//	Один рядок:
	//		$row = DB::select("SELECT * FROM testdt");
	// 	Усі рядки:
	//		$rows = DB::select("SELECT * FROM testdt", true);
	// 	Параметризований запит, один рядок:
	//		$row = DB::select("SELECT * FROM testdt WHERE status = ?", 'i', $status);
	//		$row = DB::select("SELECT * FROM testdt WHERE status = ? AND mdatetime > ?", 'is', [$status, $mdatetime]);
	// 	Параметризований запит, усі рядки:
	//		$rows = DB::select("SELECT * FROM testdt WHERE status = ?", 'i', $status, true);
	//		$rows = DB::select("SELECT * FROM testdt WHERE status = ? AND mdatetime > ?", 'is', [$status, $mdatetime], true);
	// ————————————————————————————————————————————————————————————————————————————————
	static public function select($query, $types = false, $bindparams = [], $all = false) {
		$time_before = DB::get_real_time();

		if (is_bool($types)) {
			$all = $types;
			$tmpresult = self::instance()->query($query) or self::display_error($query);
		} else {
			$stmt = self::instance()->prepare($query) or self::display_error($query);
			if (is_array($bindparams))	$stmt->bind_param($types, ...$bindparams);
			else 						$stmt->bind_param($types, $bindparams);
			$stmt->execute() or self::display_error($query);
			$tmpresult = $stmt->get_result();
		}

		if ($all) {
			$result = [];
			while ($row = $tmpresult->fetch_object()) $result[] = $row;
		} else $result = $tmpresult->fetch_object();
		$tmpresult->free();

		self::$query_time += DB::get_real_time() - $time_before;
		return $result;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Формування і виконання запиту вставки 
	// $odku — для оновлення запису у випадку дублювання ключа
	// ————————————————————————————————————————————————————————————————————————————————
	static public function insert($table, $data, $odku = false) {
		$fields = self::makeSetList($data);
		if (!$fields) return false;

		$table = self::dbPointTable($table);

		if ($odku) 	return self::query("INSERT INTO `{$table}` SET {$fields} ON DUPLICATE KEY UPDATE {$fields}");
		else 		return self::query("INSERT INTO `{$table}` SET {$fields}");
	}

	static public function insertid($table, $data) {
		$fields = self::makeSetList($data);
		if (!$fields) return false;

		$table = self::dbPointTable($table);

		return self::query("INSERT INTO `{$table}` SET {$fields} ON DUPLICATE KEY UPDATE {$fields}, id = LAST_INSERT_ID(id)");
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Формування і виконання запиту оновлення
	// ————————————————————————————————————————————————————————————————————————————————
	static public function update($table, $data, $keyfields) {
		$where_clause = self::makeConditionList($keyfields);
		if (!$where_clause) return false;

		$table = self::dbPointTable($table);

		$fields = self::makeSetList($data, $keyfields);
		if (!$fields) return false;

		return self::query("UPDATE `{$table}` SET {$fields} WHERE {$where_clause}");
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// Формування і виконання запиту видалення
	// ————————————————————————————————————————————————————————————————————————————————
	static public function delete($table, $keyfields) {
		$where_clause = self::makeConditionList($keyfields);
		if (!$where_clause) return false;

		$table = self::dbPointTable($table);

		return self::query("DELETE FROM `{$table}` WHERE {$where_clause}");
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// 
	// ————————————————————————————————————————————————————————————————————————————————
	static public function prepare($query) {
		$stmt = self::instance()->prepare($query) or self::display_error($query);
		return $stmt;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// 
	// ————————————————————————————————————————————————————————————————————————————————
	static public function get_real_time() {
		list($seconds, $microSeconds) = explode(' ', microtime());
		return ((float)$seconds + (float)$microSeconds);
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// 
	// ————————————————————————————————————————————————————————————————————————————————
	static public function exists($query, $types = false, $bindparams = []) {
		return self::select("SELECT EXISTS({$query}) e", $types, $bindparams)->e;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// $query: SELECT id, name FROM sometable 
	// ————————————————————————————————————————————————————————————————————————————————
	static public function column($query) {
		$qres = self::query($query);
		$arr = [];
		while ($row = $qres->fetch_array()) $arr[$row[0]] = $row[1];
		return $arr;
	}


	// ————————————————————————————————————————————————————————————————————————————————
	// 
	// ————————————————————————————————————————————————————————————————————————————————
	public static function display_error($query = '')	{
		$error = self::instance()->error;
		$error_num = self::instance()->errno;

		if($query) {
			// Safify query
			$query = preg_replace("/([0-9a-f]){32}/", "********************************", $query); // Hides all hashes
		}

		$query_plaintext = $query;
		$error_plaintext = $error;

		$query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
		$error = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');

		$trace = debug_backtrace();

		$level = 1;
		if (in_array($trace[1]['function'], ['query', 'select'])) $level = 1;
		if (in_array($trace[2]['function'], ['insert', 'update', 'delete'])) $level = 2;

		// if (isset($_SERVER['DOCUMENT_ROOT'])) $trace[$level]['file'] = substr($trace[$level]['file'], strlen($_SERVER['DOCUMENT_ROOT']));

		echo "MySQL error: {$error_num}: {$error}";

		$date = gmdate('D, d M Y H:i:s', time()).' GMT';
		$content = <<<TXT
---------------------------------------------------------------------
{$date}
File: {$trace[$level]['file']}
Line: {$trace[$level]['line']}
Error Number: {$error_num}

Error:
{$error_plaintext}

Query:
{$query_plaintext}
---------------------------------------------------------------------
TXT;

		file_put_contents(DIR_APP.'/../system/log/dberror.log', $content, FILE_APPEND | LOCK_EX);
		exit();
	}


	private function __clone() {}
	public function __wakeup() {}
} 
