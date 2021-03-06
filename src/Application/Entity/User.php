<?php
namespace GraphQL\Application\Entity;

use GraphQL\Application\Database\DataSource;
use GraphQL\Server\RequestError;

/**
 * Class User
 * Сущность пользователя
 *
 * @package GraphQL\Application\Data
 */

class User extends EntityBase
{
    public string $ip;
    public string $date_registered;
    public string $username;
    public string $sex;
    public string $email;
    public string $status_email;
    public string $verification_key_email;
    public string $password;

    //TODO: заносить данные в сущность через __construct
	public function __construct(array $data = null)
	{
		parent::__construct($data);
	}

	/**
	 * Ассоциированная таблица
	 * (таблица должна быть создана в базе данных)
	 *
	 * @return string
	 */
	public function __getTable()
    {
    	return "user";
    }


    /* Статические методы */

    /**
     * Настройки хэширования паролей
     *
     * @var array
     */
    public static $password_default_options = [
        "cost" => 12
    ];


    /**
     * Создание хэша пароля
     *
     * @param $password
     * @return string
     */
    public static function hashPassword($password): string{
	    return password_hash($password, PASSWORD_BCRYPT, self::$password_default_options);
    }

    /**
     * Проверка пароля, сравнение пароля с его хэшем
     *
     * @param $password
     * @param $hash
     * @return bool
     */
    public static function validatePassword($password, $hash): bool{
        return password_verify($password, $hash);
    }

    /**
     * Авторизован ли текущий пользователь
     *
     * @return bool
     */
    public function isAuthorized(){
        return isset($this->id) && $this->id != 0;
    }

    /*
     * //TODO реализовать проверку на доступ к правам (https://habr.com/ru/post/51327/)
     * */

    /**
     * Проверка на доступ к функции
     *
     * @param int $action_id
     * @param string $list_id
     * @return bool
     */
    public function hasAccess(int $action_id, string $list_id = "1"): bool{
        // Если пользователь неавторизован
        if(!$this->isAuthorized())
            return false;

        /** @var array $user_role */
        $user_role = DataSource::findAll("UserRole", "user_id = :uid", ["uid" => $this->id]);
        foreach ($user_role as $i){
            /** @var UserRole $i */

            /** @var ActionList $action_data */
            $action_data = DataSource::findOne("ActionList", "role_id = :rid AND action_id = :aid AND list_id = :lid", [
                "rid" => $i->role_id,
                "aid" => $action_id,
                "lid" => $list_id
            ]);

            if($action_data != null AND $action_data->sign == "+")
                return true;
        }

        return false;
    }

    /**
     * Проверка на доступ к функции. Если доступа нет, то приложение выбрасывает ошибку.
     *
     * @param int $action_id
     * @param string $list_id
     * @return bool
     * @throws RequestError
     */
    public function hasAccessOrError(int $action_id, string $list_id = "1"){
        if(!$this->hasAccess($action_id, $list_id))
            throw new RequestError("Ошибка: нет доступа к функции");

        return true;
    }

}
