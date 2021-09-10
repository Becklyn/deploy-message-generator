<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// populate $_ENV with all real environment variables as they are somehow not set by default
foreach (\getenv() as $k => $v)
{
    if (empty($_ENV[$k]))
    {
        $_ENV[$k] = $v;
    }
}
