# Pathfinder development deployment

This deployment deliberately separates persistent infrastructure from Skaffold-managed application resources.

## Lifecycle

`deploy/k8s/infra` contains MariaDB, its retained PVC, and Redis. It is applied by `deploy/scripts/infra-up.sh` and is **not** included in `skaffold.yaml`. Exiting `skaffold dev` removes the application Deployment, Services, and Ingress, but leaves MariaDB, its PVC, its pod, Redis, and runtime Secret in place.

`deploy/k8s/app` contains the disposable application resources managed by Skaffold.

Do not use `skaffold delete` or namespace deletion as a database cleanup mechanism. Do not delete `mariadb-data-mariadb-0` unless permanent data loss is intended and confirmed.

## Prerequisites

- VPN access to the home cluster
- `~/.kube/config.hulsker`
- `kubectl`
- Skaffold v2.25+ at `~/.local/bin/skaffold`
- root `.env` containing `ESI_CLIENT_ID`, `ESI_CLIENT_SECRET`, and the registered callback URL
- the WebSocket repository checked out at the sibling path `../pathfinder_websocket`
- working DNS/TLS access to `registry.bieker.ca`

The build runs in a Kaniko pod in `dev-pathfinder`; local Docker is not required.

## Start persistent infrastructure

```bash
deploy/scripts/infra-up.sh
```

The first run creates the namespace and a `pathfinder-runtime` Secret with generated database passwords and rendered `environment.ini`. Later runs preserve the generated database passwords while refreshing the rendered application configuration from `.env`.

## Development loop

```bash
deploy/scripts/skaffold-dev.sh
```

Skaffold uploads the Pathfinder and sibling `pathfinder_websocket` build contexts to in-cluster Kaniko builders, pushes both images to `registry.bieker.ca`, deploys the app, and follows its logs. The WebSocket server runs as a sidecar in the Pathfinder pod: browser traffic reaches port 8020 through nginx at `/ws/`, while Pathfinder uses the pod-local TCP command port 5555. Port 5555 is never exposed through Ingress. Ctrl-C cleans up only Skaffold-managed app resources. Persistent infrastructure remains running.

## One-shot deployment

```bash
deploy/scripts/skaffold-run.sh
```

Unlike `skaffold dev`, `skaffold run` leaves the application resources deployed.

## One-time database initialization

After the first successful application rollout:

```bash
deploy/scripts/initialize-databases.sh
```

This bootstraps Pathfinder and Universe table schemas through the local port-forwarded setup route and imports `export/sql/eve_universe.sql.zip`. The Pochven/Trailblazer and Zarzakh supplements are applied only when their marker records are absent because current archives may already contain them. If the Universe database already contains at least 1,000 systems, the base import is skipped while supplemental markers are still verified. Set `FORCE=1` only when an intentional destructive re-import is desired.

After import, use the development setup page over the VPN to build:

1. wormhole, structure, and ship data from ESI;
2. wormhole static data from the bundled CSV;
3. the system search index;
4. system-neighbour routing data.

### Refresh from the current Fuzzwork SDE

Fuzzwork publishes a current MySQL conversion of CCP's SDE at `https://www.fuzzwork.co.uk/dump/latest-mysql.sql.gz`. Validate the repository migration without touching persistent data first:

```bash
deploy/scripts/refresh-universe-from-fuzzwork.sh
```

The script verifies Fuzzwork's published MD5, imports the SDE into a temporary database, clones `eve_universe`, runs the migration against that disposable candidate, checks row counts, landmark systems, and orphaned relationships, and removes both temporary databases. To apply a candidate that passes validation:

```bash
APPLY=1 deploy/scripts/refresh-universe-from-fuzzwork.sh
```

Apply mode first writes and verifies a compressed backup under `~/.cache/pathfinder/backups/`. Do not use `APPLY=1` against another namespace or production database without a separately reviewed backup and rollback plan.

After refreshing the base data, import the ESI-backed data and rebuild Pathfinder's derived indexes:

```bash
deploy/scripts/build-universe-derived-data.sh
```

This runs the setup API from inside the Pathfinder nginx container, imports wormholes, structures, ships, and bundled system statics, then clears and rebuilds the system search and neighbour indexes. It is intended only for the authorized development namespace.

The setup route must not remain exposed in a production deployment.

## Database-backed integration tests

Run integration tests against the initialized development database with:

```bash
deploy/scripts/test-integration.sh --colors=never
```

The runner clones both persistent schemas into uniquely named disposable test databases, creates a temporary account with access only to those clones, forwards the cluster-only MariaDB service to localhost, and removes the account, cloned databases, and port-forward on exit. This isolation is required because universe models refresh stale records from ESI during integration tests. The runner does not print database credentials or write them to the repository.

