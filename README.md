# Kardex Tauro: Remote SQLite Proxy

Este es el script de servidor diseñado exclusivamente para permitir que la aplicación de escritorio **Kardex Tauro** se conecte y sincronice con una base de datos **SQLite** alojada remotamente.

El script actúa como un puente (proxy) que recibe consultas desde tu software, las ejecuta sobre el archivo de base de datos en el servidor y devuelve los resultados en formato JSON.

## 🚀 Configuración Inicial

Para que el sistema funcione, es necesario realizar una configuración manual tanto en el script como en tu aplicación:

1.  **Preparación del Servidor:**
    *   Crea una carpeta en tu servidor web.
    *   Copia el archivo `proxy.php` en dicha carpeta.
    *   Coloca el archivo de tu base de datos (`kardex.db`) dentro de la misma carpeta.
    *   **Importante:** Asegúrate de que esta carpeta tenga **permisos completos** de lectura y escritura para el servidor web (ej. `chmod 775` o `777` según el entorno).

2.  **Configuración del Script (`proxy.php`):**
    Abre el archivo y ajusta las siguientes variables:
    *   `$remotePass`: Define la contraseña que también configurarás dentro de Kardex Tauro.
    *   `$jwtSecret`: Establece una frase secreta única y compleja para la generación de tokens (cámbiala por seguridad).

3.  **Configuración en Kardex Tauro:**
    En el menú de configuración de tu software Kardex Tauro:
    *   Ingresa la **URL completa** donde alojaste el `proxy.php`.
    *   Ingresa el mismo **Password** que definiste en la variable `$remotePass` del script.

## 🔒 Notas de Seguridad

*   **Protección de Archivos:** Configura tu servidor (vía `.htaccess` si usas Apache) para **denegar el acceso público directo** al archivo `.db` y a la carpeta `/backs`.
*   **HTTPS:** Se recomienda encarecidamente utilizar una conexión HTTPS para asegurar que la contraseña y los datos transmitidos entre tu escritorio y el servidor no sean interceptados.
*   **Secreto del Token:** Asegúrate de cambiar el valor por defecto de `$jwtSecret`. No compartas esta clave con terceros.



## 🔒 Seguridad del Servidor

Para proteger tus datos contra descargas no autorizadas, es **obligatorio** configurar las restricciones de acceso en tu servidor. 

1. **Archivo .htaccess:** Asegúrate de incluir el archivo `.htaccess` que se encuentra en este repositorio en la misma carpeta que el `proxy.php`. Esto evitará que navegadores o herramientas externas puedan acceder directamente a tu archivo `kardex.db` o a los respaldos guardados en la carpeta `/backs`.

2. **Permisos de carpeta:** Aunque el script necesita permisos de escritura (para crear los respaldos), intenta que la carpeta no tenga permisos de ejecución de scripts innecesarios si tu proveedor de hosting lo permite.
