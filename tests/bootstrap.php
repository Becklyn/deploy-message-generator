<?php declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context)
{
    if (file_exists(dirname(__DIR__) . '/config/bootstrap.php'))
    {
        require dirname(__DIR__) . '/config/bootstrap.php';
    }
    elseif (method_exists(Dotenv::class, 'load'))
    {
        $projectDir = dirname(__DIR__);

        if (!empty($context['HOME']))
        {
            $home = $context['HOME'];
        }
        elseif (!empty($context['USERPROFILE']))
        {
            $home = $context['USERPROFILE'];
        }
        else
        {
            // Fallback to the project dir as home dir
            $home = $projectDir;
        }


        $dotenv = new Dotenv();
        // This will load the global config for the generator
        $dotenv->load("{$home}/.deploy-message-generator.env");

        // This will load the test specific configuration within the project dir
        $dotenv->loadEnv("{$projectDir}/.env");

        $context += $_ENV + $_SERVER;
        return $context;
    }
    return $context;
};
