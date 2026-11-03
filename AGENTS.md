# relaticle-crm

Deployed on the VPS by Coolify. Tests live in `scripts/verify.sh`.

- Run `./scripts/verify.sh` before saying work is done; the pre-push hook runs
  it too, so a red gate cannot be pushed.
- Do not add `.github/workflows/`. Scheduling belongs in a systemd timer on the
  VPS; gating belongs in `scripts/verify.sh`.
- Keep the gate self-sufficient: it must work on a fresh checkout.
