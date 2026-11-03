#!/usr/bin/env bash
# The one blocking gate for this project. Run it before every push, and the
# VPS verifier runs it nightly (see /opt/vps-ci on the box).
#
# Rules:
#   * non-zero exit = broken = no push, no deploy
#   * no network calls to production services
#   * finishes in minutes, not tens of minutes
#
# Replace the placeholder step below with this project's real checks:
#   node:   npm ci && npm run typecheck && npm test && npm run build
#   python: python3 -m venv .venv-verify && . .venv-verify/bin/activate && pip install -r requirements-dev.txt && pytest -q
# Keep it self-sufficient: assume a fresh checkout with no node_modules/venv.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> [1/1] placeholder gate"
# TODO(new-project): delete this block once real checks exist.
# It keeps a brand-new repo pushable while making the omission visible.
if [ ! -f README.md ]; then
  echo "verify: README.md is missing" >&2
  exit 1
fi
if grep -q "TODO(new-project)" README.md; then
  echo "verify: placeholder gate passed, but this project has no real tests yet" >&2
fi

echo "verify: OK"
