<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

include 'app-init.php';
include 'app-mobile.php';


$app->get('/view/{name}', function ($name) use ($app) {
    return 'view';
});

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig', array(
    ));
});

$app->get('/angularjs', function ($name = '') use ($app) {
    return $app['twig']->render('index_angular.html.twig', array(
    ));
});

$app->get('/twig/{mode}', function ($mode = '') use ($app) {

    $mongo = $app['mongodb.client'];
    $podisum = $app['podisum'];

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

        $data['collections'][$shortName]['summaries'][$cname]['avg'] = $i ? $data['collections'][$shortName]['summaries'][$cname]['total'] / $i  :0;
        $data['collections'][$shortName]['summaries'][$cname]['avgm'] = $parts[0] < 1 ? 0 : $data['collections'][$shortName]['summaries'][$cname]['total'] / ($parts[0] / 60);

        if (!$selectedCollection) {
            $selectedCollection = $cname;
        }
    }

    //$coll = $mongo->summarizer->selectCollection($selectedCollection);

    if ($mode == '' && $app["mobile_detect"]->isMobile()) {
        $mode = 'mobile';
    }

    return $app['twig']->render( ($mode == 'mobile' ? 'mobile/index.html.twig' : 'index_twig.html.twig'), array(
        'data' => $data,
    ));
})
->value('mode', 'html');

$app->post('/collect', function (Request $request) use ($app) {
    $c = $request->getContent();

    $data = json_decode($c, true);
    if ($data) {
        $podisum = $app['podisum'];

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
