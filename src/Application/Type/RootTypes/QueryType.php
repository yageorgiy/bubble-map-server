<?php
namespace GraphQL\Application\Type;

use GraphQL\Application\AppContext;
use GraphQL\Application\Data\User;
use GraphQL\Application\Database\DataSource;
use GraphQL\Application\Types;
use GraphQL\Server\RequestError;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Class QueryType
 * Корневой тип, содержащий общие методы по нахождению других типов.
 *
 *
 * @package GraphQL\Application\Type
 */
class QueryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'Query',
            'fields' => [
                'viewer' => [
                    'type' => Types::user()
                ],
                'mapNodes' => [
                    'args' => [
                        "count" => Types::int()
                    ],
                    'type' => Types::listOf(Types::mapNode())
                ],
                'mapNode' => [
                    'args' => [
                        'id' => Types::nonNull(Types::int())
                    ],
                    'type' => Types::mapNode()
                ],
                'user' => [
                    'args' => [
                        'id' => Types::nonNull(Types::int())
                    ],
                    'type' => Types::user()
                ]
            ],
            'resolveField' => function($val, $args, $context, ResolveInfo $info) {
                return $this->{$info->fieldName}($val, $args, $context, $info);
            }
        ];
        parent::__construct($config);
    }


    /*
     *
     */


	/**
	 * Поиск единичного пользователя из базы данных
	 *
	 * @param $rootValue
	 * @param $args
	 * @return mixed
	 */
	public function user($rootValue, $args, AppContext $context)
    {
        return DataSource::find('User', $args['id']);
    }

    /**
     * Поиск поста на карте
     *
     * @param $rootValue
     * @param $args
     * @return mixed
     */
    public function mapNode($rootValue, $args, AppContext $context)
    {
        return DataSource::find('MapNode', $args['id']);
    }

    /**
     * Генерация
     *
     * @param $rootValue
     * @param $args
     * @return mixed
     */
    public function mapNodes($rootValue, $args, AppContext $context)
    {
        $count = $args['count'] ?? 10;
        $count = (int)$count;
        if($count > 30) $count = 30;
        if($count < 3) $count = 3;


        // TODO оптимизация https://ruhighload.com/%D0%9E%D0%BF%D1%82%D0%B8%D0%BC%D0%B8%D0%B7%D0%B0%D1%86%D0%B8%D1%8F+order+by+rand%28%29
        return DataSource::findAll('MapNode', "1 ORDER BY rand() LIMIT :count", [
            "count" => $count
        ]);
    }

    /**
     * Текущий пользователь
     *
     * @param $rootValue
     * @param $args
     * @param AppContext $context
     * @return \GraphQL\Application\Entity\User
     * @throws RequestError
     */
	public function viewer($rootValue, $args, AppContext $context)
    {
        if(!$context->viewer->isAuthorized()){
            throw new RequestError("Требуется авторизация");
        }

        return $context->viewer;
    }
}
