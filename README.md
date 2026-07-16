# Kardex-Tauro-Proxy
Proxy SQLite PHP para Kardex Tauro

# SQLite PHP Proxy Bridge

Este script es un puente ligero diseñado para permitir que aplicaciones de escritorio interactúen con una base de datos **SQLite** alojada en un servidor web remoto mediante peticiones HTTP.

Es ideal para software que necesita persistencia en la nube sin la complejidad de gestionar un motor de base de datos cliente-servidor (como MySQL o PostgreSQL).

## 🚀 Características

*   **Arquitectura Ultra-Ligera:** Un solo archivo `.php` centralizado.
*   **Seguridad:** Autenticación basada en tokens (HMAC SHA-256) para proteger las consultas.
*   **Versatilidad:** Soporta ejecuciones de consulta (`query`), sentencias de modificación (`nonquery`), escalares (`scalar`) y `transacciones` atómicas.
*   **Gestión de BD:** Funciones integradas de `backup` automático (con purga de archivos antiguos) y descarga/restauración de la base de datos.
*   **Compatibilidad:** Formato de respuesta JSON estándar, listo para deserializar en C#, Java, Python o cualquier otro lenguaje.

## 🛠️ Instalación

1.  Copia `proxy.php` a tu servidor.
2.  Asegúrate de que el directorio tenga permisos de escritura (para el archivo `.db` y la carpeta `/backs`).
3.  Configura las credenciales en la sección de configuración del script:
    *   `$usuarioValido` / `$passwordValido`: Tus credenciales de acceso.
    *   `$jwtSecret`: Una cadena única y compleja.

## 📋 Uso del API

Todas las peticiones deben ser `POST` enviando un JSON.

### Acciones disponibles
*   `login`: Autenticación para obtener el token.
*   `query`: Ejecuta `SELECT` y devuelve los datos.
*   `nonquery`: Ejecuta `INSERT`, `UPDATE`, `DELETE`.
*   `scalar`: Retorna un único valor (ej. `SELECT COUNT(*)...`).
*   `transaction`: Ejecuta múltiples sentencias en un bloque atómico.
*   `download`: Descarga una copia de seguridad de la base de datos.

## 🔒 Consideraciones de Seguridad (Importante)

*   **HTTPS:** Es indispensable que el servidor tenga un certificado SSL activo para proteger los datos en tránsito.
*   **Acceso al archivo:** Configura tu servidor web (Apache/Nginx) para **prohibir** el acceso público directo al archivo `kardex.db`.
*   **Variables de entorno:** Se recomienda extraer las credenciales a un archivo `.env` o a un archivo de configuración fuera del alcance público del servidor.

## 🛡️ Recomendación de seguridad para Git

No subas tu archivo de base de datos ni tus configuraciones privadas al repositorio. Crea un archivo llamado `.gitignore` en la raíz de tu proyecto con el siguiente contenido:

```text
# Ignorar bases de datos
*.db

# Ignorar carpeta de respaldos
/backs/

# Ignorar archivos de configuración local
config.php
.env
