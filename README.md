# 🏥 Integrar Salud - API REST Core

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![JWT](https://img.shields.io/badge/JWT-black?style=for-the-badge&logo=JSON%20web%20tokens)

Núcleo lógico y motor de datos para el ecosistema **Integrar Salud**. Esta API gestiona la persistencia de datos, autenticación segura y lógica de negocio para la administración clínica.

---

## 🚀 Características Principales

- 🔐 **Autenticación Centralizada**: Implementación robusta con JWT (JSON Web Tokens).
- 📅 **Motor de Agenda**: Lógica de colisiones y validación de horarios médicos.
- 📂 **Historia Clínica Digital**: Gestión de pacientes, documentos y evolución médica.
- 💰 **Módulo Financiero**: Registro transaccional de ingresos y egresos.
- 🧾 **Integración AFIP**: Preparado para facturación electrónica (Módulo AFIP).
- 🛡️ **Seguridad**: Filtrado de inputs, protección CORS y variables de entorno.

---

## 🛠️ Estructura del Proyecto

```text
backend/
├── api/            # Endpoints finales por recurso
├── config/         # Configuraciones de base de datos y sistema
├── core/           # Clúster de lógica central (DB, JWT, Response)
├── libs/           # Librerías de terceros y extensiones
├── uploads/        # Directorio de archivos médicos (protegido)
├── .env.example    # Plantilla de configuración de entorno
└── index.php       # Router principal de la API
```

---

## 📦 Instalación y Configuración

Sigue estos pasos para poner en marcha el servidor localmente:

1.  **Configurar Variables de Entorno:**
    Renombra el archivo `.env.example` a `.env` y completa tus credenciales:
    ```bash
    cp .env.example .env
    ```

2.  **Importar Base de Datos:**
    Crea una base de datos en MySQL (ej: `consultorio_db`) e importa el archivo:
    ```bash
    mysql -u root -p consultorio_db < database.sql
    ```

3.  **Configurar el Servidor Web (Apache):**
    Asegúrate de tener el módulo `mod_rewrite` activo. El archivo `.htaccess` ya está configurado para redirigir todas las peticiones al `index.php`.

4.  **Permisos de Carpetas:**
    Asegúrate de que la carpeta `uploads/` tenga permisos de escritura:
    ```bash
    chmod -R 775 uploads/
    ```

---

## 🔍 Endpoints Principales

| Recurso | Método | Endpoint | Descripción |
| :--- | :--- | :--- | :--- |
| **Auth** | `POST` | `/auth/login` | Inicio de sesión y obtención de Token |
| **Pacientes** | `GET` | `/patients` | Listado y búsqueda de pacientes |
| **Agenda** | `POST` | `/appointments` | Creación de un nuevo turno |
| **Finanzas** | `POST` | `/transactions` | Registro de ingreso/egreso de caja |

---

## 🛡️ Seguridad y Buenas Prácticas

- Los certificados de AFIP ubicados en `/certificates` están excluidos del repositorio por seguridad.
- Todas las respuestas de la API siguen el estándar JSON uniforme.
- El sistema utiliza `PDO` con sentencias preparadas para prevenir SQL Injection.

---

<p align="center">
  Desarrollado con ❤️ para la gestión de salud mental por <b>Jmpyy</b>
</p>
