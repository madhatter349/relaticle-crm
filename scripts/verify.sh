#!/usr/bin/env bash
# The one blocking gate for this repo. The pre-push hook runs it, and the VPS
# verifier runs it nightly (/opt/vps-ci on the box).
#
# What it proves without a deploy: the stack is shaped the way Coolify needs it,
# every variable the compose file references is documented, the documented
# secrets are still blank, and no secret material got committed. It never calls
# the live instance.
set -euo pipefail
cd "$(dirname "$0")/.."

python3 - <<'PY'
import re
import subprocess
import sys
from pathlib import Path

FAIL = []


def fail(msg):
    FAIL.append(msg)


compose_path = Path("docker-compose.yaml")
for name in ("docker-compose.yaml", "README.md", ".env.example", "AGENTS.md"):
    if not Path(name).is_file():
        fail(f"{name} is missing")

text = compose_path.read_text() if compose_path.is_file() else ""
lines = text.splitlines()

# --- shape -------------------------------------------------------------------
# Coolify pulls these images. A `build:` context would mean building Relaticle's
# Laravel monorepo on the VPS, which is not what this repo is for.
services = {}
current = None
in_services = False
for line in lines:
    if re.match(r"^services:\s*$", line):
        in_services = True
        continue
    if in_services and re.match(r"^\S", line):
        in_services = False
    if in_services:
        m = re.match(r"^  ([A-Za-z0-9_-]+):\s*$", line)
        if m:
            current = m.group(1)
            services[current] = []
        elif current:
            services[current].append(line)

if not services:
    fail("docker-compose.yaml has no services")

for svc, body in services.items():
    joined = "\n".join(body)
    if not re.search(r"^\s{4}image:\s*\S+", joined, re.M):
        fail(f"service {svc}: no image: (this repo pins upstream images)")
    if re.search(r"^\s{4}build:", joined, re.M):
        fail(f"service {svc}: has build: — not a source build")
    if re.search(r":latest\b", joined):
        fail(f"service {svc}: image tag is :latest — pin a release")
    if re.search(r"^\s{4}ports:", joined, re.M):
        fail(
            f"service {svc}: publishes ports: — Coolify owns host ports; "
            "use expose: so it can route the domain"
        )
    if re.search(r"^\s{4}container_name:", joined, re.M):
        fail(f"service {svc}: fixed container_name breaks Coolify redeploys")

for required in ("app", "horizon", "scheduler", "reverb", "postgres", "redis"):
    if required not in services:
        fail(f"no {required} service")

if "app" in services and not re.search(
    r'^\s+- "8080"', "\n".join(services["app"]), re.M
):
    fail('app must expose "8080" for Coolify to route the domain')

# The web container runs the migrations; a worker racing it corrupts a fresh
# install. AUTORUN_ENABLED is what guards that.
for svc in ("horizon", "scheduler", "reverb"):
    if svc in services and 'AUTORUN_ENABLED: "false"' not in "\n".join(services[svc]):
        fail(f"{svc}: AUTORUN_ENABLED must be false (only app migrates)")
if "app" in services and 'AUTORUN_ENABLED: "true"' not in "\n".join(services["app"]):
    fail("app: AUTORUN_ENABLED must be true — it runs the migrations")

# --- env contract ------------------------------------------------------------
referenced = set(re.findall(r"\$\{([A-Z0-9_]+)", text))
for required in (
    "APP_KEY",
    "APP_URL",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD",
    "REVERB_APP_SECRET",
    "RELATICLE_VERSION",
):
    if required not in referenced:
        fail(f"compose never passes {required} to the containers")

env_path = Path(".env.example")
documented = set()
env_text = ""
if env_path.is_file():
    env_text = env_path.read_text()
    for line in env_text.splitlines():
        m = re.match(r"^([A-Z0-9_]+)=", line)
        if m:
            documented.add(m.group(1))

undocumented = sorted(referenced - documented)
if undocumented:
    fail(f".env.example does not document: {', '.join(undocumented)}")

stale = sorted(documented - referenced)
if stale:
    fail(f".env.example documents unused keys: {', '.join(stale)}")

for key in ("APP_KEY", "DB_PASSWORD", "REVERB_APP_SECRET", "MAIL_PASSWORD"):
    m = re.search(rf"^{key}=(.*)$", env_text, re.M)
    if m and m.group(1).strip():
        fail(f".env.example has a real value for {key}; it must stay empty")

m = re.search(r"^APP_URL=(.*)$", env_text, re.M)
if m:
    url = m.group(1).strip()
    if not url.startswith("https://"):
        fail(f"APP_URL must be https, got {url!r}")
    if url.endswith("/"):
        fail("APP_URL must not end with /")

m = re.search(r"^RELATICLE_VERSION=(.*)$", env_text, re.M)
if m and m.group(1).strip() in ("", "latest"):
    fail("RELATICLE_VERSION must pin a release, not `latest`")

# --- nothing secret is committed --------------------------------------------
if subprocess.run(["git", "ls-files", ".env"], capture_output=True).stdout.strip():
    fail(".env is tracked; secrets belong in Coolify, not the repo")

tracked = subprocess.run(
    ["git", "ls-files"], capture_output=True, text=True, check=False
).stdout.split()
secret_re = re.compile(
    r"(APP_KEY|DB_PASSWORD|REVERB_APP_SECRET|MAIL_PASSWORD|TOKEN|API_KEY)\s*[:=]\s*"
    r"[\"']?([A-Za-z0-9+/=_-]{24,})"
)
for path in tracked:
    p = Path(path)
    if not p.is_file() or p.stat().st_size > 200_000:
        continue
    try:
        body = p.read_text()
    except (UnicodeDecodeError, OSError):
        continue
    for match in secret_re.finditer(body):
        fail(f"{path}: looks like a real secret is committed ({match.group(1)})")

# --- real parse, when docker is available -----------------------------------
try:
    have_compose = (
        subprocess.run(
            ["docker", "compose", "version"], capture_output=True, check=False
        ).returncode
        == 0
    )
except FileNotFoundError:
    have_compose = False

if have_compose:
    proc = subprocess.run(
        ["docker", "compose", "--env-file", ".env.example", "config", "-q"],
        capture_output=True,
        text=True,
        check=False,
    )
    if proc.returncode != 0:
        fail(f"docker compose config rejected the file:\n{proc.stderr.strip()}")
else:
    print("verify: docker not present here — compose syntax checked by hand only")

if FAIL:
    print("verify: FAILED", file=sys.stderr)
    for f in FAIL:
        print(f"  - {f}", file=sys.stderr)
    sys.exit(1)

print(f"verify: OK — {len(services)} services, env contract complete, no secrets")
PY
