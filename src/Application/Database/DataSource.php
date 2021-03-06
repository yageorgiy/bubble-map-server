<?php
namespace GraphQL\Application\Database;

use Error;
use GraphQL\Application\ConfigManager;
use GraphQL\Application\Entity\EntityBase;
use GraphQL\Server\RequestError;
use PDO;
use PDOException;

/**
 * Class DataSource
 *
 * Менеджер по работе с базой данных (используется PDO).
 *
 * @package GraphQL\Application
 */
class DataSource
{

	/**
	 * Адаптер PDO для работы с базой данных
	 *
	 * @var PDO
	 */
	private static PDO $pdo;

	/**
	 * Инициализация адаптера
	 */
	private static function initInstance()
	{

		if(!isset(self::$pdo))
		{
			$db_type = ConfigManager::getField("db_type");
			$db_host = ConfigManager::getField("db_host");
			$db_name = ConfigManager::getField("db_name");
			$db_user = ConfigManager::getField("db_user");
			$db_pass = ConfigManager::getField("db_pass");
			try {
				self::$pdo = new PDO("{$db_type}:dbname={$db_name};host={$db_host};charset=utf8", $db_user, $db_pass,[
					PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_EMULATE_PREPARES => false
				]);
			} catch (PDOException $e) {
				throw new Error("Не могу соединиться с базой данных.");
			}
		}

	}

	/**
	 * Получение адаптера
	 *
	 * @return PDO
	 */
	public static function getPDO()
	{
		return self::$pdo;
	}

    /**
     * Вывод текущего времени в формате MySQL (гггг-мм-чч чч:мм:сс)
     *
     * @return false|string
     */
	public static function timeInMYSQLFormat(){
	    return date("Y-m-d H:i:s");
    }

	/**
	 * Нахождение сущности по ID
	 * Пример использования:
	 *      DataSource::find('User', 1);
	 *
	 * @param string $class
	 * @param int $id
	 * @return mixed|null
	 */
	public static function find(string $class, int $id)
	{
		$bindings = ["id" => (string)$id];
		return self::findOne($class, "id = :id", $bindings);
	}

	/**
	 * Нахождение сущности по первому вхождению
	 * [ Выдаётся первый попавшийся элемент, для нахождения по ID см. метод выше ]
	 *
	 * @param string $class
	 * @param string $query
	 * @param array $bindings
	 * @return mixed|null
	 */
	public static function findOne(string $class, string $query, array $bindings = [])
	{
		$result = self::findAll($class, $query." LIMIT 1", $bindings);
		return (count($result) > 0) ? $result[0] : null;
	}

	/**
	 * Нахождение всех сущностей по запросу
	 *
	 * @param string $class
	 * @param string $query
	 * @param array $bindings
	 * @return array
	 */
	public static function findAll(string $class, string $query, array $bindings = []): array
	{

		self::initInstance();

		$class = "\\GraphQL\\Application\\Entity\\{$class}";
		$assoc_table = (new $class(null))->__getTable();


		$query = self::getPDO()->prepare("SELECT * FROM `{$assoc_table}` WHERE {$query}");
		foreach($bindings as $key => $value)
		{
			$query->bindValue($key, $value);
		}


		$isSuccessful = $query->execute();

		if(!$isSuccessful)
		{
			$arr = print_r($query->errorInfo(), true);
			throw new Error("Не удалось совершить запрос: ".$arr);
		}

		$res = $query->fetchAll();
		$result = [];
		foreach($res as $object_key => $object_value)
		{
			$__ = new $class();

			foreach($object_value as $key => $value)
			{
				$__->$key = $value;
			}

			$result[] = $__;

		}
		return $result;
	}

    /**
     * Добавить сущность в таблицу
     *
     * @param EntityBase $instance
     * @return bool
     * @throws RequestError
     */
	public static function insert(EntityBase $instance){
	    if(!method_exists($instance,'__getTable') || trim($instance->__getTable()) == ""){
	        throw new Error("Отсутствует метод __getTable или возвращает неверные данные у сущности");
        }

        $assoc_table = $instance->__getTable();
        $fields = [];
        $values = [];
        $bindings = [];

        //TODO: оптимизировать

        foreach((array)$instance as $key => $value){
            if(is_null($value)){
                $fields[] = "`".$key."`";
                $values[] = "NULL";
                continue;
            }

            $fields[] = "`".$key."`";
            $values[] = ":".$key;
            $bindings[$key] = $value;
        }


        $str = "INSERT INTO `{$assoc_table}` (".implode(",", $fields).") VALUES (".implode(",", $values).")";
        $query = self::getPDO()->prepare($str);
        foreach($bindings as $key => $value)
        {
            $query->bindValue($key, $value);
        }

        $isSuccessful = $query->execute();

        if(!$isSuccessful)
        {
            $arr = print_r($query->errorInfo(), true);
            throw new Error("Не удалось совершить запрос (".$str."): ".$arr);
        }

        return self::getPDO()->lastInsertId('id');
    }


    /**
     * Удаление сущности по ID
     *
     * @param string $class
     * @param int $id
     * @return bool
     */
    public static function delete(string $class, int $id){
        $bindings = ["id" => (string)$id];
        return self::deleteOne($class, "id = :id", $bindings);
    }

    /**
     * Удаление сущности
     *
     * @param string $class
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public static function deleteOne(string $class, string $query, array $bindings = []){
        $result = self::deleteAll($class, $query." LIMIT 1", $bindings);
        return $result;
    }


    /**
     * Удаление нескольких сущностей
     *
     * @param string $class
     * @param string $query
     * @param array $bindings
     * @return bool
     */
    public static function deleteAll(string $class, string $query, array $bindings = []): bool {

        self::initInstance();

        $class = "\\GraphQL\\Application\\Entity\\{$class}";
        $assoc_table = (new $class(null))->__getTable();


        $query = self::getPDO()->prepare("DELETE FROM `{$assoc_table}` WHERE {$query}");
        foreach($bindings as $key => $value)
        {
            $query->bindValue($key, $value);
        }


        $isSuccessful = $query->execute();

        if(!$isSuccessful)
        {
            $arr = print_r($query->errorInfo(), true);
            throw new Error("Не удалось совершить удаление: ".$arr);
        }

        return true;
    }
}
