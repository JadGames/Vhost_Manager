#!/bin/bash
set -e

STORAGE_DIR="/opt/aphost/storage"

# Ensure storage directories exist and are owned by www-data before startup
mkdir -p "$STORAGE_DIR/data" "$STORAGE_DIR/logs"
chown -R www-data:www-data "$STORAGE_DIR"

exec apache2-foreground
