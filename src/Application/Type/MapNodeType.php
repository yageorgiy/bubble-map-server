<?php
namespace GraphQL\Application\Type;

use GraphQL\Application\AppContext;
use GraphQL\Application\Database\DataSource;
use GraphQL\Application\Entity\MapNode;
use GraphQL\Application\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class MapNodeType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'MapNode',
            'fields' => function() {
                return [
                    'id' => Types::id(),
                    'user_id' => Types::int(),
                    'date_created' => Types::date(),
                    'title' => Types::string(),
                    'description' => Types::string(),
                    "thumbnail_url" => Types::string(),
                    "getAuthor" => Types::user()
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


    public function resolveGetAuthor(MapNode $value, $args, $context, $info){
        return DataSource::find('User', $value->user_id);
    }

}
