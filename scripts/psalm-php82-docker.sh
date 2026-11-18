#!/usr/bin/env bash
# Run Psalm 5 with PHP 8.2 without changing the host PHP installation.
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
image="osm-psalm-php82:local"

if ! command -v docker >/dev/null 2>&1; then
  echo 'Docker is required for the isolated Psalm test.' >&2
  exit 1
fi

if [[ ! -f "$repo_root/vendor/bin/psalm" ]]; then
  echo 'Psalm dependencies are missing; run composer install on the host first.' >&2
  exit 1
fi

docker build --tag "$image" "$repo_root/docker/php82-psalm"
docker run --rm --init \
  --user "$(id -u):$(id -g)" \
  --mount "type=bind,source=$repo_root,target=/work" \
  --workdir /work \
  "$image" php vendor/bin/psalm --threads=1 --no-cache
