# Sistema de Gestión de Recursos

Sistema completo para la gestión de recursos, reservas y mantenimientos en entornos académicos o empresariales. Esta aplicación web permite administrar recursos físicos, gestionar reservas, programar mantenimientos y generar reportes detallados.

## Características Principales

- **Gestión de usuarios y roles**: Control de acceso basado en roles (Administrador, Académico, Profesor, Estudiante)
- **Gestión de recursos**: Inventario completo de recursos con diferentes tipos y ubicaciones
- **Sistema de reservas**: Reserva de recursos con verificación de disponibilidad en tiempo real
- **Calendario visual**: Vista de calendario para visualizar todas las reservas programadas
- **Gestión de mantenimientos**: Programación y seguimiento de mantenimientos de recursos
- **Reportes y estadísticas**: Análisis de uso y generación de informes exportables
- **Notificaciones**: Sistema integrado de notificaciones para mantener informados a los usuarios

## Requisitos Técnicos

- PHP 7.4 o superior
- MariaDB 10.4 o MySQL 5.7 o superior
- Servidor web (Apache, Nginx, etc.)
- Navegador web moderno

## Instalación

1. Clonar este repositorio o descargar los archivos en el directorio del servidor web
2. Importar el script SQL `new-sql-script.sql` para crear la base de datos y tablas necesarias
3. Configurar la conexión a la base de datos en `config/database.php`
4. Acceder a la aplicación desde un navegador web
5. Iniciar sesión con el usuario administrador predeterminado:
   - **Usuario**: `admin@sistema.edu`
   - **Contraseña**: `admin123`

## Estructura del Proyecto

```bash
sistema_recursos/
├── config/
│   ├── config.php               # Configuración global de la aplicación
│   └── database.php             # Configuración de conexión a la base de datos
├── includes/
│   ├── functions.php            # Funciones auxiliares
│   └── templates/               # Plantillas reutilizables
├── public/
│   ├── admin/                   # Panel de administración
│   ├── assets/                  # Recursos estáticos (CSS, JS, imágenes)
│   │   ├── css/
│   │   │   ├── styles.css       # Estilos globales
│   │   │   └── reportes.css     # Estilos para módulo de reportes
│   │   └── js/
│   │       ├── main.js          # JavaScript global
│   │       ├── calendar.js      # Funcionalidad para el calendario
│   │       └── reportes.js      # Funcionalidad para el módulo de reportes
│   ├── login.php                # Página de inicio de sesión
│   ├── logout.php               # Script de cierre de sesión
│   ├── usuarios/                # Gestión de usuarios
│   ├── recursos/                # Gestión de recursos
│   ├── reservas/                # Gestión y calendario de reservas
│   ├── mantenimiento/           # Gestión de mantenimientos
│   └── reportes/                # Reportes y estadísticas
└── new-sql-script.sql           # Script SQL para crear la base de datos
```
## Módulos Principales

### Usuarios
- Gestión de usuarios con diferentes roles  
- Activación/desactivación de cuentas  
- Control de permisos por rol  

### Recursos
- Catálogo de recursos organizados por tipos  
- Información detallada incluyendo ubicación y disponibilidad  
- Gestión de estado (disponible, en mantenimiento, dañado, baja)  

### Reservas
- Creación, edición y cancelación de reservas  
- Verificación automática de disponibilidad  
- Vista de calendario para visualizar todas las reservas  
- Sistema de confirmación y notificaciones  

### Mantenimiento
- Programación de mantenimientos preventivos y correctivos  
- Seguimiento del estado de los mantenimientos  
- Actualización automática del estado de los recursos  
- Historial completo de mantenimientos por recurso  

### Reportes
- Análisis detallado del uso de recursos  
- Estadísticas de reservas y mantenimientos  
- Exportación de datos en diferentes formatos  
- Recomendaciones automáticas basadas en patrones de uso  

---

## Guía Rápida de Uso

### Para administradores
- **Dashboard**: Acceso a estadísticas generales desde la página principal  
- **Usuarios**: Gestión de usuarios, roles y permisos  
- **Recursos**: Administración del catálogo de recursos disponibles  
- **Mantenimiento**: Programación y seguimiento de mantenimientos  
- **Reportes**: Generación y exportación de informes detallados  

### Para usuarios (profesores/estudiantes)
- **Reservas**: Creación de nuevas reservas de recursos  
- **Calendario**: Visualización del estado de las reservas  
- **Perfil**: Gestión de información personal y preferencias  

---

## Seguridad
- Autenticación segura con contraseñas encriptadas  
- Control de acceso basado en roles  
- Registro detallado de acciones (logs)  
- Validación de datos en servidor y cliente  

---

## Contribución
1. Hacer un fork del repositorio  
2. Crear una rama para tu nueva funcionalidad (`git checkout -b nueva-funcionalidad`)  
3. Realizar tus cambios y hacer commit (`git commit -m 'Añade nueva funcionalidad'`)  
4. Hacer push a la rama (`git push origin nueva-funcionalidad`)  
5. Abrir un Pull Request  

---

## Licencia
Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

---

## Contacto
Para soporte o consultas, por favor contacta a [amarinmella@gmail.com](mailto:amarinmella@gmail.com)

© 2023-2025 Sistema de Gestión de Recursos. Todos los derechos reservados.


