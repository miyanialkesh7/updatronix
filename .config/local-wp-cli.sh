#!/usr/bin/env bash
# Runs WP-CLI with Local by Flywheel's site environment (same as "Open Site Shell").
# Resolves the site via ~/.config/Local/ssh-entry/*.sh or ~/.config/Local/sites.json.
# Usage: bash .config/local-wp-cli.sh pcp|pot|integration-test [phpunit args...]

set -euo pipefail

MODE="${1:-}"
if [[ "$MODE" != "pcp" && "$MODE" != "pot" && "$MODE" != "integration-test" ]]; then
	echo "Usage: bash .config/local-wp-cli.sh pcp|pot|integration-test [phpunit args...]" >&2
	exit 1
fi
shift || true

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# WordPress root: walk up from this plugin until wp-load.php
DIR="$PLUGIN_ROOT"
WP_ROOT=""
while [[ "$DIR" != "/" ]]; do
	if [[ -f "${DIR}/wp-load.php" ]]; then
		WP_ROOT="$(cd "$DIR" && pwd -P)"
		break
	fi
	DIR="$(dirname "$DIR")"
done

if [[ -z "$WP_ROOT" ]]; then
	echo "Could not find wp-load.php above the plugin directory." >&2
	exit 1
fi

LOCAL_CONFIG_DIR="${HOME}/.config/Local"
SSH_DIR="${LOCAL_CONFIG_DIR}/ssh-entry"
SITES_JSON="${LOCAL_CONFIG_DIR}/sites.json"

