<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$mongoClass = class_exists('MongoClient') ? 'MongoClient' : 'Mongo';
$app->register(new Sfk\Silex\Provider\MongoDBServiceProvider(), array(
    'mongodb.server' => 'mongodb://localhost:27017/summarizer',
    'mongodb.options' => array(),
    'mongodb.client_class' => $mongoClass,
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../resources/views',
));

$app->get('/view/{name}', function ($name) use ($app) {
    return 'view';
});

$app->get('/', function ($name = '') use ($app) {

    $mongo = $app['mongodb.client'];

    $podisum = new \Podisum($mongo, \Symfony\Component\Yaml\Yaml::parse('../config/podisum.yml'));
    $db = $mongo->selectDB($podisum->getConfig('mongo_db', 'podisum'));
    $collections = $db->listCollections();

    $str = '';

    $data = array();

    $selectedCollection = null;

    foreach($collections as $c) {
        $cname = $c->getName();

        if ($cname[0] != 's') {
            continue;
        }

        $parts = explode('_', substr($cname, 1), 2);

        if (empty($parts[0])) {
            continue;
        }

        $shortName = $parts[1];
        $data['collections'][$shortName]['name'] = $shortName;
        $data['collections'][$shortName]['summaries'][$cname]['ttl'] = $parts[0];

        $data['collections'][$shortName]['summaries'][$cname]['entries'] = $c->find()->sort(array('counter' => -1))->limit(20);

        $data['collections'][$shortName]['summaries'][$cname]['total'] = 0;
        $i = 0;
        foreach($data['collections'][$shortName]['summaries'][$cname]['entries'] as $entry) {
            $data['collections'][$shortName]['summaries'][$cname]['total'] += $entry['counter'];
            $i++;
        }
        $data['collections'][$shortName]['summaries'][$cname]['avg'] = $data['collections'][$shortName]['summaries'][$cname]['total'] / $i;
        $data['collections'][$shortName]['summaries'][$cname]['avgm'] = $parts[0] < 1 ? 0 : $data['collections'][$shortName]['summaries'][$cname]['total'] / ($parts[0] / 60);

        if (!$selectedCollection) {
            $selectedCollection = $cname;
        }
    }

    //$coll = $mongo->summarizer->selectCollection($selectedCollection);

    return $app['twig']->render('index.html.twig', array(
        'data' => $data,
    ));
});

$app->post('/collect', function (Request $request) use ($app) {

    $mongo = $app['mongodb.client'];

    $config = \Symfony\Component\Yaml\Yaml::parse('../config/podisum.yml');

    $c = $request->getContent();

    $data = json_decode($c, true);
    if ($data) {
        $podisum = new \Podisum($mongo, $config);
        $podisum->ensureIndexes();
        $podisum->insertMetric(
            $data,
            $request->headers->get('x-metric'),
            $request->headers->get('x-ttl'),
            $request->headers->get('x-summaries')
        );
    }

    return 'collect';
});
