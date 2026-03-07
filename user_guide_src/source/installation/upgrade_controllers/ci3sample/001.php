<?php

declare(strict_types=1);

class Helloworld extends CI_Controller
{
    public function index($name)
    {
        echo 'Hello ' . html_escape($name) . '!';
    }
}
