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
            $home = dirname(__DIR__);
        }

        (new Dotenv())->load($home . '/.deploy-message-generator.env');
        $context += $_ENV;
        return $context;
    }
    return $context;
};
