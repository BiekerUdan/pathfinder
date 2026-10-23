#!/usr/bin/env bash
# Ensure per-instance Batch 4 secrets exist in a Kubernetes Secret.
# Idempotent: never overwrites existing keys. Prints key names only, never values.
#
# Usage:
#   gen-instance-secrets.sh [-n namespace] [-s secret-name] [--rotate-esi] [--rotate-ws]
#
#   --rotate-esi  move current esi-crypto-key -> esi-crypto-key-old and generate
#                 a new current key (step 1 of ESI key rotation; see runbook)
#   --rotate-ws   generate a new ws-command-secret (requires pod restart to
#                 pick up; both containers read it at startup)
#
# Kubeconfig/context selection follows your environment (KUBECONFIG, current
# context). Always pass an explicit --kubeconfig or KUBECONFIG in fleet use.
set -euo pipefail

NAMESPACE="dev-pathfinder"
SECRET="pathfinder-runtime"
ROTATE_ESI=0
ROTATE_WS=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    -n) NAMESPACE="$2"; shift 2;;
    -s) SECRET="$2"; shift 2;;
    --rotate-esi) ROTATE_ESI=1; shift;;
    --rotate-ws) ROTATE_WS=1; shift;;
    *) echo "usage: $0 [-n namespace] [-s secret] [--rotate-esi] [--rotate-ws]" >&2; exit 1;;
  esac
done

need() { command -v "$1" >/dev/null 2>&1 || { echo "missing: $1" >&2; exit 1; }; }
need kubectl; need python3; need base64

get_key() { # <key> -> prints value or nothing
  kubectl -n "$NAMESPACE" get secret "$SECRET" -o jsonpath="{.data.$1}" 2>/dev/null | base64 -d 2>/dev/null || true
}

patch_keys() { # <json map of key->base64> merges keys into the Secret
  kubectl -n "$NAMESPACE" patch secret "$SECRET" --type=merge -p "{\"data\":$1}" >/dev/null
}

gen_hex() { python3 -c 'import secrets; print(secrets.token_hex(32), end="")'; }
gen_b64key() { python3 -c 'import os,base64; print(base64.b64encode(os.urandom(32)).decode(), end="")'; }
b64() { printf '%s' "$1" | base64 -w0; }

if ! kubectl -n "$NAMESPACE" get secret "$SECRET" >/dev/null 2>&1; then
  echo "secret $SECRET not found in $NAMESPACE; creating with Batch 4 keys only" >&2
  kubectl -n "$NAMESPACE" create secret generic "$SECRET" \
    --from-literal=ws-command-secret="$(gen_hex)" \
    --from-literal=esi-crypto-key="$(gen_b64key)" >/dev/null
  echo "created $SECRET (ws-command-secret, esi-crypto-key)"
  exit 0
fi

# --- ws-command-secret: ensure exists or rotate on demand ---
if [[ "$ROTATE_WS" -eq 1 ]]; then
  patch_keys "$(printf '{"ws-command-secret":"%s"}' "$(b64 "$(gen_hex)")")"
  echo "rotated ws-command-secret (restart the pod to apply)"
elif [[ -z "$(get_key ws-command-secret)" ]]; then
  patch_keys "$(printf '{"ws-command-secret":"%s"}' "$(b64 "$(gen_hex)")")"
  echo "added ws-command-secret"
else
  echo "ws-command-secret present (unchanged)"
fi

# --- esi-crypto-key: ensure exists or rotate on demand ---
if [[ "$ROTATE_ESI" -eq 1 ]]; then
  current="$(get_key esi-crypto-key)"
  if [[ -z "$current" ]]; then
    echo "cannot rotate: no current esi-crypto-key" >&2; exit 1
  fi
  patch_keys "$(printf '{"esi-crypto-key-old":"%s","esi-crypto-key":"%s"}' "$(b64 "$current")" "$(b64 "$(gen_b64key)")")"
  echo "rotated esi-crypto-key (old key kept as esi-crypto-key-old; restart pod, sweep, then drop -old)"
elif [[ -z "$(get_key esi-crypto-key)" ]]; then
  patch_keys "$(printf '{"esi-crypto-key":"%s"}' "$(b64 "$(gen_b64key)")")"
  echo "added esi-crypto-key"
else
  echo "esi-crypto-key present (unchanged)"
fi

if [[ -n "$(get_key esi-crypto-key-old)" && "$ROTATE_ESI" -eq 0 ]]; then
  echo "note: esi-crypto-key-old still present (finish or abort the rotation)"
fi
