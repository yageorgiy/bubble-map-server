<?php


/*
 * Pay attention that the function will not validate "not latin" domains.

if (filter_var('уникум@из.рф', FILTER_VALIDATE_EMAIL)) {
    echo 'VALID';
} else {
    echo 'NOT VALID';
}

перегнать в ANSI через json_encode();
 */

namespace GraphQL\Application\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\CustomScalarType;
use GraphQL\Utils\Utils;

class EmailType
{
    public static function create()
    {
        return new CustomScalarType([
            'name' => 'Email',
            'description' => 'Электронная почта формата user@domain.com',
            'serialize' => [__CLASS__, 'serialize'],
            'parseValue' => [__CLASS__, 'parseValue'],
            'parseLiteral' => [__CLASS__, 'parseLiteral'],
        ]);
    }

    /**
     * Serializes an internal value to include in a response.
     *
     * @param string $value
     * @return string
     */
    public static function serialize($value)
    {
        // Assuming internal representation of email is always correct:
        return $value;

        // If it might be incorrect and you want to make sure that only correct values are included in response -
        // use following line instead:
        // return $this->parseValue($value);
    }

    /**
     * Parses an externally provided value (query variable) to use as an input
     *
     * @param mixed $value
     * @return mixed
     */
    public static function parseValue($value)
    {
        //TODO: сделать поддержку кириллицы (filter_var не умеет проверять кириллицу!)
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \UnexpectedValueException("Cannot represent value as email: " . Utils::printSafe($value));
        }
        return $value;
    }

    /**
     * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input
     *
     * @param \GraphQL\Language\AST\Node $valueNode
     * @return string
     * @throws Error
     */
    public static function parseLiteral($valueNode)
    {
        // Note: throwing GraphQL\Error\Error vs \UnexpectedValueException to benefit from GraphQL
        // error location in query:
        if (!$valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, [$valueNode]);
        }
        //TODO: сделать поддержку кириллицы (filter_var не умеет проверять кириллицу!) здесь тоже
        if (!filter_var($valueNode->value, FILTER_VALIDATE_EMAIL)) {
            throw new Error("Not a valid email", [$valueNode]);
        }
        return $valueNode->value;
    }
}
