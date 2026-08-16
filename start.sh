#!/bin/bash
echo "Starting PHP built-in server with 500M upload limit..."
php -c php.ini -S 0.0.0.0:8000
