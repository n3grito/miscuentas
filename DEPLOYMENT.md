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
# Alternativa: todo en un solo comando (sin --force, no existe en Laravel 11)
# php artisan optimize
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
php artisan config:cache
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
php artisan config:cache
```

### Diagnóstico automático
Ejecuta el comando incluido que revisa clave, entorno, conexión, collation y migraciones:
```bash
php artisan deploy:diagnose
```

### 1. "No application encryption key has been specified"
Falta APP_KEY o es inválida en `.env`. Genera una SIEMPRE con CUIDADO (si ya hay
datos cifrados no debes cambiarla):
```bash
php artisan key:generate
php artisan config:clear
```
> En un servidor hay que mantener la MISMA APP_KEY entre despliegues. Si cambia, los
> datos cifrados (sesiones, contraseñas, tokens) dejan de descifrarse.

### 2. "The --force option does not exist"
En Laravel 11, `config:cache` y `optimize` ya NO aceptan `--force`. Usa:
```bash
php artisan config:cache     # sin --force
php artisan optimize         # sin --force
```
`--force` solo aplica a `migrate`, `db:seed` y `schedule:run`.

### 3. "Unknown collation: 'utf8mb4_0900_ai_ci'" (SQLSTATE 1273)
Servidor MariaDB o MySQL < 8.0.16 que no conoce esa collation. Verifica con:
```bash
mysql -u root -p -e "SHOW VARIABLES LIKE 'version';"
mysql -u root -p -e "SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci';"
```
Si no aparece la collation, corrige en `.env` (la app usa por defecto utf8mb4_unicode_ci):
```bash
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
php artisan config:clear
```
Si la base YA se creó con la collation equivocada, convierte las tablas:
```bash
mysql -u root -p miscuentas
ALTER DATABASE miscuentas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SELECT CONCAT('ALTER TABLE `', TABLE_NAME, '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
FROM information_schema.TABLES WHERE TABLE_SCHEMA='miscuentas';
```

### 4. "Unknown database 'miscuentas'" (SQLSTATE 1049)
La base no existe. Créala ANTES de migrar:
```bash
mysql -u root -p
CREATE DATABASE miscuentas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'miscuentas_user'@'127.0.0.1' IDENTIFIED BY 'CAMBIAME_por_una_fuerte';
GRANT ALL PRIVILEGES ON miscuentas.* TO 'miscuentas_user'@'127.0.0.1';
FLUSH PRIVILEGES;
php artisan migrate --force
```

### 5. "'accounts.code' isn't in GROUP BY" (SQLSTATE 1055)
MySQL/MariaDB en modo estricto (`ONLY_FULL_GROUP_BY`). La query del Balance de
Comprobación agrupaba por `accounts.id` pero seleccionaba `accounts.*`. Ya se
corrigió para agrupar por las columnas explícitas. Solo asegúrate de desplegar la
última versión (`git pull` + recargar) — no se requiere tocar el servidor.

### 6. "Duplicate entry '1-1'" (SQLSTATE 1062, inventario)
El servicio de inventario ya usa `lockInventory` con creación segura y re-intento
(movimientos concurrentes). El error lo emitió una versión ANTIGUA del código; con la
versión actual el escenario concurrente se resuelve solo. Si ocurriera igual, revisar
que en el servidor esté desplegada la última versión:
```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
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