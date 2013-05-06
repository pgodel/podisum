podisum
=======

Application to Summarize Logstash events built on Silex

Installation
------------

To install Podisum, you need PHP 5.3.x and MongoDB. It uses ttl collections to keep only recent data and discard old data.
The amount of time data is kept is configured with the X-ttl header.

The installation is done with Composer:

    # install composer
    curl -sS https://getcomposer.org/installer | php

    # install dependencies
    php composer.phar install

Then, configure your virtual host to to the web directory.

Configuration
-------------

Podisum creates summaries of data sent from Logstash in MongoDB. For this, you can send events from Logstash using the redis (recommended) or http outputs.

A config/podisum.yml configuration file was recently introduced. You can define your summaries in this file so sending HTTP headers is no longer necessary.

	mongo: mongodb://localhost:27017
	mongo_db: podisum
	redis: tcp://localhost:6379
	redis_key: podisum
	default_ttl: 86400
	default_sleep: 10
	metrics:
	  -
		tag: dovecot_login
		metric: "dovecot.login|login"
		ttl: 86400
		summaries: "300,3600,86400"

This configuration will match any events with the 'dovecot_login' tag and create a metric 'dovecot_login' using the login field.


Using redis output
-----------------

Example:

  	output {
		redis {
			host => "localhost"
			data_type => "list"
			key => "podisum"
			tags => ["podisum"]
		}
	}

This example will send any events with the tag podisum to the redis server with the 'podisum' key.

Start the redis client:

	$ php redis-client.php

Using http output
-----------------

Example:

    output {
        http {
            http_method => "post"
            url => "http://podisum.dev/collect"
            headers => [
                "X-metric", "test.emails|email", "X-ttl", "86400", "X-summaries", "300,3600,86400"]
        }
    }

This entry will send all matching messages to Podisum and will create a new summary called test.emails. It will create a counter
of entries with the email field. This way you can count how many instances of "foo@example.com" occurred in 300 secs (5 minutes),
3600 secs (1 hour) and 86400 (1 day). It requires 'email' to be a field in the logstash message.

You can add multiple http outputs for different summaries.

Web UI
------

To view the summaries, go to http://podisum.dev/

