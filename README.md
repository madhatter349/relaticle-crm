# relaticle-crm

Deploys [Relaticle](https://github.com/relaticle/relaticle) — the open-source CRM
with an MCP server — onto the VPS with Coolify. This repo is the deployment half
only: the stack, the env contract and the gate. Relaticle's source is not
vendored; the containers run the published upstream image.

## Where it lives

| Thing | Value |
|---|---|
| URL | <https://crm.forefrontinvestors.com> — CRM at `/app`, instance admin at `/sysadmin` |
| Coolify app | `d0lmqwpz0fc2usknvwglofcq` (project Relaticle, environment production) |
| Repo | public, `madhatter349/relaticle-crm`, pulled by Coolify |
| Redeploy | push to `main` — GitHub webhook `web` (id 706684470) POSTs to Coolify's manual webhook |

This hostname replaced a Twenty CRM deployment on 2026-11-03. Its database dump
and storage archive are on the VPS in `/root/backups/twenty-*`, and its named
volumes (`o5iehgnyxsbhvcsdp5kxnikc_*`) were left in place.

## What runs

| Service | Image | Notes |
|---|---|---|
| `app` | built `relaticle-crm-app:3.8.0` | upstream image + one nginx snippet, on :8080; runs migrations on boot |
| `horizon` | same built image | queue worker (`artisan horizon`) — chat turns run here |
| `scheduler` | same built image | `artisan schedule:work` |
| `reverb` | same built image | websocket server; the app proxies it at `/reverb` |
| `ai-proxy` | built `relaticle-crm-ai-proxy:1` | nginx; adds the `x-opencode-session` header the assistant's upstream demands |
| `postgres` | `postgres:17-alpine` | volume `postgres` |
| `redis` | `redis:7-alpine` | append-only; sessions, cache and queue |

All four PHP services run one image, built `FROM ghcr.io/relaticle/relaticle:<RELATICLE_VERSION>`.
It adds `docker/root-redirect.conf` (sends `/` to `/app`; the upstream image serves
its marketing homepage there) and `docker/patch-ai-config.php` (switches the
self-hosted AI provider to the chat-completions driver the gateway accepts, failing
the build if upstream's config moves). A bind mount would be dropped by Coolify,
hence the thin image. The workers use the same build because the chat job runs on
`horizon` — a worker on the unpatched upstream would keep posting to `/responses`.

Only `app` is exposed to Traefik, on 8080. Nothing publishes a host port —
Coolify owns those. Migrations run only in `app` (`AUTORUN_ENABLED`), so a
worker cannot race them on a fresh install.

## Env

Set in Coolify (app → Environment Variables), never in this repo. `.env.example`
is the contract; the gate fails if the two drift apart.

| Key | How to make it |
|---|---|
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"` — decrypts encrypted custom fields |
| `DB_PASSWORD` | `openssl rand -hex 32` |
| `REVERB_APP_SECRET` | `openssl rand -hex 32` — separate from `APP_KEY` so it rotates alone |
| `APP_URL` | `https://crm.forefrontinvestors.com`, no trailing slash |
| `TRUSTED_HOSTS` | `localhost` — the container health check calls `localhost:8080/up` |
| `MAIL_*` | Purelymail SMTP, mailbox `josh@forefrontinvestors.com`, port 587, TLS |

### Assistant ("Rela")

The chat is wired to DeepSeek V4.1 Flash through OpenCode Go. Three keys carry it:

| Key | Value |
|---|---|
| `SELF_HOSTED_AI_URL` | `http://ai-proxy:8080/v1` — the in-stack proxy, not the vendor |
| `SELF_HOSTED_AI_KEY` | an OpenCode API key (`oc_sk_…`), minted per install, not shared with other tooling |
| `SELF_HOSTED_AI_MODELS` | `deepseek-v4.1-flash` |

Why a proxy: OpenCode Go rejects any request without an `x-opencode-session` id
(`400 MissingSessionID`), and Laravel's AI SDK can set a URL and a key but no
request header. `docker/ai-proxy` adds that header, mints a per-request id when
the caller sends none, forwards the `Authorization` it was given, and streams
the reply. It stores no secret.

This is a coding-agent subscription whose traffic is monitored, so it is the
right fit for a low-volume single-workspace CRM and the wrong fit for heavy
multi-tenant chat. `chat:models --probe=selfhosted:deepseek-v4.1-flash` proves
tool-calling against the endpoint without waiting for a UI round trip.

`APP_PANEL_DOMAIN` is deliberately unset: the panel is served at `/app` on the
same host, which keeps one hostname for passkeys and for the websocket.

## How this project is built and run

| Concern | Where it happens |
|---|---|
| Gate | `scripts/verify.sh` — pre-push hook and nightly on the VPS |
| CI service | **none.** No GitHub Actions, no minutes |
| Deploy | Coolify, on push to `main` |
| Scheduled work | a systemd timer on the VPS, never a GitHub `schedule:` |

## Operating it

```sh
./scripts/verify.sh          # the gate; must pass before pushing
```

- **First admin:** there is no seeded account. Create one inside the `app`
  container:

  ```sh
  docker exec -it $(docker ps --format '{{.Names}}' | grep -m1 relaticle-crm-app) \
    php artisan make:filament-user
  ```

  Pick the `app` panel; the account is auto-verified. `php artisan sysadmin:create`
  makes the instance-wide administrator instead (`/sysadmin`).
- **Deploy:** `git push origin main`. The GitHub `push` webhook posts to
  `https://382972.xyz/webhooks/source/github/events/manual` and signs the body
  with the app's `manual_webhook_secret_github`, which Coolify requires — an
  unsigned delivery is answered `No matching application or invalid signature`.
  If that secret is rotated in the UI, update the GitHub hook to match.
- **Upgrade:** bump `RELATICLE_VERSION` in Coolify, then redeploy. Migrations run
  on app boot; the old container keeps serving until the new one is healthy.
- **Roll back:** set `RELATICLE_VERSION` back and redeploy. Migrations are
  forward only, so a rollback across a schema change needs the matching dump.
- **Backups:** `pg_dump -U relaticle relaticle`, the `storage` volume (uploads),
  and `APP_KEY`. A dump without `APP_KEY` restores unreadable encrypted columns.
- **Mail:** `MAIL_MAILER=log` writes mail to the container log. Invites and
  reminders need `smtp` and a working mailbox password.

## Conventions

- `scripts/verify.sh` is the single source of truth for "is this good?".
- No `.github/workflows/`. Push → deploy. Schedule → VPS timer.
- Registered for nightly verification in `/opt/vps-ci/registry.tsv`.
