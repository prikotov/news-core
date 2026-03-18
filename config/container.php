<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Dotenv\Dotenv;

$projectDir = getcwd();
$packageDir = dirname(__DIR__);

$dotenv = new Dotenv();

if (file_exists($packageDir . '/.env')) {
    $dotenv->load($packageDir . '/.env');
}

if (file_exists($projectDir . '/.env')) {
    $dotenv->load($projectDir . '/.env');
}

if (file_exists($projectDir . '/.env.local')) {
    $dotenv->load($projectDir . '/.env.local');
}

$container = new ContainerBuilder();
$container->setParameter('kernel.project_dir', $projectDir);

$loader = new YamlFileLoader($container, new FileLocator($packageDir . '/config'));
$loader->load('services.yaml');

$container->compile(true);

return $container;
