#!/usr/bin/env bash
# Run Psalm 5 with PHP 8.2 without changing the host PHP installation.
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
image="osm-psalm-php82:local"

if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker is required for the isolated Psalm test.' >&2
  exit 1
fi

# Prefer non-root Docker; fall back to sudo for the Docker commands only.
# Keep the script under the invoking user so the bind mount retains user ownership.
docker_cmd=(docker)
if { [[ -S /var/run/docker.sock ]] && [[ ! -w /var/run/docker.sock ]]; } || ! docker version --format '{{.Server.Version}}' >/dev/null 2>&1; then
  if ! command -v sudo >/dev/null 2>&1; then
    echo 'Docker access denied and sudo is unavailable.' >&2
    exit 1
  fi
  echo 'Docker daemon inaccessible as current user; using sudo docker.' >&2
  docker_cmd=(sudo docker)
fi

if [[ ! -f "$repo_root/vendor/bin/psalm" ]]; then
  echo 'Psalm dependencies are missing; run composer install on the host first.' >&2
  exit 1
fi

"${docker_cmd[@]}" build --tag "$image" "$repo_root/docker/php82-psalm"
"${docker_cmd[@]}" run --rm --init \
  --user "$(id -u):$(id -g)" \
  --mount "type=bind,source=$repo_root,target=/work" \
  --workdir /work \
  "$image" php vendor/bin/psalm --threads=1 --no-cache
