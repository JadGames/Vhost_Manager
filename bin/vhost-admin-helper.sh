#!/usr/bin/env bash
set -euo pipefail

ACTION="${1:-}"
DOMAIN="${2:-}"
ARG3="${3:-}"
ARG4="${4:-}"
ARG5="${5:-}"

APACHE_DIR="/etc/apache2/sites-available"
SITE_USER="www-data"
DEFAULT_BASE="/var/www"
TEMPLATE_FILE="/etc/vhost-manager/vhost.conf.tpl"
MODULES_ALLOWLIST=(rewrite headers ssl proxy proxy_http http2 expires deflate remoteip setenvif dir alias mime)
REQUIRED_MODULES=(rewrite)

fail() {
  echo "ERROR: $1" >&2
  exit 1
}

is_allowed_module() {
  local module="$1"
  local allowed

  for allowed in "${MODULES_ALLOWLIST[@]}"; do
    if [[ "$allowed" == "$module" ]]; then
      return 0
    fi
  done

  return 1
}

is_required_module() {
  local module="$1"
  local required

  for required in "${REQUIRED_MODULES[@]}"; do
    if [[ "$required" == "$module" ]]; then
      return 0
    fi
  done

  return 1
}

validate_module() {
  local module="$1"
  [[ "$module" =~ ^[a-z0-9_]+$ ]] || fail "invalid module"
  is_allowed_module "$module" || fail "unsupported module"
}

module_state() {
  local module="$1"

  if a2query -m "$module" >/dev/null 2>&1; then
    echo "enabled"
    return 0
  fi

  echo "disabled"
}

list_modules() {
  local module
  local state

  for module in "${MODULES_ALLOWLIST[@]}"; do
    state="$(module_state "$module")"
    printf '%s|%s\n' "$module" "$state"
  done
}

enable_module() {
  local module="$1"

  validate_module "$module"

  if [[ "$(module_state "$module")" == "enabled" ]]; then
    echo "ok"
    return 0
  fi

  a2enmod "$module"
  apache2ctl configtest
  apache2ctl graceful
  echo "ok"
}

disable_module() {
  local module="$1"

  validate_module "$module"
  is_required_module "$module" && fail "module is required"

  if [[ "$(module_state "$module")" == "disabled" ]]; then
    echo "ok"
    return 0
  fi

  a2dismod "$module"
  apache2ctl configtest
  apache2ctl graceful
  echo "ok"
}

validate_domain() {
  local domain="$1"
  if [[ ! "$domain" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]]; then
    fail "invalid domain"
  fi
}

normalize_path() {
  local raw="$1"
  readlink -m -- "$raw"
}

ensure_apache_traverse() {
  local path="$1"
  local current

  current="$(normalize_path "$path")"
  while [[ "$current" != "/" ]]; do
    if [[ -d "$current" ]]; then
      chmod o+x -- "$current"
    fi
    current="$(dirname -- "$current")"
  done
}

