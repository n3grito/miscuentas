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
git clone <repo-url> /storage/www/html/miscuentas
cd /storage/www/html/miscuentas
```

### 2. Crear la base de datos en MySQL ANTES de correr cualquier `php artisan`
> IMPORTANTE: Este paso es obligatorio y debe ir **antes** de `key:generate` o de
> `composer install`. El panel Filament consulta la tabla `settings` al arrancar
> CUALQUIER comando `php artisan`. Si la base de datos no existe, fallará con
> `Unknown database 'miscuentas'`. Crea primero la BD y el usuario.

```bash
mysql -u root -p
```

```sql
CREATE DATABASE miscuentas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'miscuentas_user'@'127.0.0.1' IDENTIFIED BY 'CAMBIAME_por_una_fuerte';
GRANT ALL PRIVILEGES ON miscuentas.* TO 'miscuentas_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

> **Collation:** si tu servidor es **MariaDB** (o MySQL < 8), `utf8mb4_0900_ai_ci` no es
> soportado y Laravel fallará con `Unknown collation: 'utf8mb4_0900_ai_ci'`. La app usa
> `utf8mb4_unicode_ci` por defecto (compatible con ambos), definido en `config/database.php`
> y exportado en `.env` como `DB_COLLATION=utf8mb4_unicode_ci`. Solo si tu servidor es
> **MySQL 8+** puedes usar `utf8mb4_0900_ai_ci`. Aplica el que corresponda tanto a la
> `CREATE DATABASE` como a `DB_COLLATION` en `.env`.

### 3. Configurar entorno ANTES de instalar dependencias
> IMPORTANTE: Crear `.env` **antes** de `composer install`. El hook `post-autoload-dump`
> ejecuta `package:discover`, que arranca Laravel y conecta a la base de datos. Si aún no
> existe `.env`, o si apunta a SQLite sin el archivo, fallará con
> "Database file at path [.../database.sqlite] does not exist".

```bash
cp .env.production.example .env
php artisan key:generate

# Editar .env con valores reales:
#   APP_ENV=production
#   APP_DEBUG=false
#   DB_DATABASE=miscuentas
#   DB_USERNAME=miscuentas_user
#   DB_PASSWORD=<tu contraseña>
#   DB_CHARSET=utf8mb4
#   DB_COLLATION=utf8mb4_unicode_ci   # compatible MariaDB/MySQL (ver Paso 2)
```

### 4. Instalar dependencias
```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build  # solo si compilas assets
```

### 5. Migrar y sembrar la base de datos
```bash
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
```

### 6. Permisos (Linux/Mac)
```bash
chown -R www-data:www-data /storage/www/html/miscuentas
chmod -R 755 /storage/www/html/miscuentas
chmod -R 775 /storage/www/html/miscuentas/storage
chmod -R 775 /storage/www/html/miscuentas/bootstrap/cache
```

### 7. Optimizar para producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 8. Configurar Scheduler (cron)
Agregar al crontab del servidor:
```bash
* * * * * cd /storage/www/html/miscuentas && php artisan schedule:run >> /dev/null 2>&1
```

### 9. Configurar Queue Worker (si usas jobs)
```bash
# Como servicio systemd o supervisord
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### 10. Configurar Backup
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