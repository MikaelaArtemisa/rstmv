# Configuración de Fotos de Perfil

## Pasos para completar la configuración:

### 1. Ejecutar el script SQL
Ejecuta el archivo `add_profile_picture_column.sql` en tu base de datos para agregar la columna `profile_picture` a la tabla `users`:

```sql
ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL AFTER `aboutme`;
```

### 2. Verificar directorios
Asegúrate de que existan los siguientes directorios:
- `uploads/profile_pictures/` (para almacenar las fotos subidas)

### 3. Permisos de directorio
Asegúrate de que el directorio `uploads/profile_pictures/` tenga permisos de escritura (755 o 777 en desarrollo).

## Funcionalidades implementadas:

### ✅ Subida de fotos de perfil
- **Ubicación**: `users/edit-profile.php`
- **Validaciones de seguridad**:
  - Tamaño máximo: 5MB
  - Formatos permitidos: JPG, PNG, GIF
  - Verificación de tipo MIME real
  - Verificación de extensión de archivo
  - Verificación de que sea realmente una imagen
  - Nombres únicos para evitar conflictos
  - Eliminación automática de imagen anterior

### ✅ Visualización en toda la aplicación
- **Perfil de usuario**: `users/profile.php`
- **Dashboard**: `users/dashboard.php`
- **Página de posts**: `postpublicado.html`
- **Modal de publicación**: Incluye foto de perfil

### ✅ Avatar por defecto
- Si no hay foto de perfil, se muestra un avatar generado con las iniciales del username
- Usa el servicio UI Avatars para generar avatares consistentes

### ✅ Interfaz mejorada
- Foto de perfil circular con borde
- Efectos hover
- Icono de cámara para indicar que se puede cambiar
- Preview en tiempo real al seleccionar imagen
- Mensajes informativos sobre requisitos

## Estructura de archivos:
```
uploads/
└── profile_pictures/
    └── profile_[user_id]_[timestamp].[extension]

users/
├── edit-profile.php (subida y edición)
├── profile.php (visualización)
└── dashboard.php (visualización)

postpublicado.html (visualización)
```

## Notas de seguridad:
- Todas las imágenes se validan tanto en frontend como backend
- Se usa `move_uploaded_file()` para prevenir ataques de upload
- Los nombres de archivo se sanitizan
- Se verifica el tipo MIME real del archivo
- Se eliminan archivos antiguos automáticamente 