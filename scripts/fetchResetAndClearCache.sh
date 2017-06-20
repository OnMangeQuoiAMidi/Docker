#!/usr/bin/env bash

git fetch origin;
git reset origin/master --hard;
php bin/console cache:clear --env=prod;
./scripts/resetDb.sh
./scripts/phpmetrics.sh

