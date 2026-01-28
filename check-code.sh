#!/bin/bash
# Moodle Code Checker Script for Game Module
# Usage: ./check-code.sh [file]

CODECHECKER="/web/wwww/oceania_50/public/local/codechecker"

if [ ! -d "$CODECHECKER" ]; then
    echo "Error: Code checker not found at $CODECHECKER"
    exit 1
fi

if [ -n "$1" ]; then
    # Check specific file
    echo "Checking $1..."
    $CODECHECKER/vendor/bin/phpcs --standard=moodle "$1"
else
    # Check all PHP files in common directories
    echo "Checking classes/..."
    $CODECHECKER/vendor/bin/phpcs --standard=moodle classes/
    
    echo ""
    echo "Checking tests/..."
    $CODECHECKER/vendor/bin/phpcs --standard=moodle tests/
    
    echo ""
    echo "Checking mod_form.php..."
    $CODECHECKER/vendor/bin/phpcs --standard=moodle mod_form.php
    
    echo ""
    echo "Checking lib.php..."
    $CODECHECKER/vendor/bin/phpcs --standard=moodle lib.php
fi

echo ""
echo "To auto-fix issues, run: $CODECHECKER/vendor/bin/phpcbf --standard=moodle [file]"
