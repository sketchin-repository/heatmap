<?php
require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', array(
        'name' => "laura",
    ));
});

$app->get('/map', function () use ($app) {
    return $app['twig']->render('map.html');
});

$app->run();
?>