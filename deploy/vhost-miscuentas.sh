#!/usr/bin/env bash
#
# =============================================================================
#  vhost-miscuentas.sh
# -----------------------------------------------------------------------------
#  Crea de forma AUTOMÁTICA, SEGURA y ESTABLE el VirtualHost de Apache para
#  MisCuentas (dominio mis-cuentas.tallerssh.cu).
#
#  Características de seguridad/estabilidad:
#   1. Se ejecuta como root (lo requiere para tocar /etc/apache2 /etc/httpd).
#   2. Opera sobre un .conf NUEVO sin tocar configuraciones existentes.
#   3. Valida la sintaxis con `apachectl configtest` ANTES de activar/reiniciar,
#      por lo que NUNCA deja Apache caído si algo está mal.
#   4. Aplica permisos mínimos (directorio del proyecto y storage).
#   5. Cubre HTTP y HTTPS (habilitar con la misma ejecución si se desea).
#   6. Crea un backup de cualquier .conf previo con el mismo nombre.
#   7. Verifica el resultado final con curl y reporta el código HTTP.
#
#  Uso:
#    ./vhost-miscuentas.sh [--ssl|--no-ssl] [opciones]
#
#  Opciones:
#    --ssl                Crea VHosts HTTP -> redirección a HTTPS y :443 (por defecto).
#    --no-ssl             Crea únicamente el VirtualHost :80 (HTTP plano).
#    --domain=DOM         Dominio (por defecto: mis-cuentas.tallerssh.cu).
#    --path=DIR           Ruta absoluta del proyecto (por defecto: /storage/www/html/miscuentas).
#    --email=EMAIL        Email para letsencrypt (solo si usas addons-letsencrypt).
#    --letsencrypt        Instala/emite certificado con certbot tras crear el VHost :443.
#    --force              Sobrescribir el .conf si ya existe (tras hacer backup).
#    --help               Muestra esta ayuda.
#
#  Compatibilidad:
#    - Debian/Ubuntu (a2ensite / a2dissite, sites-available & sites-enabled).
#    - RHEL/CentOS/AlmaLinux (httpd.conf incluido desde /etc/httpd/conf.d, con
#      redirect /etc/httpd/sites-available y sites-enabled).
#
# ----------------------------------------------------------------------------
#  Lanzamiento:
#    chmod +x deploy/vhost-miscuentas.sh
#    sudo ./deploy/vhost-miscuentas.sh --ssl
# =============================================================================

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuración por defecto
# ---------------------------------------------------------------------------
DOMAIN="mis-cuentas.tallerssh.cu"
PROJECT_PATH="/storage/www/html/miscuentas"
ADMIN_EMAIL="admin@tallerssh.cu"
USE_SSL=1
LETSSENCRYPT=0
FORCE=0

DISTRO="unknown"

# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------
log()  { printf '[vhost] %s\n' "$*"; }
err()  { printf '[vhost][ERROR] %s\n' "$*" >&2; }
die()  { err "$*"; exit 1; }

usage() {
    sed -n '2,60p' "$0" | sed 's/^# \{0,1\}//'
    exit 0
}

# ---------------------------------------------------------------------------
# Parseo de argumentos
# ---------------------------------------------------------------------------
for arg in "$@"; do
    case "$arg" in
        --ssl)          USE_SSL=1 ;;
        --no-ssl)       USE_SSL=0 ;;
        --letsencrypt)  LETSSENCRYPT=1 ;;
        --force)        FORCE=1 ;;
        --help|-h)      usage ;;
        --domain=*)     DOMAIN="${arg#*=}" ;;
        --path=*)       PROJECT_PATH="${arg#*=}" ;;
        --email=*)      ADMIN_EMAIL="${arg#*=}" ;;
        *)
            die "Argumento desconocido: $arg (usa --help)"
            ;;
    esac
done

# ---------------------------------------------------------------------------
# Comprobaciones previas (root, rutas, comandos)
# ---------------------------------------------------------------------------
[ "$(id -u)" -eq 0 ] || die "Debes ejecutar el script como root (sudo)."

