<?php


namespace GraphQL\Application\Entity;


class UserToken extends EntityBase
{
    public string $token;
    public string $date_created;
    public int $user_id;

    public function __getTable()
    {
        return "user_token";
    }
}