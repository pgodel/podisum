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
$sleep = $podisum->getConfig('default_sleep', 1);

while(1) {
    $value = $redis->rpop($config['redis_key']);
    if (null === $value) {
        echo "no more data, sleeping...\n";
        sleep($sleep);
        continue;
    }

    $data = json_decode($value, true);
    if (!$data) {
        echo "Invalid json string ".$value;
        continue;
    }

    $process = false;
    foreach($data['@tags'] as $tag) {
        $cfgs = $podisum->getConfigForTag($tag);
        foreach($cfgs as $cfg) {
            $podisum->insertMetric($data, $cfg['metric'], $cfg['ttl'], $cfg['summaries']);
            $processed++;
            echo $processed. " - processing ".$cfg['metric']."\n";
            $process = true;
        }
    }
    if (!$process) {
        echo "No config for tags ".implode(", ", $data['@tags']);
    }
}