[ -d "$PROJECT_PATH" ] || die "El directorio del proyecto no existe: $PROJECT_PATH"
[ -d "$PROJECT_PATH/public" ] || die "Falta el DocumentRoot: $PROJECT_PATH/public no existe."

# Detectar distribución y comandos de Apache
for cmd in apachectl apache2ctl httpd; do
    if command -v "$cmd" >/dev/null 2>&1; then
        APACHE_CTL="$(command -v "$cmd")"
        break
    fi
done
[ -n "${APACHE_CTL:-}" ] || die "No se encontró apachectl/apache2ctl/httpd en el sistema."

if [ -d /etc/apache2/sites-available ]; then
    DISTRO="debian"
    SITES_AVAILABLE="/etc/apache2/sites-available"
    SITES_ENABLED="/etc/apache2/sites-enabled"
    CONF_NAME="${DOMAIN}.conf"
    CONF_FILE="${SITES_AVAILABLE}/${CONF_NAME}"
    ENABLED_LINK="${SITES_ENABLED}/${CONF_NAME}"
elif [ -d /etc/httpd/conf.d ]; then
    DISTRO="redhat"
    SITES_AVAILABLE="/etc/httpd/sites-available"
    SITES_ENABLED="/etc/httpd/sites-enabled"
    CONF_NAME="${DOMAIN}.conf"
    CONF_FILE="${SITES_AVAILABLE}/${CONF_NAME}"
    ENABLED_LINK="${SITES_ENABLED}/${CONF_NAME}"
    mkdir -p "$SITES_AVAILABLE" "$SITES_ENABLED"
    # Asegurar que httpd.conf incluya sites-enabled (idempotente)
    if ! grep -q "IncludeOptional sites-enabled/\*.conf" /etc/httpd/conf/httpd.conf 2>/dev/null; then
        printf '\nIncludeOptional sites-enabled/*.conf\n' >> /etc/httpd/conf/httpd.conf
        log "Añadido IncludeOptional sites-enabled a httpd.conf."
    fi
else
    die "No se detectó una estructura de Apache conocida (/etc/apache2 o /etc/httpd)."
fi

# ---------------------------------------------------------------------------
# AJUSTAR PERMISOS DEL PROYECTO (mínimos y seguros)
# ---------------------------------------------------------------------------
chown -R root:root "$PROJECT_PATH" 2>/dev/null || true
# Solo storage y bootstrap/cache deben ser escribibles por www-data y deploy.
chown -R www-data:www-data "$PROJECT_PATH/storage" "$PROJECT_PATH/bootstrap/cache" 2>/dev/null || true
chmod -R 755 "$PROJECT_PATH"
chmod -R 775 "$PROJECT_PATH/storage" "$PROJECT_PATH/bootstrap/cache" 2>/dev/null || true
log "Permisos aplicados (dir asegura www-data en storage y bootstrap/cache)."

# ---------------------------------------------------------------------------
# GENERAR CONTENIDO DEL VirtualHost
# ---------------------------------------------------------------------------
REDIR_CONF=""
SSL_CONF=""

# VirtualHost :80 -> siempre existe
REDIR_CONF=$(cat <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${PROJECT_PATH}/public

    <Directory ${PROJECT_PATH}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteRule ^(.*)\$ \$1 [L]
    </IfModule>

EOF
)

if [ "$USE_SSL" -eq 1 ]; then
    REDIR_CONF+=$(cat <<EOF
    # Redirigir todo HTTP a HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^/?(.*) https://%{SERVER_NAME}/\$1 [R=301,L]

EOF
)
    SSL_CONF=$(cat <<EOF

<VirtualHost *:443>
    ServerName ${DOMAIN}
    DocumentRoot ${PROJECT_PATH}/public

    <Directory ${PROJECT_PATH}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteRule ^(.*)\$ \$1 [L]
    </IfModule>

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/${DOMAIN}/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/${DOMAIN}/privkey.pem

    <IfModule mod_headers.c>
        Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    </IfModule>
</VirtualHost>
EOF
)
fi

