# Guía de Despliegue - MisCuentas

## Requisitos del Servidor

### PHP 8.2+ con extensiones:
- curl, openssl, mbstring, xml, zip, pdo_mysql, bcmath, json, fileinfo, tokenizer, ctype

### Servidor Web:
- Apache con mod_rewrite (recomendado) o Nginx
- DocumentRoot apuntando a `/public`

### Base de Datos:
- MySQL 8.0+ o MariaDB 10.6+

### Otros:
- Node.js 18+ (para compilar assets, solo en build)
- Redis (opcional, para caché y sesiones en alto tráfico)

---

## Pasos de Despliegue

### 1. Copiar archivos al servidor
```bash
# Desde tu máquina local
git clone <repo-url> /var/www/miscuentas
cd /var/www/miscuentas
```

### 2. Instalar dependencias
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build  # solo si compilas assets
```

### 3. Configurar entorno
```bash
cp .env.production.example .env
php artisan key:generate
```

Editar `.env` con valores reales:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mis-cuentas.tallerssh.cu

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=miscuentas
DB_USERNAME=miscuentas_user
DB_PASSWORD=<contraseña_segura>

MAIL_MAILER=smtp
MAIL_HOST=mail.tallerssh.cu
MAIL_PORT=587
MAIL_USERNAME=noreply@tallerssh.cu
MAIL_PASSWORD=<contraseña>
MAIL_ENCRYPTION=tls
```

### 4. Base de datos
```bash
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
```

### 5. Permisos (Linux/Mac)
```bash
chown -R www-data:www-data /var/www/miscuentas
chmod -R 755 /var/www/miscuentas
chmod -R 775 /var/www/miscuentas/storage
chmod -R 775 /var/www/miscuentas/bootstrap/cache
```

### 6. Optimizar para producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 7. Configurar Scheduler (cron)
Agregar al crontab del servidor:
```bash
* * * * * cd /var/www/miscuentas && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Configurar Queue Worker (si usas jobs)
```bash
# Como servicio systemd o supervisord
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### 9. Configurar Backup
```bash
# Crear directorio de backups
mkdir -p storage/app/backups
chmod 775 storage/app/backups

# Probar backup manual
php artisan backup:run --only-db
```

---

## Verificación Post-Despliegue

```bash
# Verificar configuración
php artisan about
php artisan config:cache --force
php artisan route:list

# Verificar permisos
ls -la storage/
ls -la bootstrap/cache/

# Verificar scheduler
php artisan schedule:list

# Verificar backup
php artisan backup:run --only-db
ls -la storage/app/backups/
```

---

## Solución de Problemas

### Error 500 en producción
```bash
# Verificar logs
tail -f storage/logs/laravel.log
cat .env  # verificar APP_DEBUG=false
php artisan config:cache --force
```

### Permisos denegados
```bash
chown -R www-data:www-data storage/ bootstrap/cache/
chmod -R 775 storage/ bootstrap/cache/
```

### Backup falla
```bash
# Verificar espacio en disco
df -h

# Verificar permisos del directorio
ls -la storage/app/backups/

# Ejecutar manual para ver errores
php artisan backup:run -vvv
```

---

## Comandos Útiles

```bash
# Backup manual
php artisan backup:run --only-db

# Limpiar backups antiguos
php artisan backup:clean

# Verificar estado del sistema
php artisan about

# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```