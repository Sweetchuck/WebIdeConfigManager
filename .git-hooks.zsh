#!/usr/bin/env zsh

: "${sghHookName:?'argument is required'}"
: "${sghHasInput:?'argument is required'}"

echo 1>&2 "BEGIN Git hook: ${sghHookName}"

function sghExit ()
{
	if [[ "${2}" != '' ]]; then
		echo 1>&2 "${2}"
	fi

	echo 1>&2 "END   Git hook: ${sghHookName}"

	exit "${1}"
}

# @todo Better detection for executables: php, composer.phar and robo.
robo="$(composer config 'bin-dir')/robo"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "${robo}" || sghExit 0 'robo executable not found'
"${robo}" help "githook:${sghHookName}" 1> /dev/null 2>&1 || sghExit 0 'robo task does not exist.'

if [ "${sghHasInput}" = 'true' ]; then
	"${robo}" "githook:${sghHookName}" "${@}" <&0 || sghExit $?
else
	"${robo}" "githook:${sghHookName}" "${@}" || sghExit $?
fi

sghExit 0
