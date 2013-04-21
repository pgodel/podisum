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


    $collections = $mongo->summarizer->listCollections();

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

    $c = $request->getContent();

    $data = json_decode($c, true);
    if ($data) {
        $data['metric'] = $request->headers->get('x-metric');
        $data['ttl'] = $request->headers->get('x-ttl');
        $data['summaries'] = $request->headers->get('x-summaries');

        $now = new \MongoDate();

        $collection = $mongo->summarizer->messages;
        $collection->ensureIndex(
            'cts', array('expireAfterSeconds' => $data['ttl'])
        );

        $doc = array(
            'cts' => $now,
            'data' => $data,
        );

        $collection->insert($doc);

        $summaries = explode(',', $data['summaries']);

        list($metricName, $fieldsStr) = explode('|', $data['metric']);
        $fields = explode(',', $fieldsStr);
        $metricName = str_replace('.', '_', $metricName);

        foreach ($summaries as $sm) {
            $collection = $mongo->summarizer->selectCollection('s'.$sm.'_' . $metricName);

            $t = time();
            $ttl = $t - $t % $sm;

            foreach ($fields as $field) {
                if (empty($field) || !isset($data['@fields'])) {
                    continue;
                }
                $criteria = array(
                    'field' => $data['@fields'][$field][0],
                    'ttl' => $ttl,
                );

                $docs = $collection->find($criteria)->count();

                if (!$docs) {
                    $collection->ensureIndex(
                        array('cts' => 1), array('expireAfterSeconds' => (int) $sm)
                    );

                    $collection->ensureIndex(
                        array(
                            'count' => -1,
                        )
                    );

                    $collection->ensureIndex(
                        array(
                            'field' => 1,
                            'ttl' => 1,
                        )
                    );

                    $values = array(
                        'cts' => $now,
                        'field' => $data['@fields'][$field][0],
                        'ttl' => $ttl,
                    );
                } else {
                    $values = null;
                }

                $counters = array(
                    'counter' => 1,
                );

                $docData = array(
                    '$inc' => $counters,
                );
                if ($values) {
                    $docData['$set'] = $values;
                }

                $collection->update($criteria, $docData,
                    array(
                        'upsert' => true,
                    ));

            }


        }

    }

    return 'collect';
});
