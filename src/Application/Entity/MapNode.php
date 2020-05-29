<?php


namespace GraphQL\Application\Entity;


class MapNode extends EntityBase
{

    public int $user_id;
    public string $date_created;
    public string $title;
    public string $description;
    public string $thumbnail_url;

    public function __construct(array $data = null)
    {
        parent::__construct($data);
    }

    public function __getTable() {
        return "mapnode";
    }
}