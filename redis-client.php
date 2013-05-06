<?php

require_once __DIR__ . '/vendor/autoload.php';

if (!file_exists('config/podisum.yml')) {
    die("No config file");
}


$config = \Symfony\Component\Yaml\Yaml::parse('config/podisum.yml');
if (class_exists('MongoClient')) {
    $mongo = new \MongoClient($config['mongo']);
} else {
    $mongo = new \Mongo($config['mongo']);
}
$podisum = new Podisum($mongo, $config);
$podisum->ensureIndexes();

$redis = new Predis\Client($config['redis']);

$len = $redis->llen($config['redis_key']);

echo "Found $len entries waiting on list...\n";

$processed = 0;
while(1) {
    $value = $redis->rpop($config['redis_key']);
    if (null === $value) {
        echo "no more data, sleeping...\n";
        sleep(1);
        continue;
    }

    $data = json_decode($value, true);
    if (!$data) {
        continue;
    }

    $process = false;
    foreach($data['@tags'] as $tag) {
        if (null !== $cfg = $podisum->getConfigForTag($tag)) {
            $process = true;
            break;
        }
    }

    if (!$process) {
        continue;
    }

    $podisum->insertMetric($data, $cfg['metric'], $cfg['ttl'], $cfg['summaries']);
    $processed++;
    echo $processed. " - processing ".$cfg['metric']."\n";
}


