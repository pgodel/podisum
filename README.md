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

Podisum creates summaries of data sent from Logstash in MongoDB. For this, you need to add a http output to your logstash configuration.

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

To view the summaries, go to http://podisum.dev/

