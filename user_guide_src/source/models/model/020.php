<?php

declare(strict_types=1);

namespace App\Entities;

class Job
{
    protected $id;
    protected $name;
    protected $description;

    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }
    }

    public function __set($key, $value)
    {
        if (property_exists($this, $key)) {
            $this->{$key} = $value;
        }
    }
}
