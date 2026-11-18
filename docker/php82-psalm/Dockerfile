FROM debian:bookworm-slim

# Use Debian's prebuilt PHP 8.2 extensions instead of compiling the PHP source.
# This avoids DOM/XMLReader build-order and header problems in the CLI image.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        php8.2-cli php8.2-mbstring php8.2-xml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /work
