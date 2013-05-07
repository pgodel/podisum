#!/bin/bash

URL=http://podisum.dev/collect
TTL=300
SUMMARIES="300,3600,86400"
METRIC="test.emails|email"

EMAIL=$1

curl -X POST $URL -H "X-ttl: $TTL" -H "x-summaries: $SUMMARIES" -H "x-metric: $METRIC" -d "{\"@tags\":[],\"@fields\":{\"email\":[\"$EMAIL\"]},\"@message\":\"test\",\"@type\":\"stdin-type\"}"
