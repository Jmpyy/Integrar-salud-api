# ⚙️ Integrar Salud | API REST Core v2.0
> **Backend v2.0** - El motor lógico y de datos para la gestión clínica moderna.

Esta es la API central de **Integrar Salud**, encargada de procesar la lógica de negocio, la seguridad y la persistencia de datos en MySQL. En su versión 2.0, la API ha sido extendida para soportar todos los módulos necesarios de un centro de salud completo, manteniendo una arquitectura ligera, rápida y fácil de desplegar en cualquier servidor con soporte PHP.

---

## 🚀 Funcionalidades del Motor (v2.0)
- **🔐 Seguridad JWT Avanzada:** Autenticación robusta basada en tokens para proteger los datos médicos, con control de roles (Admin, Médico, Recepcionista).
- **📅 Engine de Agenda Completo:** Lógica avanzada para la gestión de turnos, estados de asistencia, cancelaciones y reprogramaciones.
- **🏥 Gestión de Pacientes y HC:** ABM de pacientes, historias clínicas digitales, antecedentes médicos y evoluciones.
- **💰 Accounting v2.0 (Finanzas):** Soporte para transacciones complejas, control de ingresos/egresos, categorías de gastos, y liquidación de honorarios con ajustes por redondeo.
- **📦 Inventario y Medicamentos:** Endpoints para gestión de stock de medicamentos y recetas.
- **👥 Recursos Humanos y Consultorios:** Administración del personal del centro y asignación de consultorios físicos.
- **📁 File System Seguro:** Gestión segura de archivos adjuntos para historias clínicas (estudios, imágenes).
- **🔍 Filtros de BI y Reportes:** Endpoints optimizados para generar métricas de Business Intelligence, cruce de datos financieros y estadísticos.

---

## 🛠️ Correcciones y Mejoras Recientes
- **Compatibilidad con Entornos XAMPP/Windows:** Solución al fallo de carga crítica del `JWT_SECRET` (Error 500) modificando la abstracción de variables de entorno (`core/Env.php`) para incluir el arreglo superglobal `$_ENV` como sistema de respaldo primario, garantizando un despliegue sin fallos en servidores de desarrollo locales y VPS sin apache mod_env.

---

## 🛠️ Stack Tecnológico
- **Lenguaje:** [PHP 8.x](https://www.php.net/) (Vanilla, arquitectura modular sin frameworks pesados para máximo rendimiento).
- **Base de Datos:** [MySQL / MariaDB](https://www.mysql.com/) (Esquema relacional optimizado para historial médico).
- **Autenticación:** [Firebase JWT](https://github.com/firebase/php-jwt).
- **Servidor Recomendado:** Nginx o Apache sobre Ubuntu.

---

## 📂 Estructura del Sistema
- `/api`: Endpoints públicos y protegidos organizados por módulos (Pacientes, Turnos, Finanzas, Medicamentos, Personal, etc.).
- `/core`: Clases base para conexión a BD PDO, manejo de respuestas JSON estandarizadas y middlewares de seguridad.
- `/uploads`: Almacenamiento local de documentos médicos (protegido por `.htaccess`).
- `/certificates`: Almacenamiento de claves o certificados (si aplica).
- `/config`: Archivos de configuración del entorno.
- `database.sql`: Esquema completo y actualizado para inicializar el sistema de base de datos v2.

---

## 📦 Guía de Instalación

1.  **Subir archivos:** Sube el contenido de esta carpeta a tu servidor web (ej: `/var/www/integrar-salud/backend`).
2.  **Base de Datos:** Crea una base de datos MySQL e importa el archivo `database.sql`.
3.  **Configuración:**
    Crea un archivo `.env` en la raíz de la carpeta backend basándote en un archivo de ejemplo (si existe) o configurando lo siguiente:
    ```env
    DB_HOST=localhost
    DB_NAME=integrar_salud
    DB_USER=tu_usuario
    DB_PASS=tu_contraseña
    JWT_SECRET=una_clave_muy_segura
    ALLOWED_ORIGINS=http://localhost:5173,https://tu-dominio.com
    ```
4.  **Dependencias Composer (Opcional si ya se incluyen):**
    Si usas Composer para el JWT:
    ```bash
    php composer.phar install
    ```
5.  **Permisos:** Asegúrate de que la carpeta `/uploads` tenga permisos de escritura (`775`) y pertenezca al usuario del servidor web (`www-data`).

---

**© 2026 Integrar Salud - Potenciando la eficiencia en salud.**
