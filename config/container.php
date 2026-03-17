<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Dotenv\Dotenv;

$projectDir = dirname(__DIR__);

$dotenv = new Dotenv();
$dotenv->loadEnv($projectDir . '/.env');

$container = new ContainerBuilder();
$container->setParameter('kernel.project_dir', $projectDir);

$loader = new YamlFileLoader($container, new FileLocator($projectDir . '/config'));
$loader->load('services.yaml');

$container->compile(true);

return $container;
