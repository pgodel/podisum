<?php


$app->get('/api/collections', function () use ($app) {

    $mongo = $app['mongodb.client'];
    $podisum = $app['podisum'];

    $db = $mongo->selectDB($podisum->getConfig('mongo_db', 'podisum'));
    $collections = $db->listCollections();

    $data = array();

    foreach($collections as $c) {
        $cname = $c->getName();

        if ($cname[0] != 's') {
            continue;
        }

        $parts = explode('_', substr($cname, 1), 2);

        if (empty($parts[0])) {
            continue;
        }


        $data[] = array(
            'collectionName' => $cname,
            'shortName' => $parts[1],
            'ttl' => $parts[0],
        );

    }

    return json_encode($data);
});
