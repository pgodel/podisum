<?php

use Tobiassjosten\Silex\ResponsibleServiceProvider;

$mongoClass = class_exists('MongoClient') ? 'MongoClient' : 'Mongo';

$app['podisum_cfg'] = $app->share(function()
{
    return \Symfony\Component\Yaml\Yaml::parse('../config/podisum.yml');
});

$app->register(new Sfk\Silex\Provider\MongoDBServiceProvider(), array(
    'mongodb.server' => $app['podisum_cfg']['mongo'],
    'mongodb.options' => array(),
    'mongodb.client_class' => $mongoClass,
));

$app->register(new ResponsibleServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../resources/views',
));


$app['podisum'] = function($app)
{
    return new \Podisum($app['mongodb.client'], $app['podisum_cfg']);
};

include 'app-api.php';
