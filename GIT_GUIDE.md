# Guía para Subir el Proyecto a Git

## 1. Configurar tu Información de Programador

### Opción A: Solo para este proyecto (recomendado)
```bash
cd C:\laragon\www\miscuentas

git config user.name "Tu Nombre"
git config user.email "tu@email.com"
```

### Opción B: Global (para todos los proyectos)
```bash
git config --global user.name "Tu Nombre"
git config --global user.email "tu@email.com"
```

### Verificar configuración
```bash
git config user.name
git config user.email
git config --list
```

---

## 2. Preparar el Repositorio

### Verificar estado actual
```bash
git status
git log --oneline
```

### Archivos que NO deben subirse (ya están en .gitignore)
- `.env` (credenciales)
- `/vendor` (dependencias)
- `/node_modules` (dependencias npm)
- `/storage/app/backups` (respaldos)
- IDE configs (.idea, .vscode, .fleet)

---

## 3. Crear Repositorio en GitHub/GitLab

### En GitHub:
1. Ir a https://github.com
2. Click en "+" → "New repository"
3. Nombre: `miscuentas`
4. Descripción: "Sistema de inventario, POS y contabilidad"
5. **NO** marcar "Add a README file" (ya tenemos uno)
6. **NO** marcar .gitignore (ya tenemos uno)
7. Click "Create repository"

### En GitLab:
1. Ir a https://gitlab.com
2. Click "New project" → "Create blank project"
3. Project name: `miscuentas`
4. **NO** marcar "Initialize repository with a README"
5. Click "Create project"

---

## 4. Conectar Repositorio Local con Remoto

### Copiar la URL del repositorio remoto y ejecutar:

```bash
cd C:\laragon\www\miscuentas

# Para GitHub (reemplaza TU_USUARIO):
git remote add origin https://github.com/TU_USUARIO/miscuentas.git

# Para GitLab (reemplaza TU_USUARIO):
git remote add origin https://gitlab.com/TU_USUARIO/miscuentas.git
```

### Verificar conexión
```bash
git remote -v
```

---

## 5. Subir el Código

### Primera vez (push inicial)
```bash
git push -u origin master
```

### Si pide autenticación:
- **GitHub**: Usar token de acceso personal (PAT)
  1. GitHub → Settings → Developer settings → Personal access tokens
  2. Crear token con permiso `repo`
  3. Usar token como contraseña al hacer push

- **GitLab**: Usar token de acceso personal
  1. GitLab → User Settings → Access Tokens
  2. Crear token con alcance `read_repository, write_repository`
  3. Usar token como contraseña

---

## 6. Comandos Útiles

### Ver historial
```bash
git log --oneline
git log --oneline --graph
```

### Ver cambios pendientes
```bash
git status
git diff
```

### Guardar cambios
```bash
git add -A
git commit -m "mensaje descriptivo"
git push
```

### Ver ramas
```bash
git branch -a
git branch -v
```

---

## 7. Estructura del Repositorio

```
miscuentas/
├── app/                    # Código PHP (148 archivos)
│   ├── Filament/          # Recursos, páginas, widgets
│   ├── Models/            # 26 modelos
│   ├── Policies/          # 21 políticas
│   ├── Services/          # 6 servicios
│   └── Support/           # Clases de soporte
├── config/                 # Configuración Laravel
├── database/               # Migraciones, seeders
├── public/                 # Assets públicos
├── resources/              # Views, CSS, JS
├── routes/                 # Rutas
├── storage/                # Logs, caché, backups
├── tests/                  # 91 tests
├── .env.production.example # Ejemplo para producción
├── composer.json           # Dependencias PHP
├── DEPLOYMENT.md           # Guía de despliegue
└── README.md               # Documentación
```

---

## 8. Buena Práctica de Commits

### Formato recomendado
```
tipo: descripción corta

[opcional] descripción más detallada

[opcional] notas de la issue o ticket
```

### Tipos de commit
- `feat:` nueva funcionalidad
- `fix:` corrección de bug
- `docs:` documentación
- `style:` formato (no afecta código)
- `refactor:` reestructurar código
- `test:` agregar o modificar tests
- `chore:` tareas de mantenimiento

### Ejemplos
```bash
git commit -m "feat: agregar módulo de facturación"
git commit -m "fix: corregir cálculo de cambio en POS"
git commit -m "docs: actualizar guía de despliegue"
git commit -m "test: agregar tests para dashboard widgets"
```

---

## 9. Configuración SSH (Opcional pero Recomendado)

### Generar clave SSH
```bash
ssh-keygen -t ed25519 -C "tu@email.com"
```

### Agregar clave al agente
```bash
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519
```

### Copiar clave pública
```bash
# Windows
clip < ~/.ssh/id_ed25519.pub

# Linux/Mac
cat ~/.ssh/id_ed25519.pub
```

### Agregar en GitHub/GitLab
1. GitHub → Settings → SSH and GPG keys → New SSH key
2. Pegar la clave pública

### Cambiar remote a SSH
```bash
git remote set-url origin git@github.com:TU_USUARIO/miscuentas.git
```

---

## 10. Verificar Subida Exitosa

```bash
# Ver remote
git remote -v

# Ver historial
git log --oneline

# Verificar que no hay archivos pendientes
git status
```

---

## Información del Proyecto

| Campo | Valor |
|-------|-------|
| Nombre | MisCuentas |
| Versión | 1.0.0 |
| Descripción | Sistema de inventario, POS y contabilidad |
| PHP | 8.2+ |
| Framework | Laravel 11 + Filament v3 |
| Tests | 91 tests / 303 assertions |
| Autor | [Tu nombre aquí] |
| Email | [Tu email aquí] |
| Repositorio | [URL del repositorio] |