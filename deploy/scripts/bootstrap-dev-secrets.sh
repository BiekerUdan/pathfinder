#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
KUBECONFIG_PATH="${KUBECONFIG_PATH:-$HOME/.kube/config.hulsker}"
NAMESPACE="${NAMESPACE:-dev-pathfinder}"
ENV_FILE="${ENV_FILE:-$ROOT_DIR/.env}"
EXPECTED_CALLBACK="https://dev-pathfinder.bieker.ca/sso/callbackAuthorization"

kubectl_cmd=(kubectl --kubeconfig "$KUBECONFIG_PATH")

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE. Copy .env.example and add the development EVE application credentials." >&2
  exit 1
fi

chmod 600 "$ENV_FILE"

# Read only the required local values without sourcing executable shell content.
eval "$(python3 - "$ENV_FILE" <<'PY'
import shlex
import sys

values = {}
with open(sys.argv[1], encoding='utf-8') as stream:
    for raw in stream:
        line = raw.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, value = line.split('=', 1)
        key = key.strip()
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in "\"'":
            value = value[1:-1]
        values[key] = value

for key in ('ESI_CLIENT_ID', 'ESI_CLIENT_SECRET'):
    if not values.get(key):
        raise SystemExit(f'Missing required {key} in {sys.argv[1]}')

for key in ('ESI_CLIENT_ID', 'ESI_CLIENT_SECRET', 'ESI_CALLBACK_URL'):
    print(f'{key}={shlex.quote(values.get(key, ""))}')
PY
)"

if [[ -n "${ESI_CALLBACK_URL:-}" && "$ESI_CALLBACK_URL" != "$EXPECTED_CALLBACK" ]]; then
  echo "Warning: ESI_CALLBACK_URL does not match $EXPECTED_CALLBACK" >&2
fi

"${kubectl_cmd[@]}" create namespace "$NAMESPACE" --dry-run=client -o yaml \
  | "${kubectl_cmd[@]}" apply -f - >/dev/null

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT
chmod 700 "$tmp_dir"

if "${kubectl_cmd[@]}" -n "$NAMESPACE" get secret pathfinder-runtime >/dev/null 2>&1; then
  "${kubectl_cmd[@]}" -n "$NAMESPACE" get secret pathfinder-runtime \
    -o jsonpath='{.data.MYSQL_ROOT_PASSWORD}' | base64 -d > "$tmp_dir/MYSQL_ROOT_PASSWORD"
  "${kubectl_cmd[@]}" -n "$NAMESPACE" get secret pathfinder-runtime \
    -o jsonpath='{.data.MYSQL_PASSWORD}' | base64 -d > "$tmp_dir/MYSQL_PASSWORD"
  # preserve per-instance Batch 4 secrets across re-runs (empty file = generate below)
  "${kubectl_cmd[@]}" -n "$NAMESPACE" get secret pathfinder-runtime \
    -o jsonpath='{.data.ws-command-secret}' | base64 -d > "$tmp_dir/WS_COMMAND_SECRET" 2>/dev/null || true
  "${kubectl_cmd[@]}" -n "$NAMESPACE" get secret pathfinder-runtime \
    -o jsonpath='{.data.esi-crypto-key}' | base64 -d > "$tmp_dir/ESI_CRYPTO_KEY" 2>/dev/null || true
  # preserve a previous ESI key mid-rotation (see runbook); absent normally
  "${kubectl_cmd[@]}" -n "$NAMESPACE" get secret pathfinder-runtime \
    -o jsonpath='{.data.esi-crypto-key-old}' | base64 -d > "$tmp_dir/ESI_CRYPTO_KEY_OLD" 2>/dev/null || true
else
  python3 - <<'PY' > "$tmp_dir/MYSQL_ROOT_PASSWORD"
import secrets
print(secrets.token_hex(32), end='')
PY
  python3 - <<'PY' > "$tmp_dir/MYSQL_PASSWORD"
import secrets
print(secrets.token_hex(32), end='')
PY
fi
chmod 600 "$tmp_dir/MYSQL_ROOT_PASSWORD" "$tmp_dir/MYSQL_PASSWORD"

# per-instance Batch 4 secrets: generate once, preserve afterwards
if [[ ! -s "$tmp_dir/WS_COMMAND_SECRET" ]]; then
  python3 -c 'import secrets; print(secrets.token_hex(32), end="")' > "$tmp_dir/WS_COMMAND_SECRET"
fi
if [[ ! -s "$tmp_dir/ESI_CRYPTO_KEY" ]]; then
  python3 -c 'import os,base64; print(base64.b64encode(os.urandom(32)).decode(), end="")' > "$tmp_dir/ESI_CRYPTO_KEY"
