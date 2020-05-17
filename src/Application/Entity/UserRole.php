<?php


namespace GraphQL\Application\Entity;


class UserRole extends EntityBase
{
    public int $role_id;
    public int $user_id;

    public function __getTable()
    {
        return "user_role";
    }
}