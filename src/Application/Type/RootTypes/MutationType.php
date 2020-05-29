<?php
namespace GraphQL\Application\Type;


use GraphQL\Application\Bearer;
use GraphQL\Application\AppContext;
use GraphQL\Application\Database\DataSource;
use GraphQL\Application\Entity\MapNode;
use GraphQL\Application\Entity\User;
use GraphQL\Application\Entity\UserRole;
use GraphQL\Application\Entity\UserToken;
use GraphQL\Application\File\FileStorage;
use GraphQL\Application\Types;
use GraphQL\Server\RequestError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use Psr\Http\Message\UploadedFileInterface;

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
                        'password' => Types::nonNull(Types::password()),
                    ]
                ],

                'logout' => [
                    'type' => Types::boolean(),
                    'args' => []
                ],

                'publishPost' => [
                    'type' => Types::boolean(),
                    'args' => [
                        'title' => Types::nonNull(Types::string()),
                        'tag' => Types::nonNull(Types::postTag()),
                        'content' => Types::nonNull(Types::html()),
//                        'image' => Types::nonNull(Types::upload())
                    ]
                ],

                'editPost' => [
                    'type' => Types::boolean(),
                    'args' => [
                        'id' => Types::nonNull(Types::int()),
                        'title' => Types::string(),
                        'tag' => Types::postTag(),
                        'content' => Types::html(),
//                        'image' => Types::upload()
                    ]
                ],

                'deletePost' => [
                    'type' => Types::boolean(),
                    'args' => [
                        'id' => Types::nonNull(Types::int())
                    ]
                ]


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
        //TODO: ограничение на длину всех полей регистрации

        $q = DataSource::findOne("User", "ip = :ip OR username = :username OR email = :email", [
            "email" => $args["email"],
            "username" => $args["username"],
            "ip" => $context->ip
        ]);
        if($q != null)
            throw new RequestError("Имя пользователя, e-mail или IP адрес уже зарегистрированы в базе");

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

        $id = DataSource::insert($instance);

        DataSource::insert(new UserRole([
            "user_id" => $id,
            "role_id" => 1
        ]));

        return true;
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

        $found = DataSource::findOne("User", "username = :username", [
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

    /**
     * @param $rootValue
     * @param array $args
     * @param AppContext $context
     * @return string|null
     * @throws RequestError
     */
    public function publishPost($rootValue, array $args, AppContext $context){
        $context->viewer->hasAccessOrError(1);

        $image = "";

        switch($args["tag"]){
            case "Жизненные заметки":
                $image = "sticky-note-regular.svg.png";
                break;

            case "Хобби":
                $image = "book-open-solid.svg.png";
                break;

            case "Сделай сам (DIY)":
                $image = "hammer-solid.svg.png";
                break;

            case "Проект":
                $image = "project-diagram-solid.svg.png";
                break;

            case "Стиль":
                $image = "tshirt-solid.svg.png";
                break;

            case "Искусство":
                $image = "paint-brush-solid.svg.png";
                break;

            case "IT":
                $image = "laptop-code-solid.svg.png";
                break;

            case "Игры":
                $image = "gamepad-solid.svg.png";
                break;

            case "Другое":
            default:
                $image = "align-left-solid.svg.png";
                break;

        }

        DataSource::insert(new MapNode([
            "user_id" => $context->viewer->id,
            "date_created" => DataSource::timeInMYSQLFormat(),
            "title" => $args["title"],
            "description" => $args["content"],
            "thumbnail_url" => $context->rootUrl."/images/".$image
        ]));

        return true;
    }

    /**
     * @param $rootValue
     * @param array $args
     * @param AppContext $context
     * @return string|null
     * @throws RequestError
     */
    public function editPost($rootValue, array $args, AppContext $context){
        $context->viewer->hasAccessOrError(2);

        /** @var MapNode $post */
        $post = DataSource::findOne("MapNode", $args["id"]);
        if($post == null)
            throw new RequestError("Пост не найден");

        if($post->user_id != $context->viewer->id)
            throw new RequestError("Вы не владеете постом");







        /** @var UploadedFileInterface $file */
        $file = $args['file'];

        // Do something with the file
//        $file->moveTo('some/folder/in/my/project');
        return $file->getClientFilename();
    }

    /**
     * @param $rootValue
     * @param array $args
     * @param AppContext $context
     * @return bool
     * @throws RequestError
     */
    public function deletePost($rootValue, array $args, AppContext $context){
        $context->viewer->hasAccessOrError(3);

        /** @var MapNode $post */
        $post = DataSource::findOne("MapNode", $args["id"]);

        if($post == null)
            throw new RequestError("Пост не найден");

        if($post->user_id != $context->viewer->id)
            throw new RequestError("Вы не владеете постом");

        DataSource::delete("MapNode", $args["id"]);

        return true;
    }


}
