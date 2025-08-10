# Sistema de Bitácora de Incidencias

## Descripción

El sistema de bitácora de incidencias permite a los usuarios reportar problemas con los recursos que han reservado, y notifica automáticamente a los administradores del sistema.

## Características Principales

### Para Usuarios
- **Reportar Incidencias**: Los usuarios pueden reportar problemas con recursos reservados
- **Seguimiento**: Ver el estado de sus incidencias reportadas
- **Historial**: Acceso al historial completo de incidencias

### Para Administradores
- **Notificaciones Automáticas**: Reciben notificaciones inmediatas de nuevas incidencias
- **Gestión de Incidencias**: Cambiar estados, agregar comentarios, resolver problemas
- **Dashboard Integrado**: Vista general de todas las incidencias y estadísticas
- **Sistema de Notificaciones**: Panel dedicado para gestionar notificaciones

## Estructura de la Base de Datos

### Tabla: `bitacora_incidencias`
- `id_incidencia`: Identificador único
- `id_reserva`: Referencia a la reserva
- `id_usuario`: Usuario que reporta la incidencia
- `id_recurso`: Recurso afectado
- `titulo`: Título de la incidencia
- `descripcion`: Descripción detallada del problema
- `prioridad`: Nivel de prioridad (baja, media, alta, crítica)
- `estado`: Estado actual (reportada, en_revision, en_proceso, resuelta, cerrada)
- `fecha_reporte`: Fecha y hora del reporte
- `fecha_resolucion`: Fecha de resolución (si aplica)
- `notas_administrador`: Comentarios del administrador
- `id_administrador_resuelve`: Administrador que resuelve la incidencia

### Tabla: `notificaciones_incidencias`
- `id_notificacion`: Identificador único
- `id_incidencia`: Referencia a la incidencia
- `id_usuario_destino`: Usuario que recibe la notificación
- `tipo`: Tipo de notificación (nueva_incidencia, actualizacion_incidencia, incidencia_resuelta)
- `mensaje`: Mensaje de la notificación
- `leida`: Estado de lectura
- `fecha_creacion`: Fecha de creación

## Flujo de Trabajo

### 1. Reporte de Incidencia
1. El usuario accede a "Reportar Incidencia"
2. Selecciona una reserva activa
3. Completa el formulario con título, descripción y prioridad
4. Al enviar, se crea automáticamente una notificación para los administradores

### 2. Gestión por Administradores
1. Los administradores reciben notificaciones automáticas
2. Pueden ver todas las incidencias en el panel de gestión
3. Pueden cambiar estados, agregar comentarios y resolver problemas
4. Cada cambio genera notificaciones para el usuario que reportó la incidencia

### 3. Seguimiento del Usuario
1. Los usuarios pueden ver el estado de sus incidencias
2. Reciben notificaciones cuando hay actualizaciones
3. Pueden ver comentarios y respuestas de los administradores

## Archivos del Sistema

### Archivos Principales
- `public/bitacora/reportar.php` - Formulario para reportar incidencias
- `public/bitacora/mis_incidencias.php` - Lista de incidencias del usuario
- `public/bitacora/gestionar.php` - Panel de gestión para administradores
- `public/bitacora/ver_incidencia.php` - Vista detallada de una incidencia
- `public/admin/notificaciones_incidencias.php` - Panel de notificaciones

### Base de Datos
- `db/bitacora_incidencias.sql` - Estructura de la base de datos
- Triggers automáticos para notificaciones

## Instalación

### 1. Base de Datos
```sql
-- Ejecutar el archivo SQL de la base de datos
source db/bitacora_incidencias.sql;
```

### 2. Configuración
- Copiar `config/config.ini.example` a `config/config.ini`
- Configurar los parámetros de la base de datos
- Asegurar que los directorios de logs y uploads tengan permisos de escritura

### 3. Permisos
- Verificar que el usuario de la base de datos tenga permisos para crear triggers
- Asegurar que PHP tenga permisos para escribir en los directorios de logs

## Uso del Sistema

### Reportar una Incidencia
1. **Acceso**: Menú → Reportar Incidencia
2. **Selección**: Elegir una reserva activa del dropdown
3. **Formulario**: Completar título, descripción y prioridad
4. **Envío**: Hacer clic en "Reportar Incidencia"

### Gestionar Incidencias (Administradores)
1. **Acceso**: Menú → Gestionar Incidencias
2. **Lista**: Ver todas las incidencias con filtros
3. **Acciones**: Cambiar estado, agregar comentarios
4. **Resolución**: Marcar como resuelta o cerrada

### Ver Notificaciones (Administradores)
1. **Acceso**: Menú → Notificaciones
2. **Panel**: Vista general de todas las notificaciones
3. **Filtros**: Por tipo, estado de lectura
4. **Acciones**: Marcar como leídas, ver incidencias relacionadas

## Estados de las Incidencias

- **Reportada**: Incidencia recién creada
- **En Revisión**: Administrador está evaluando el problema
- **En Proceso**: Se está trabajando en la solución
- **Resuelta**: Problema solucionado
- **Cerrada**: Incidencia finalizada

## Prioridades

- **Baja**: No afecta el uso básico del recurso
- **Media**: Afecta parcialmente el uso
- **Alta**: Dificulta significativamente el uso
- **Crítica**: Imposibilita el uso del recurso

## Notificaciones Automáticas

### Triggers Implementados
1. **Nueva Incidencia**: Notifica a todos los administradores
2. **Actualización de Estado**: Notifica al usuario que reportó la incidencia
3. **Resolución**: Notifica cuando la incidencia se marca como resuelta

### Tipos de Notificación
- `nueva_incidencia`: Cuando se crea una nueva incidencia
- `actualizacion_incidencia`: Cuando cambia el estado
- `incidencia_resuelta`: Cuando se resuelve la incidencia

## Personalización

### Estilos CSS
- Los estilos están incluidos en cada archivo PHP
- Se pueden personalizar modificando las clases CSS
- Compatible con el sistema de estilos existente

### Configuración
- Prioridades configurables en la base de datos
- Estados personalizables según necesidades
- Roles y permisos configurables

## Seguridad

- Verificación de sesiones en todas las páginas
- Validación de permisos por rol de usuario
- Sanitización de entradas de usuario
- Logs de todas las acciones realizadas

## Mantenimiento

### Logs del Sistema
- Todas las acciones se registran en `log_acciones`
- Incluye usuario, acción, detalles y fecha
- Útil para auditoría y debugging

### Limpieza de Datos
- Las notificaciones se pueden marcar como leídas
- Las incidencias resueltas se pueden cerrar
- Historial completo mantenido para referencia

## Soporte

Para soporte técnico o reportar problemas:
- Revisar los logs del sistema
- Verificar la configuración de la base de datos
- Comprobar permisos de archivos y directorios

## Versión

Sistema de Bitácora v1.0
Compatible con Sistema de Gestión de Recursos Digitales 