## Verify persistence

```bash
K="$HOME/.kube/config.hulsker"

kubectl --kubeconfig "$K" -n dev-pathfinder get statefulset,pod,pvc
kubectl --kubeconfig "$K" -n dev-pathfinder exec mariadb-0 -- sh -ec \
  'mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE_UNIVERSE" -Nse "SELECT COUNT(*) FROM system"'
```

Stop `skaffold dev`, verify `mariadb-0` and its PVC still exist, then start a second session and confirm Pathfinder reconnects without schema or data recreation.

## Batch 4 secrets (per-instance key provisioning and rotation)

Each instance carries two secrets, both generated once and preserved afterwards:

- `ws-command-secret` — HMAC secret shared by the co-located `php-fpm` and
  `websocket` containers for the internal TCP command channel (port 5555).
  PHP reads it as `WS_COMMAND_SECRET` from `environment.ini`; the websocket
  sidecar reads `WS_COMMAND_SECRET` env (see `application.yaml`), falling back
  to `cmd.php --tcpSecret`.
- `esi-crypto-key` — base64 of a random 32-byte libsodium key used to encrypt
  ESI access/refresh tokens at rest. PHP reads it as `ESI_CRYPTO_KEY` from
  `environment.ini`. Optional `esi-crypto-key-old` decrypts rows written
  under the previous key during rotation.

Without these keys the features degrade loudly but keep working: commands are
accepted unsigned (warning logged server-side) and tokens are stored in
plaintext (one-time `error_log` warning). Production instances MUST set them.

### Provisioning

Development (`dev-pathfinder`): `deploy/scripts/infra-up.sh` (via
`bootstrap-dev-secrets.sh`) generates both keys on first run, renders them
into `environment.ini`, stores them in the `pathfinder-runtime` Secret, and
preserves them on later runs.

Fleet instances: ensure keys exist in the instance Secret (default name
`pathfinder-runtime`; never prints values):

```bash
deploy/scripts/gen-instance-secrets.sh -n <namespace> [-s <secret-name>]
```

Then render the instance `environment.ini` with the matching entries:

```ini
WS_COMMAND_SECRET = "<ws-command-secret value>"
ESI_CRYPTO_KEY = "<esi-crypto-key value>"
```

Read values without exposing them unnecessarily, e.g.:

```bash
kubectl -n <namespace> get secret pathfinder-runtime \
  -o jsonpath='{.data.esi-crypto-key}' | base64 -d | wc -c  # expect 44
```

Restart the pod after changing keys (both containers read them at startup).

### Rotation

ESI key (no user impact when done in order):

1. `gen-instance-secrets.sh -n <ns> --rotate-esi` — current key moves to
   `esi-crypto-key-old`, a new key becomes current.
2. Update `environment.ini` the same way (`ESI_CRYPTO_KEY` = new,
   add `ESI_CRYPTO_KEY_OLD` = old) and restart the pod.
3. Wait one token lifetime (~20 min) or force refreshes; new writes use the
   new key, old rows still decrypt via `-old`.
4. Verify no rows remain under the old key (fingerprint prefix identifies
   the encrypting key; legacy plaintext/empty rows self-heal on next login):

```sql
-- replace <new-fingerprint> with the first 12 chars after 'v1:' of a fresh row
SELECT COUNT(*) FROM `character`
WHERE `esiAccessToken` != '' AND `esiAccessToken` NOT LIKE 'v1:<new-fingerprint>:%';
```

   Then delete `esi-crypto-key-old` / `ESI_CRYPTO_KEY_OLD`.

Command secret: regenerate (`--rotate-ws`) and restart the pod. Both
containers start with the same value, so the cutover is atomic per pod;
during a rolling restart, pods briefly disagree — keep the window small or
restart the Deployment at once.

### What breaks if a key is lost

- Lost ESI key: stored tokens become undecryptable; users transparently
  re-authenticate via SSO on next use (tokens are re-issued, not data).
  Restore from a Secret backup to avoid the forced re-login.
- Lost command secret: replace with a new value on both sides and restart.
  There is no recovery need — it only authenticates fresh commands.

### Upgrade note: `esiRefreshToken` column widening

Encrypted refresh tokens exceed the old `VARCHAR256` budget; the model now
declares `TEXT`. Fresh installs get it automatically. Existing databases must
widen the column once (Setup column check flags it; `fixCols` applies it):

```sql
ALTER TABLE `character` MODIFY `esiRefreshToken` TEXT;
```

## Known initial limitations

- Registry authentication must still be reviewed. The development registry accepted anonymous read requests during inspection.
- Skaffold starts with full cached Kaniko rebuilds. Live PHP sync is deferred until a development OPcache configuration and shared PHP/nginx file behavior are validated.
