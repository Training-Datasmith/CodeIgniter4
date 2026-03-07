<?php

declare(strict_types=1);

$uri = new \CodeIgniter\HTTP\URI('http://www.example.com/some/path');

echo $uri->getPath();                            // '/some/path'
echo $uri->setPath('/another/path')->getPath();  // '/another/path'
