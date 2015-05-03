#!/usr/bin/env bash

if [ ! -z "$(php -v | grep 'PHP 5.2')" ]; then
	exit
fi

composer install
grunt phpcs
grunt phplint
