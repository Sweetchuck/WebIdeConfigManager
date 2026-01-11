#!/usr/bin/env bash

echo "BEGIN Git hook: ${sghHookName}"

function sghExit ()
{
    echo "END   Git hook: ${sghHookName}"

    exit $1
}

export COMPOSER_DISABLE_XDEBUG_WARN=1

# @todo Better detection for executables: php, composer.phar.
sghRobo="$(composer config 'bin-dir')/robo"

test -s "${sghBridge}-local" && . "${sghBridge}-local"

sghTask="githook:${sghHookName}"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "$sghRobo" || sghExit 0
"${sghRobo}" help "${sghTask}" 1> /dev/null 2>&1 || sghExit 0

if [ "$sghHasInput" = 'true' ]; then
    "$sghRobo" "${sghTask}" $@ <<< $(</dev/stdin) || sghExit $?
else
    "$sghRobo" "${sghTask}" $@ || sghExit $?
fi

sghExit 0
