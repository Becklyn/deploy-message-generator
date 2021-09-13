<?php declare(strict_types=1);

if (is_file($autoloader = dirname(__DIR__) . "/vendor/autoload.php"))
{
    require_once $autoloader;
}
else if (is_file($autoloader = dirname(__DIR__, 3) . "/autoload.php"))
{
    require_once $autoloader;
}

require_once dirname(__DIR__) . "/src/BecklynDeployMessageGenerator.php";