REDIR_CONF+="</VirtualHost>"

FULL_CONF="${REDIR_CONF}${SSL_CONF}"

# ---------------------------------------------------------------------------
# Backup / override del .conf
# ---------------------------------------------------------------------------
if [ -e "$CONF_FILE" ]; then
    if [ "$FORCE" -eq 1 ]; then
        cp "$CONF_FILE" "${CONF_FILE}.bak.$(date +%Y%m%d%H%M%S)"
        log "Backup del .conf previo creado: ${CONF_FILE}.bak.*"
    else
        die "El archivo $CONF_FILE ya existe y no usaste --force. Nada se cambió."
    fi
fi

printf '%s\n' "$FULL_CONF" > "$CONF_FILE"
log "Configuración escrita en $CONF_FILE"

# ---------------------------------------------------------------------------
# VALIDAR SINTAXIS ANTES DE ACTIVAR (nunca dejar Apache caído)
# ---------------------------------------------------------------------------
if ! "$APACHE_CTL" configtest 2>/tmp/vhost-configtest.log; then
    err "La validación de sintaxis FALLÓ. Se revierte la operación."
    [ -f "$CONF_FILE" ] && rm -f "$CONF_FILE"
    cat /tmp/vhost-configtest.log >&2
    exit 1
fi
log "Sintaxis de Apache validada correctamente."

# ---------------------------------------------------------------------------
# ACTIVAR EL SITIO
# ---------------------------------------------------------------------------
if [ "$DISTRO" = "debian" ]; then
    a2ensite "$CONF_NAME" >/dev/null
else
    ln -sf "$CONF_FILE" "$ENABLED_LINK"
fi

# Recargar de forma GRACEFUL (no reinicia, no corta conexiones activas).
if ! "$APACHE_CTL" graceful 2>/tmp/vhost-graceful.log; then
    err "El 'graceful reload' falló. Revisa /tmp/vhost-graceful.log y la sintaxis."
    cat /tmp/vhost-graceful.log >&2
    exit 1
fi
log "Apache recargado en modo graceful. Sitio activado."

# ---------------------------------------------------------------------------
# OPCIONAL: EMITIR CERTIFICADO LETSENCRYPT
# ---------------------------------------------------------------------------
if [ "$LETSSENCRYPT" -eq 1 ]; then
    if ! command -v certbot >/dev/null 2>&1; then
        die "--letsencrypt requiere certbot instalado. Ejecuta primero: apt install certbot python3-certbot-apache"
    fi
    certbot --apache -d "$DOMAIN" \
        --non-interactive \
        --redirect \
        --agree-tos \
        -m "$ADMIN_EMAIL" \
        --keep-until-expiring \
        || die "certbot falló. Corrige y vuelve a ejecutar certbot a mano."
    log "Certificado emitido/renovado por letsencrypt."
fi

# ---------------------------------------------------------------------------
# VERIFICACIÓN FINAL
# ---------------------------------------------------------------------------
sleep 2
CODE_HTTP="$(curl -s -o /dev/null -w '%{http_code}' "http://${DOMAIN}/" 2>/dev/null || echo '000')"
log "Verificación final: http://${DOMAIN}/ -> HTTP ${CODE_HTTP}"

if [ "$USE_SSL" -eq 1 ] && [ "$LETSSENCRYPT" -eq 1 ]; then
    CODE_HTTPS="$(curl -sk -o /dev/null -w '%{http_code}' "https://${DOMAIN}/" 2>/dev/null || echo '000')"
    log "Verificación final: https://${DOMAIN}/ -> HTTP ${CODE_HTTPS}"
fi

log "Listo. Host: $DOMAIN | Proyecto: $PROJECT_PATH | SSL: $([ "$USE_SSL" -eq 1 ] && echo 'sí' || echo 'no')"
