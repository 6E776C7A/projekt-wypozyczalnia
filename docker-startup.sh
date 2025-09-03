#!/bin/sh
set -e

if [ ! -d vendor ]; then
    composer install
fi

exec "$@"