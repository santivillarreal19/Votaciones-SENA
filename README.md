<div align="center">

# Votaciones SENA

**Sistema web para la elección de representantes de aprendices.**

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

</div>

Cada aprendiz vota una sola vez por el candidato de su jornada; la administración prepara el año electoral, carga las fichas y los aprendices habilitados, y consulta la participación y los resultados.

> Este README es **bitácora viva del proyecto**. Cada iteración suma una sección al final con lo que se hizo, las decisiones tomadas, problemas encontrados y pendientes. Está pensado para que cualquiera (incluyendo Claude en sesiones futuras) pueda recuperar contexto rápido.

## Qué hace

| Rol | Qué puede hacer |
|---|---|
| **Aprendiz** | Ingresa con su ficha y documento, ve los candidatos de su jornada y registra su voto. |
| **Administración** | Crea y activa el evento del año, define jornadas y horarios de votación, registra candidatos con foto y tarjetón, y gestiona fichas y aprendices. |
| **Consulta** | Revisa resultados y participación por ficha en modo solo lectura. |

**Funciones destacadas**

- **Años electorales independientes:** una misma ficha o un mismo número de tarjetón pueden repetirse en eventos de años distintos.
- **Carga masiva:** importa fichas y aprendices desde archivos, incluido el reporte que entrega Sofía Plus (`.xls`, `.csv`, `.zip`), con lectores de archivos escritos para el proyecto.
- **Estados del aprendiz:** habilitado, en inducción o en deserción; solo vota quien está habilitado.
- **Horario por jornada:** la votación de cada jornada abre y cierra a la hora configurada.
- **Historial por evento** y conteo de votos por ficha.
- **Diagnóstico del servidor:** página de apoyo para revisar extensiones y límites de subida cuando una importación falla en el hosting.

## Stack técnico

- **Backend:** PHP sin framework, con `mysqli`.
- **Base de datos:** MySQL / MariaDB (base `eleccion_representantes`).
- **Frontend:** HTML, CSS y JavaScript propios.
- **Despliegue:** hosting compartido con cPanel.

## Estructura

```
index.php      Portada con el ingreso por rol
vistas/        Pantallas (ingreso, votación, administración, historial)
back/          Acciones de los formularios (registrar voto, cargar archivos, eventos…)
model/         Lógica y consultas (functions.php) y lectores de archivos
conn/          Conexión a la base y scripts de migración
css/ · js/     Estilos y scripts
fotos/         Fotos de candidatos subidas desde el panel (no se versionan)
```

## Puesta en marcha en local

1. Copiar el proyecto en la carpeta web del servidor (WAMP, XAMPP o similar).
2. Crear la base `eleccion_representantes` y cargar su esquema.
3. Ajustar los datos de conexión en `conn/conn.php`.
4. Abrir `http://localhost/Votaciones/`.

Para actualizar una base ya existente, iniciar sesión como administración y abrir `conn/aplicar_migracion.php`, o pegar `conn/migracion_cpanel.sql` en phpMyAdmin. Ambos se pueden ejecutar varias veces sin riesgo.

## Privacidad

Este repositorio no incluye fotos de candidatos, datos de aprendices ni registros del servidor.

## Bitácora de iteraciones

### Iteración 1 — Publicación del repositorio

| | |
|---|---|
| **Fecha** | 2026-10-02 |
| **Estado** | 🔨 Lista — falta el primer commit y el `push` |
| **Objetivo** | Publicar el proyecto en GitHub para el portafolio de Santiago Villarreal Maya, sin datos personales. |

#### Qué se hizo
- Se inició git en el proyecto, que hasta ahora solo existía como carpeta local y en el hosting.
- [.gitignore](.gitignore): deja fuera `fotos/` (fotos de aprendices con nombre), los `error_log` y `verificar.php`.
- Se escribió este README.

#### Decisiones tomadas (con el usuario)
- **Santiago:** el repositorio es público.
- `verificar.php` no se versiona: trae una clave escrita en el código y se usa solo como herramienta de soporte temporal en el servidor.

#### ⏳ Diferido — NO perder
- ⏳ **Esquema base de la base de datos** — el repositorio solo trae migraciones (`conn/`), no un `.sql` para crear la base desde cero. Falta exportar la estructura sin datos de aprendices y versionarla, para que el proyecto se pueda levantar en limpio.
- ⏳ **Credenciales en `conn/conn.php`** — hoy tiene los datos locales de WAMP (`root` sin contraseña) dentro del código. Pasarlas a un archivo fuera de la carpeta pública, como se hizo en Alma Vital, antes de versionar datos de un servidor real.
- ⏳ **Revisar `verificar.php` en el hosting** — si sigue subido, borrarlo o cambiarle la clave.
- ⏳ **Capturas de pantalla** en este README.

---
