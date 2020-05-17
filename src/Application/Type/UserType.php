<?php
namespace GraphQL\Application\Type;

use GraphQL\Application\AppContext;
use GraphQL\Application\Database\DataSource;
use GraphQL\Application\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class UserType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'User',
            'fields' => function() {
                return [
                    'id' => Types::id(),
                    'username' => ['type' => Types::string()],
                    'password' => ['type' => Types::password()],
	                'sex' => Types::sex(),
	                'email' => Types::email(),
                ];
            },
            'interfaces' => [
                Types::node()
            ],
            'resolveField' => function($value, $args, $context, ResolveInfo $info) {
                $method = 'resolve' . ucfirst($info->fieldName);
                if (method_exists($this, $method)) {
                    return $this->{$method}($value, $args, $context, $info);
                } else {
                    return $value->{$info->fieldName};
                }
            }
        ];
        parent::__construct($config);
    }

}
