<?php
namespace GraphQL\Application\Type;


use GraphQL\Application\Bearer;
use GraphQL\Application\AppContext;
use GraphQL\Application\Database\DataSource;
use GraphQL\Application\Entity\User;
use GraphQL\Application\Entity\UserToken;
use GraphQL\Application\Types;
use GraphQL\Server\RequestError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class QueryType
 * Корневой тип, содержащий общие методы по нахождению других типов.
 *
 *
 * @package GraphQL\Application\Type
 */
class MutationType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'Mutation',
            'fields' => [
                'register' => [
                    'type' => Types::boolean(),
                    'args' => [
                        'username' => Types::nonNull(Types::string()),
                        'email' => Types::nonNull(Types::email()),
                        'password' => Types::nonNull(Types::password()),
                        'sex' => Types::nonNull(Types::sex())
                    ]
                ],

                'login' => [
                    'type' => Types::listOf(Types::string()),
                    'args' => [
                        'username' => Types::nonNull(Types::string()),
                        'password' => Types::nonNull(Types::string()),
                    ]
                ],

                'logout' => [
                    'type' => Types::boolean(),
                    'args' => []
                ],


            ],
            'resolveField' => function($val, $args, $context, ResolveInfo $info) {
                return $this->{$info->fieldName}($val, $args, $context, $info);
            }
        ];
        parent::__construct($config);
    }

    /**
     * Регистрация пользователя
     *
     * @param $rootValue
     * @param $args
     * @param AppContext $context
     * @return bool
     * @throws RequestError
     */
    public function register($rootValue, $args, AppContext $context){

        //TODO: проверка на уникальность пользователя(?) (ФИО, e-mail)
        //TODO: капча(?)
        //TODO: анти-DDOS регистрации
        //TODO: защита от распространенных атак
        //TODO: запрет на регистрацию кириллического пароля


        $instance = new User([
            'ip' => $context->ip,
            'date_registered' => DataSource::timeInMYSQLFormat(),
            'username' => $args['username'],
            'sex' => $args['sex'],
            'email' => $args['email'],
            'status_email' => "ожидание",
            'verification_key_email' => "",
            'password' => User::hashPassword($args['password']),
        ]);

        return DataSource::insert($instance);
    }

    /**
     * Авторизация пользователя, вывод токена
     *
     * @param $rootValue
     * @param $args
     * @param AppContext $context
     * @return array
     * @throws RequestError
     */
    public function login($rootValue, $args, AppContext $context){

        //TODO: проверка на уникальность пользователя(?) (ФИО, e-mail)
        //TODO: капча(?)
        //TODO: анти-DDOS авторизации
        //TODO: защита от распространенных атак
        //TODO: привязывать ли сессию к IP-адресу?

        $found = DataSource::findOne("user", "email = :username OR username = :username", [
            ':username' => $args['username']
        ]);

        if($found == null || !User::validatePassword($args['password'], $found->password))
            throw new RequestError("Неверный логин или пароль");

        // Создание токена пользователя и сохранение в базу данных
        $token = Bearer::generate($context, $found);
        $token_inst = new UserToken([
            "token" => $token,
            "date_created" => DataSource::timeInMYSQLFormat(),
            "user_id" => $found->id
        ]);
        DataSource::insert($token_inst);

        return [
            'token' => $token
        ];
    }

    /**
     * @param $rootValue
     * @param $args
     * @param AppContext $context
     * @return bool
     * @throws \Exception
     */
    public function logout($rootValue, $args, AppContext $context){
        $bearer = $context->getBearerOrError();

        $successful = DataSource::deleteOne("UserToken", "token = :bearer", [
            ':bearer' => $bearer
        ]);

        return $successful;
    }
}
