<p align="center"><a href="https://miscuentas.tallerssh.cu" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/TU_USUARIO/miscuentas/actions"><img src="https://github.com/TU_USUARIO/miscuentas/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## MisCuentas

Sistema integral de gestión de inventario, punto de venta (POS) y contabilidad para negocios en Cuba.

### Características Principales

- **Gestión de Inventario**: Productos con SKU automático, stock por almacén, movimientos, alertas
- **Punto de Venta (POS)**: Interfaz táctil, cálculo de cambio, alta rápida de clientes
- **Compras y Ventas**: Registro completo con entrada/salida automática de inventario
- **Facturación**: Emisión, impresión y cancelación de facturas
- **Contabilidad**: Cuentas, asientos de partida doble, asientos automáticos
- **Reportes**: Valoración de inventario, movimientos, resumen operaciones
- **Multi-usuario**: Roles y permisos granulares por módulo
- **Auditoría**: Registro completo de actividad del sistema

### Tecnologías

- **Backend**: Laravel 11 + PHP 8.2+
- **Panel Admin**: Filament v3
- **Base de Datos**: MySQL 8.0+
- **Autenticación**: Spatie Permission
- **Tests**: 91 tests / 303 assertions

### Instalación Rápida

```bash
# Clonar repositorio
git clone https://github.com/TU_USUARIO/miscuentas.git
cd miscuentas

# Instalar dependencias
composer install
npm install && npm run build

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Configurar base de datos en .env
# DB_DATABASE=miscuentas
# DB_USERNAME=root
# DB_PASSWORD=

# Ejecutar migraciones y seeders
php artisan migrate
php artisan db:seed --class=SuperAdminSeeder

# Iniciar servidor
php artisan serve
```

### Credenciales por Defecto

- **Email**: admin@miscuentas.test
- **Contraseña**: password

### Estructura del Proyecto

```
miscuentas/
├── app/
│   ├── Filament/          # Recursos, páginas, widgets
│   ├── Models/            # 26 modelos Eloquent
│   ├── Policies/          # 21 políticas de autorización
│   ├── Services/          # 6 servicios de negocio
│   └── Support/           # Clases de soporte
├── config/                # Configuración
├── database/              # Migraciones y seeders
├── public/                # Assets públicos
├── resources/             # Vistas Blade
├── routes/                # Rutas
├── storage/               # Logs, caché, backups
└── tests/                 # 91 tests feature
```

### Módulos

| Módulo | Descripción |
|--------|-------------|
| **Productos** | Catálogo con SKU automático, categorías, unidades |
| **Inventario** | Stock por almacén, movimientos, alertas, valoración |
| **Compras** | Recepción con entrada automática de inventario |
| **Ventas** | Despacho con salida FIFO y manejo de descuentos |
| **POS** | Terminal punto de venta con cálculo de cambio |
| **Facturación** | Emisión, impresión, cancelación desde venta |
| **Contabilidad** | Cuentas, asientos de partida doble automáticos |
| **Reportes** | Valoración, movimientos, resumen, balance |
| **Admin** | Usuarios, roles, permisos, auditoría, ajustes |

### Documentación

- [Guía de Despliegue](DEPLOYMENT.md)
- [Guía de Git](GIT_GUIDE.md)

### Contribuir

Las contribuciones son bienvenidas. Por favor, abra un issue para discutir cambios significativos.

### Licencia

MIT License - Ver [LICENSE](LICENSE) para más detalles.

### Autor

**[Lic. Osniel Galá]**
- Email: [osnigc@tallerssh.cu]
- GitHub: [@n3grito](https://github.com/n3grito)

### Soporte

Si tienes problemas o preguntas, abre un issue en el repositorio.