fi
chmod 600 "$tmp_dir/WS_COMMAND_SECRET" "$tmp_dir/ESI_CRYPTO_KEY"

export WS_COMMAND_SECRET ESI_CRYPTO_KEY
WS_COMMAND_SECRET="$(<"$tmp_dir/WS_COMMAND_SECRET")"
ESI_CRYPTO_KEY="$(<"$tmp_dir/ESI_CRYPTO_KEY")"

export PATHFINDER_DB_PASSWORD
PATHFINDER_DB_PASSWORD="$(<"$tmp_dir/MYSQL_PASSWORD")"
export ESI_CLIENT_ID ESI_CLIENT_SECRET

python3 - "$tmp_dir/environment.ini" <<'PY'
import os
import sys


def ini(value: str) -> str:
    return '"' + value.replace('\\', '\\\\').replace('"', '\\"') + '"'

out = f'''[ENVIRONMENT]
SERVER = PRODUCTION

[ENVIRONMENT.PRODUCTION]
BASE =
URL = {ini("https://dev-pathfinder.bieker.ca")}
DEBUG = 1

DB_PF_DNS = {ini("mysql:host=mariadb;port=3306;dbname=pathfinder")}
DB_PF_NAME = pathfinder
DB_PF_USER = pathfinder
DB_PF_PASS = {ini(os.environ["PATHFINDER_DB_PASSWORD"])}

DB_UNIVERSE_DNS = {ini("mysql:host=mariadb;port=3306;dbname=eve_universe")}
DB_UNIVERSE_NAME = eve_universe
DB_UNIVERSE_USER = pathfinder
DB_UNIVERSE_PASS = {ini(os.environ["PATHFINDER_DB_PASSWORD"])}

DB_CCP_DNS =
DB_CCP_NAME =
DB_CCP_USER =
DB_CCP_PASS =

CCP_SSO_URL = https://login.eveonline.com
CCP_SSO_CLIENT_ID = {ini(os.environ["ESI_CLIENT_ID"])}
CCP_SSO_SECRET_KEY = {ini(os.environ["ESI_CLIENT_SECRET"])}
CCP_SSO_JWK_CLAIM = login.eveonline.com
CCP_SSO_DOWNTIME = 11:00

CCP_ESI_URL = https://esi.evetech.net
CCP_ESI_DATASOURCE = tranquility
CCP_ESI_SCOPES = esi-location.read_online.v1,esi-location.read_location.v1,esi-location.read_ship_type.v1,esi-ui.write_waypoint.v1,esi-ui.open_window.v1,esi-universe.read_structures.v1,esi-corporations.read_corporation_membership.v1,esi-clones.read_clones.v1,esi-characters.read_corporation_roles.v1,esi-search.search_structures.v1
CCP_ESI_SCOPES_ADMIN =

SMTP_HOST = localhost
SMTP_PORT = 25
SMTP_SCHEME = TLS
SMTP_USER =
SMTP_PASS =
SMTP_FROM = pathfinder@localhost
SMTP_ERROR = pathfinder@localhost

SOCKET_HOST = 127.0.0.1
SOCKET_PORT = 5555

# Batch 4 per-instance secrets (generated once by this script, preserved on re-run)
WS_COMMAND_SECRET = {ini(os.environ["WS_COMMAND_SECRET"])}
ESI_CRYPTO_KEY = {ini(os.environ["ESI_CRYPTO_KEY"])}

CRON_TOKEN =
'''

with open(sys.argv[1], 'w', encoding='utf-8') as stream:
    stream.write(out)
PY
chmod 600 "$tmp_dir/environment.ini"

secret_args=(
  --from-file=MYSQL_ROOT_PASSWORD="$tmp_dir/MYSQL_ROOT_PASSWORD"
  --from-file=MYSQL_PASSWORD="$tmp_dir/MYSQL_PASSWORD"
  --from-file=environment.ini="$tmp_dir/environment.ini"
  --from-file=ws-command-secret="$tmp_dir/WS_COMMAND_SECRET"
  --from-file=esi-crypto-key="$tmp_dir/ESI_CRYPTO_KEY"
)
if [[ -s "$tmp_dir/ESI_CRYPTO_KEY_OLD" ]]; then
  secret_args+=(--from-file=esi-crypto-key-old="$tmp_dir/ESI_CRYPTO_KEY_OLD")
fi

"${kubectl_cmd[@]}" -n "$NAMESPACE" create secret generic pathfinder-runtime \
  "${secret_args[@]}" \
  --dry-run=client -o yaml \
  | "${kubectl_cmd[@]}" apply -f - >/dev/null

echo "Development namespace and runtime Secret are ready (secret values not displayed)."
