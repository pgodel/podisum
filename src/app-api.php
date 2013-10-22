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

        $shortName = $parts[1];
        $data['collections'][$shortName]['name'] = $shortName;
        $data['collections'][$shortName]['summaries'][$cname]['ttl'] = $parts[0];

        $data['collections'][$shortName]['summaries'][$cname]['entries'] = iterator_to_array($c->find()->sort(array('counter' => -1))->limit(20));

        foreach($data['collections'][$shortName]['summaries'][$cname]['entries'] as $idx => $entry) {
            unset($data['collections'][$shortName]['summaries'][$cname]['entries'][$idx]['_id']);
            unset($data['collections'][$shortName]['summaries'][$cname]['entries'][$idx]['cts']);
        }
        $data['collections'][$shortName]['summaries'][$cname]['entries'] = array_values($data['collections'][$shortName]['summaries'][$cname]['entries']);

        $data['collections'][$shortName]['summaries'][$cname]['total'] = 0;
        $i = 0;
        foreach($data['collections'][$shortName]['summaries'][$cname]['entries'] as $entry) {
            $data['collections'][$shortName]['summaries'][$cname]['total'] += $entry['counter'];
            $i++;
        }

        $data['collections'][$shortName]['summaries'][$cname]['avg'] = $i ? $data['collections'][$shortName]['summaries'][$cname]['total'] / $i  :0;
        $data['collections'][$shortName]['summaries'][$cname]['avgm'] = $parts[0] < 1 ? 0 : $data['collections'][$shortName]['summaries'][$cname]['total'] / ($parts[0] / 60);

     //   unset($data['collections'][$shortName]['summaries'][$cname]['entries']);
    }

    return isset($data['collections']) ? $data['collections'] : array();
});
