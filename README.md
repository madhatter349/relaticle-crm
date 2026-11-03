# relaticle-crm

TODO(new-project): one paragraph on what this does and how it is deployed.

## How this project is built and run

| Concern | Where it happens |
|---|---|
| Tests | `scripts/verify.sh` — run by the pre-push hook and nightly on the VPS |
| CI service | **none.** GitHub Actions is deliberately not used: no minutes, no billing dependency |
| Deploy | Coolify on the VPS, triggered by the GitHub push webhook |
| Scheduled work | a systemd timer on the VPS, not a GitHub `schedule:` cron |

## Local

```sh
./scripts/verify.sh     # the gate; must pass before pushing
```

## Deploying

Push to `main`. Coolify builds the commit and swaps the container; the previous
container keeps serving until the new one is healthy.

## Conventions

- `scripts/verify.sh` is the single source of truth for "is this good?".
  Nothing else may be the only gate.
- No `.github/workflows/`. If something must run on a schedule, it is a VPS
  timer. If something must run on a push, it is a deploy or a `verify.sh` step.
- Registered for nightly verification in `/opt/vps-ci/registry.tsv` on the VPS.
