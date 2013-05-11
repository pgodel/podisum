<?php

$app->get('/m', function ($name = '') use ($app) {

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

    return $app['twig']->render('mobile/index.html.twig', array(
        'data' => $data,
    ));
});

