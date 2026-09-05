#!/usr/bin/env sh
# Builds the project's production Docker image and asserts that every PHP extension this
# project depends on is actually loaded in it. See:
# - .specs/001-postgresql-compatibility/spec.md (acceptance criteria 1, 6, 8)
# - .specs/001-postgresql-compatibility/research.md (T001 spike findings)
set -eu

IMAGE_TAG="link-shortener-php-ext-check:latest"
REQUIRED_EXTENSIONS="pdo_pgsql pgsql pcov"

docker build -t "$IMAGE_TAG" .

missing=""
for ext in $REQUIRED_EXTENSIONS; do
    if ! docker run --rm "$IMAGE_TAG" php -m | grep -iq "^${ext}$"; then
        missing="$missing $ext"
    fi
done

if [ -n "$missing" ]; then
    echo "Missing PHP extensions in built image:$missing" >&2
    exit 1
fi

echo "All required PHP extensions present:$REQUIRED_EXTENSIONS"
