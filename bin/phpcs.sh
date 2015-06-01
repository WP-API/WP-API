#!/usr/bin/env bash

if [ ! -z "$(php -v | grep 'PHP 5.2')" ]; then
	exit
fi

composer install
grunt phpcs
EXIT_CODE=$?
if [ $EXIT_CODE > 0 ]; then
	exit $EXIT_CODE
fi
grunt phplint
