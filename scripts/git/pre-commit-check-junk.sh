#!/usr/bin/env bash
#
# Reject staged paths that should never enter this repository (.html scraps,
# logs, NDJSON dumps, editor/OS clutter).
#
# Invoked from .git/hooks/pre-commit (repository root via git rev-parse).

set -euo pipefail

is_blocked_extension () {
	local ext_lc="$1"
	case ".$ext_lc" in
		.html|.htm|.log|.ndjson|.jsonl|.tmp|.temp|.bak|.swp|.swo|.orig|.rej|.ds_store)
			return 0
			;;
	esac
	return 1
}

failed=0

while IFS= read -r path || [[ -n "$path" ]]; do
	[[ -z "$path" ]] && continue

	base="$(basename "$path")"

	case "$base" in
		.DS_Store|.directory|.localized)
			echo "Blocked staged junk file: $path"
			failed=1
			continue
			;;
	esac

	case "$base" in
		debug-*.log|xdebug.log|php_errors.log|npm-debug.log*|yarn-debug.log*|yarn-error.log*)
			echo "Blocked staged log/debug artifact: $path"
			failed=1
			continue
			;;
	esac

	case "$path" in
		*.git-rewrite/*|.git-rewrite/*|*/.git-rewrite/*)
			echo "Blocked staged git rewrite metadata: $path"
			failed=1
			continue
			;;
	esac

	ext="${path##*.}"
	if [[ "$ext" != "$path" ]]; then
		ext_lc="$(printf '%s' "$ext" | tr '[:upper:]' '[:lower:]')"
		if is_blocked_extension "$ext_lc"; then
			echo "Blocked staged file with disallowed extension (.${ext_lc}): $path"
			failed=1
		fi
	fi
done < <(git diff --cached --name-only --diff-filter=ACMR)

if [[ "$failed" -ne 0 ]]; then
	echo ""
	echo "Commit blocked: remove junk files from the index (git reset HEAD -- <path>)."
	exit 1
fi

exit 0
