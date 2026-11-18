FROM php:8.2-cli-bookworm

# Psalm 5.x requires extensions not included in the minimal CLI image.
# Keep the image isolated from the host PHP installation.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libxml2-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring dom simplexml xml xmlreader xmlwriter \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /work
