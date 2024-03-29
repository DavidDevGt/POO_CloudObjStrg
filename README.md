
# PHP Object Storage with PDFs

Este proyecto proporciona una solución para almacenar y gestionar documentos PDF en un servidor, utilizando PHP y MySQL. Permite a los usuarios subir documentos PDF, que se almacenan de forma segura en el servidor.

## Estructura del Proyecto

```
POO_CloudObjStrg/
├── config/
│   └── Database.php
├── migrations/
│   ├── 001_create_tables.sql
│   └── migrate_data.php
├── models/
│   └── Upload.php
├── public/
│   ├── index.php
│   └── upload_endpoint.php
├── uploads/
├── .gitignore
├── composer.json
├── composer.lock
└── README.md
```

## Características

- Subida y almacenamiento seguro de archivos PDF.
- Interfaz web para la gestión de archivos.
- Backend en PHP con soporte para operaciones de base de datos MySQL.

## Comandos necesarios

Para poner en marcha el proyecto, necesitarás ejecutar los siguientes comandos:

- Instalar dependencias: `composer install`
- Ejecutar migraciones de la base de datos: `php migrations/migrate.php`

## Configuración

1. Asegúrate de tener Composer y PHP instalados en tu sistema.
2. Configura tu conexión a la base de datos en `config/Database.php`.
3. Ejecuta los comandos mencionados anteriormente para configurar el proyecto.

## Uso

Después de configurar el proyecto, puedes acceder a la interfaz web para subir y gestionar tus documentos PDF.

## Futuras Mejoras

- Implementación de un sistema de firma digital y verificación de documentos.
- Integración con blockchain para el registro y verificación de la autenticidad de los documentos.

## Versión
- Version 0.0.l Snaphost 1K6WS1L