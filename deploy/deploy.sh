#!/usr/bin/env bash
# deploy.sh — First-time and update deployment for POO_CloudObjStrg
# Run as root or with sudo on the Ubuntu server.
#
# Usage:
#   First deployment : sudo bash deploy/deploy.sh install
#   Update (git pull): sudo bash deploy/deploy.sh update
#
set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
APP_DIR="/var/www/pdfmanager"
REPO_URL="https://github.com/daviddevgt/poo_cloudobjstrg.git"   # change if needed
PHP_VERSION="8.3"         # installed PHP version (8.1, 8.2, 8.3)
DB_NAME="pdf_store"
WEB_USER="www-data"

# ── Helpers ───────────────────────────────────────────────────────────────────
info()  { echo -e "\033[0;32m[INFO]\033[0m  $*"; }
warn()  { echo -e "\033[0;33m[WARN]\033[0m  $*"; }
error() { echo -e "\033[0;31m[ERROR]\033[0m $*" >&2; exit 1; }

# ── install ───────────────────────────────────────────────────────────────────
install() {
    info "=== POO_CloudObjStrg — First-time install ==="

    # 1. System packages
    info "Installing packages..."
    apt-get update -q
    apt-get install -y -q \
        nginx \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-fileinfo \
        mysql-server \
        git \
        composer \
        unzip

    # 2. Clone repo
    if [ -d "$APP_DIR" ]; then
        warn "$APP_DIR already exists — skipping clone. Run 'update' instead."
    else
        info "Cloning repository..."
        git clone "$REPO_URL" "$APP_DIR"
    fi

    # 3. Composer — production deps only
    info "Installing PHP dependencies (no-dev)..."
    composer install --no-dev --optimize-autoloader --working-dir="$APP_DIR" -q

    # 4. .env
    if [ ! -f "$APP_DIR/.env" ]; then
        cp "$APP_DIR/.env.example" "$APP_DIR/.env"
        warn ".env created from example. EDIT IT NOW before proceeding:"
        warn "  nano $APP_DIR/.env"
        warn "Then re-run:  sudo bash deploy/deploy.sh post-install"
        exit 0
    fi

    post_install
}

# ── post-install (runs after .env is configured) ──────────────────────────────
post_install() {
    info "=== Post-install setup ==="

    # 5. Database
    info "Creating database '$DB_NAME'..."
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    info "Running migrations..."
    php "$APP_DIR/migrations/migrate_data.php"

    # 6. Permissions
    info "Setting file permissions..."
    chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR"
    chmod 755 "$APP_DIR/uploads"
    chmod 640 "$APP_DIR/.env"
    chown root:"$WEB_USER" "$APP_DIR/.env"

    # 7. Nginx config
    info "Installing Nginx configuration..."
    cp "$APP_DIR/deploy/nginx.conf" /etc/nginx/sites-available/pdfmanager
    ln -sf /etc/nginx/sites-available/pdfmanager /etc/nginx/sites-enabled/pdfmanager
    rm -f /etc/nginx/sites-enabled/default
    nginx -t && systemctl reload nginx

    # 8. PHP-FPM
    info "Enabling OPcache for PHP-FPM..."
    cat > /etc/php/${PHP_VERSION}/fpm/conf.d/99-pdfmanager.ini <<EOF
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
upload_max_filesize=10M
post_max_size=11M
EOF
    systemctl restart php${PHP_VERSION}-fpm

    # 9. Systemd timer for AutoDelete
    info "Installing AutoDelete systemd timer..."
    cp "$APP_DIR/deploy/autodelete.service" /etc/systemd/system/pdfmanager-autodelete.service
    cp "$APP_DIR/deploy/autodelete.timer"   /etc/systemd/system/pdfmanager-autodelete.timer
    # Inject real app path
    sed -i "s|/var/www/pdfmanager|${APP_DIR}|g" /etc/systemd/system/pdfmanager-autodelete.service
    systemctl daemon-reload
    systemctl enable --now pdfmanager-autodelete.timer

    info ""
    info "=== Install complete ==="
    info "Edit Nginx config to set your domain: /etc/nginx/sites-available/pdfmanager"
    info "Then reload Nginx: systemctl reload nginx"
    info ""
    info "For HTTPS (optional, requires a domain):"
    info "  apt install certbot python3-certbot-nginx"
    info "  certbot --nginx -d your-domain.com"
}

# ── update ────────────────────────────────────────────────────────────────────
update() {
    info "=== POO_CloudObjStrg — Updating ==="

    [ -d "$APP_DIR" ] || error "$APP_DIR not found. Run install first."

    info "Pulling latest code..."
    git -C "$APP_DIR" pull --ff-only

    info "Updating dependencies..."
    composer install --no-dev --optimize-autoloader --working-dir="$APP_DIR" -q

    info "Running migrations..."
    php "$APP_DIR/migrations/migrate_data.php"

    info "Fixing permissions..."
    chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR"
    chmod 755 "$APP_DIR/uploads"

    info "Reloading services..."
    systemctl reload php${PHP_VERSION}-fpm
    systemctl reload nginx

    info "=== Update complete ==="
}

# ── entrypoint ────────────────────────────────────────────────────────────────
case "${1:-}" in
    install)      install ;;
    post-install) post_install ;;
    update)       update ;;
    *)
        echo "Usage: sudo bash deploy/deploy.sh {install|post-install|update}"
        exit 1
        ;;
esac