_find_ssh_entry_by_cd() {
	local f cd_line cd_path
	for f in "${SSH_DIR}"/*.sh; do
		[[ -f "$f" ]] || continue
		cd_line="$(grep -E '^cd "' "$f" | head -1 || true)"
		[[ -n "$cd_line" ]] || continue
		cd_path="${cd_line#cd \"}"
		cd_path="${cd_path%\"}"
		if [[ "$(cd "$cd_path" 2>/dev/null && pwd -P)" == "$WP_ROOT" ]]; then
			echo "$f"
			return 0
		fi
	done
	return 1
}

_find_local_site_id() {
	[[ -f "$SITES_JSON" ]] || return 1
	python3 - "$WP_ROOT" "$SITES_JSON" <<'PY'
import json
import os
import sys

wp_root = os.path.realpath(sys.argv[1])
sites_path = sys.argv[2]

with open(sites_path, encoding="utf-8") as handle:
    sites = json.load(handle)

for site_id, site in sites.items():
    raw_path = site.get("path", "")
    if not raw_path:
        continue
    base = os.path.realpath(os.path.expanduser(raw_path))
    public = os.path.realpath(os.path.join(base, "app", "public"))
    if public == wp_root:
        print(site_id)
        raise SystemExit(0)

raise SystemExit(1)
PY
}

_source_ssh_entry_exports() {
	local entry="$1"
	# shellcheck disable=SC1090
	source <(awk '/^export / { print } /^cd "/ { print } /^unset NODE_ENV$/ { print }' "$entry")
}

_source_local_from_sites_json() {
	local site_id="$1"
	[[ -f "$SITES_JSON" ]] || return 1

	eval "$(python3 - "$site_id" "$SITES_JSON" "$LOCAL_CONFIG_DIR" <<'PY'
import glob
import json
import os
import shlex
import sys

site_id = sys.argv[1]
sites_path = sys.argv[2]
local_config = sys.argv[3]

with open(sites_path, encoding="utf-8") as handle:
    site = json.load(handle)[site_id]

php_version = site["services"]["php"]["version"]
mysql_version = site["services"]["mysql"]["version"]

def lightning_bin(service_prefix: str, version: str) -> str:
    pattern = os.path.join(
        local_config,
        "lightning-services",
        f"{service_prefix}-{version}*",
        "bin",
        "linux",
        "bin",
    )
    matches = sorted(glob.glob(pattern))
    if not matches:
        raise SystemExit(
            f"No Local lightning service directory for {service_prefix} {version}"
        )
    return matches[-1]

php_bin_dir = lightning_bin("php", php_version)
mysql_bin_dir = lightning_bin("mysql", mysql_version)
php_root = os.path.dirname(php_bin_dir)
run_conf = os.path.join(local_config, "run", site_id, "conf")

exports = {
    "MYSQL_HOME": os.path.join(run_conf, "mysql"),
    "PHPRC": os.path.join(run_conf, "php"),
    "WP_CLI_CONFIG_PATH": "/opt/Local/resources/extraResources/bin/wp-cli/config.yaml",
    "WP_CLI_DISABLE_AUTO_CHECK_UPDATE": "1",
    "MAGICK_CODER_MODULE_PATH": os.path.join(
        php_root,
        "ImageMagick",
        "modules-Q16",
        "coders",
    ),
    "LD_LIBRARY_PATH": os.path.join(php_root, "shared-libs"),
}

path_parts = [
    mysql_bin_dir,
    php_bin_dir,
    "/opt/Local/resources/extraResources/bin/wp-cli/posix",
    "/opt/Local/resources/extraResources/bin/composer/posix",
]

lines = []
for key, value in exports.items():
    lines.append(f"export {key}={shlex.quote(os.path.realpath(value))}")
lines.append("export PATH=" + shlex.quote(":".join(path_parts) + ":$PATH"))
lines.append("unset NODE_ENV")
print("\n".join(lines))
PY
)"
}

SSH_ENTRY=""
if [[ -d "$SSH_DIR" ]]; then
	SSH_ENTRY="$(_find_ssh_entry_by_cd || true)"
fi

LOCAL_SITE_ID=""
if [[ -z "$SSH_ENTRY" ]] && LOCAL_SITE_ID="$(_find_local_site_id || true)" && [[ -n "$LOCAL_SITE_ID" ]]; then
	if [[ -f "${SSH_DIR}/${LOCAL_SITE_ID}.sh" ]]; then
		SSH_ENTRY="${SSH_DIR}/${LOCAL_SITE_ID}.sh"
	fi
fi

if [[ -n "$SSH_ENTRY" ]]; then
	_source_ssh_entry_exports "$SSH_ENTRY"
elif LOCAL_SITE_ID="$(_find_local_site_id || true)" && [[ -n "$LOCAL_SITE_ID" ]]; then
	_source_local_from_sites_json "$LOCAL_SITE_ID"
else
	echo "Local WP not detected for WordPress path:" >&2
	echo "  ${WP_ROOT}" >&2
	echo "Ensure the site is registered in Local (~/.config/Local/sites.json)." >&2
	echo "If you use Site Shell, open it once so ~/.config/Local/ssh-entry/ is generated." >&2
	exit 1
fi

REQUIRE_FILE="${PLUGIN_ROOT}/.config/pcp-setup.php"

# Paths and files not part of the distributable plugin (dev / repo tooling).
# - tests/: PHPUnit bootstraps + integration tests (not shipped in the zip from .distignore).
# - bin/: install-wp-tests.sh triggers application_detected in PCP.
PCP_EXCLUDE_DIRS=".config,.github,.cursor,bin,tests"
PCP_EXCLUDE_FILES="workflow.md,.distignore,.gitignore,.gitattributes,.editorconfig,updatronix.zip"

# Legitimate use of core update APIs for an updates-management plugin (not a bundled updater).
PCP_IGNORE_CODES="plugin_updater_detected,update_modification_detected"

case "$MODE" in
	pcp)
		wp plugin check updatronix \
			--require="$REQUIRE_FILE" \
			--exclude-directories="$PCP_EXCLUDE_DIRS" \
			--exclude-files="$PCP_EXCLUDE_FILES" \
			--ignore-codes="$PCP_IGNORE_CODES"
		;;
	pot)
		wp i18n make-pot "$PLUGIN_ROOT" "${PLUGIN_ROOT}/languages/updatronix.pot" --exclude=assets/build
		;;
	integration-test)
		if [[ -f "${PLUGIN_ROOT}/.config/wp-tests.env" ]]; then
			# shellcheck disable=SC1090
			source "${PLUGIN_ROOT}/.config/wp-tests.env"
		fi
		if [[ -z "${WP_TESTS_DIR:-}" ]] || [[ ! -f "${WP_TESTS_DIR}/includes/functions.php" ]]; then
			echo "WP_TESTS_DIR must point to wordpress-tests-lib. Copy .config/wp-tests-env.example to .config/wp-tests.env, run bin/install-wp-tests.sh once, then retry." >&2
			exit 1
		fi
		cd "$PLUGIN_ROOT" || exit 1
		exec "${PLUGIN_ROOT}/vendor/bin/phpunit" --configuration="${PLUGIN_ROOT}/.config/phpunit.integration.xml.dist" "$@"
		;;
esac
