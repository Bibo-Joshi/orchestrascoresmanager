#!/usr/bin/env bash
set -euo pipefail

fail() {
	printf 'OSM-Testumgebung: %s\n' "$*" >&2
	exit 1
}

[[ $# -eq 0 || ($# -eq 1 && "$1" == --check) ]] || fail "Aufruf: start.sh [--check]"

readonly data_partition=/media/kai/Daten
readonly image=/media/kai/Daten/kaibe/osm-test/osm-test.ext4
readonly mountpoint=/mnt/osm-test
readonly compose_dir=/mnt/osm-test/compose

partition_fs=$(findmnt --noheadings --mountpoint "$data_partition" --output FSTYPE) \
	|| fail "Datenpartition ist nicht unter $data_partition eingehängt."
case "$partition_fs" in
	ntfs3|ntfs) ;;
	*) fail "$data_partition ist kein NTFS-Mount ($partition_fs)." ;;
esac
[[ -f "$image" && ! -L "$image" ]] || fail "Die erwartete Image-Datei fehlt: $image"
[[ $(findmnt --noheadings --target "$image" --output TARGET) == "$data_partition" ]] \
	|| fail "Die Image-Datei liegt nicht auf dem erwarteten NTFS-Mount."

cd -- "$mountpoint" || fail "$mountpoint ist nicht erreichbar."
read -r filesystem source < <(findmnt --noheadings --mountpoint "$mountpoint" --output FSTYPE,SOURCE) \
	|| fail "$mountpoint ist kein eigener Mountpoint."
[[ "$filesystem" == ext4 && "$source" =~ ^/dev/loop[0-9]+$ ]] \
	|| fail "$mountpoint muss ein ext4-Loop-Mount sein."
backing_file=$(cat "/sys/class/block/${source##*/}/loop/backing_file") \
	|| fail "Zum Loop-Device $source konnte keine Image-Datei ermittelt werden."
[[ "$backing_file" == "$image" ]] || fail "$source verwendet ein anderes Image: $backing_file"

mount_id=$(findmnt --noheadings --mountpoint "$mountpoint" --output ID) \
	|| fail "$mountpoint ist nicht mehr eingehängt."
[[ $(findmnt --noheadings --target . --output ID) == "$mount_id" ]] \
	|| fail "$mountpoint wurde während der Prüfung gewechselt."

probe=$(mktemp -- .osm-write-check.XXXXXXXX) \
	|| fail "$mountpoint ist für dieses Konto nicht beschreibbar."
rm -- "$probe" || fail "Die Schreibprobe konnte nicht entfernt werden."
if [[ ${1:-} == --check ]]; then
	printf '%s\n' "NTFS-Partition und beschreibbares ext4-Image sind eingehängt."
	exit 0
fi

[[ -d "$compose_dir" && ! -L "$compose_dir" ]] || fail "$compose_dir fehlt oder ist ein Symlink."
[[ $(findmnt --noheadings --target "$compose_dir" --output ID) == "$mount_id" ]] \
	|| fail "$compose_dir liegt auf einem anderen Mount."
[[ -f "$compose_dir/.env" && ! -L "$compose_dir/.env" ]] || fail "Lokale .env fehlt oder ist ein Symlink."
[[ -f "$compose_dir/compose.yaml" && ! -L "$compose_dir/compose.yaml" ]] || fail "Lokale compose.yaml fehlt oder ist ein Symlink."
[[ -f "$compose_dir/nginx.conf" && ! -L "$compose_dir/nginx.conf" ]] || fail "Lokale nginx.conf fehlt oder ist ein Symlink."

for directory in nextcloud mariadb; do
	[[ ! -L "$directory" ]] || fail "$directory ist ein Symlink."
	mkdir -p -- "$directory"
	[[ $(findmnt --noheadings --target "$directory" --output ID) == "$mount_id" ]] \
		|| fail "$directory liegt auf einem anderen Mount."
done

# An interactive shell's values must not override the local test credentials.
unset OSM_TEST_DB_PASSWORD OSM_TEST_DB_ROOT_PASSWORD DOCKER_HOST DOCKER_CONTEXT
exec docker --host unix:///var/run/docker.sock compose --project-name osm-pdf-v2-notebook --project-directory "$compose_dir" \
	--env-file "$compose_dir/.env" -f "$compose_dir/compose.yaml" up -d --wait
