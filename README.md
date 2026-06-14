# ⚙️ Integrar Salud | API REST Core v3.0
> **Backend v3.0** - El motor lógico de grado militar para la gestión clínica moderna.

Esta es la API central de **Integrar Salud**, encargada de procesar la lógica de negocio, la extrema seguridad y la persistencia de datos en MySQL. En su versión 3.0, la API ha sido reescrita y auditada para mitigar vulnerabilidades y soportar operaciones clínicas críticas mediante una arquitectura sólida (Zero-Trust) orientada a cookies HttpOnly y control de roles férreo.

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

## 🛠️ Novedades de Arquitectura y Auditoría de Seguridad (Versión 3.0)
- **Zero-Trust & HttpOnly Cookies:** Migración completa de almacenamiento de tokens (antes en localStorage) a Cookies Seguras y HttpOnly, eliminando vulnerabilidades de robo de sesión por XSS en todo el sistema.
- **Gestión Inteligente de Inactividad:** Sistema global de Auto-Cierre de Sesión que lee desde base de datos (`sessionTimeout`) y ajusta la vigencia de las cookies de autenticación, permitiendo sesiones persistentes controladas para profesionales confiables.
- **Auditoría de Roles Avanzada (RBAC & anti-IDOR):** Refuerzo estricto de seguridad que impide la escalada de privilegios y bloquea completamente a los médicos para acceder a la agenda o datos financieros de colegas, limitando sus acciones estrictamente a la visualización de su propio tiempo y sus pacientes asignados.
- **WebSockets Protegidos (server.js):** El servidor Node.js ahora es capaz de interpretar de forma asíncrona las nuevas cookies HttpOnly y decodificar el `auth_token`, impidiendo la conexión de clientes no autorizados a las salas virtuales y de alertas.
- **Facturación Electrónica AFIP:** Integración nativa y blindada para emitir comprobantes directamente, alojando y utilizando las claves (`.key`, `.crt`) en el directorio restringido del backend sin exponer información al frontend.
- **Sistema de Reseñas Inteligente:** Motor de captación de feedback con Alertas de Crisis que cruza información y alerta al administrador vía WebSocket y push en tiempo real.
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
