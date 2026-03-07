<?php

declare(strict_types=1);

$url = $result->getRedirectUrl();
$this->assertEquals(site_url('foo/bar'), $url);
