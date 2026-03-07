<?php

declare(strict_types=1);

namespace Config;

// ...

class MyConfig extends BaseConfig
{
    public static $registrars = [
        SupportingPackageRegistrar::class,
    ];

    // ...
}