ensure_allowed_docroot() {
  local path="$1"
  local allowed_bases="$2"
  local normalized
  normalized="$(normalize_path "$path")"
  
  # Split comma-separated bases and check if path is within any allowed base
  IFS=',' read -ra bases <<< "$allowed_bases"
  for base in "${bases[@]}"; do
    base="$(echo "$base" | xargs)"  # Trim whitespace
    [[ -z "$base" ]] && continue
    local normalized_base
    normalized_base="$(normalize_path "$base")"
    if [[ "$normalized" == "$normalized_base" || "$normalized" == "$normalized_base"/* ]]; then
      echo "$normalized"
      return 0
    fi
  done
  
  fail "docroot must be within allowed directories: $allowed_bases"
}

render_template() {
  local domain="$1"
  local docroot="$2"
  local output_file="$3"

  [[ -f "$TEMPLATE_FILE" ]] || fail "template file missing: $TEMPLATE_FILE"

  sed \
    -e "s|{{DOMAIN}}|$domain|g" \
    -e "s|{{DOCROOT}}|$docroot|g" \
    -e "s|{{ERROR_LOG}}|/var/log/apache2/${domain}_error.log|g" \
    -e "s|{{ACCESS_LOG}}|/var/log/apache2/${domain}_access.log|g" \
    "$TEMPLATE_FILE" > "$output_file"
}

create_site() {
  local domain="$1"
  local docroot_raw="$2"
  local allowed_bases="$3"
  local docroot
  local conf_file
  local matched_base=""

  docroot="$(ensure_allowed_docroot "$docroot_raw" "$allowed_bases")"
  IFS=',' read -ra bases <<< "$allowed_bases"
  for base in "${bases[@]}"; do
    base="$(echo "$base" | xargs)"
    [[ -z "$base" ]] && continue
    local normalized_base
    normalized_base="$(normalize_path "$base")"
    if [[ "$docroot" == "$normalized_base" || "$docroot" == "$normalized_base"/* ]]; then
      matched_base="$normalized_base"
      break
    fi
  done

  if [[ -n "$matched_base" && "$matched_base" != "$DEFAULT_BASE" && "$matched_base" != "$DEFAULT_BASE"/* ]]; then
    ensure_apache_traverse "$matched_base"
  fi

  conf_file="$APACHE_DIR/${domain}.conf"

  mkdir -p -- "$docroot"
  chown "$SITE_USER":"$SITE_USER" "$docroot"
  chmod 0755 "$docroot"

  if [[ ! -f "$docroot/index.html" ]]; then
    cat > "$docroot/index.html" <<HTML
<!doctype html>
<html>
  <head><meta charset="utf-8"><title>${domain}</title></head>
  <body><h1>${domain} is configured</h1></body>
</html>
HTML
    chown "$SITE_USER":"$SITE_USER" "$docroot/index.html"
    chmod 0644 "$docroot/index.html"
  fi

  render_template "$domain" "$docroot" "$conf_file"

  apache2ctl configtest
  a2ensite "${domain}.conf"
  apache2ctl configtest
  apache2ctl graceful

  echo "ok"
}

delete_site() {
  local domain="$1"
  local delete_root="$2"
  local docroot_raw="$3"
  local allowed_bases="$4"
  local conf_file="$APACHE_DIR/${domain}.conf"
  local docroot=""

  if [[ -f "$conf_file" ]]; then
    a2dissite "${domain}.conf" || true
    rm -f -- "$conf_file"
  fi

  if [[ "$delete_root" == "1" ]]; then
    [[ -n "$docroot_raw" ]] || fail "missing docroot"
    docroot="$(ensure_allowed_docroot "$docroot_raw" "$allowed_bases")"
    if [[ -d "$docroot" ]]; then
      rm -rf --one-file-system -- "$docroot"
    fi
  fi

  apache2ctl configtest
  apache2ctl graceful

  echo "ok"
}

case "$ACTION" in
  list-modules)
    list_modules
    ;;
  enable-module)
    [[ -n "$DOMAIN" ]] || fail "missing module"
    enable_module "$DOMAIN"
    ;;
  disable-module)
    [[ -n "$DOMAIN" ]] || fail "missing module"
    disable_module "$DOMAIN"
    ;;
  create)
    [[ -n "$DOMAIN" ]] || fail "missing domain"
    validate_domain "$DOMAIN"
    [[ -n "$ARG3" ]] || fail "missing docroot"
    create_site "$DOMAIN" "$ARG3" "${ARG4:-/var/www}"
    ;;
  delete)
    [[ -n "$DOMAIN" ]] || fail "missing domain"
    validate_domain "$DOMAIN"
    if [[ "$ARG3" != "0" && "$ARG3" != "1" ]]; then
      fail "delete flag must be 0 or 1"
    fi
    delete_site "$DOMAIN" "$ARG3" "$ARG4" "${ARG5:-/var/www}"
    ;;
  *)
    fail "unsupported action"
    ;;
esac
