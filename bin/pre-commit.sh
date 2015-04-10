#!/bin/bash

set -e

# Navigate to root of git directory if not there already
if [ ! -e .git ]; then
	cd $(git rev-parse --show-cdup)
fi

source bin/ci-env.sh

# Check PHP syntax
find . \( -name '*.php' \) -exec php -lf {} \;

# Check WordPress Coding Standards with PHP_CodeSniffer
if command -v phpcs >/dev/null 2>&1 && phpcs -i | grep -sq "$WPCS_STANDARD"; then
	phpcs --standard="$WPCS_STANDARD" .
fi

# Run PHPUnit
if command -v phpunit >/dev/null 2>&1; then
	phpunit
fi
