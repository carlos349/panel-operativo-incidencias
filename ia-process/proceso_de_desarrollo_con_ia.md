# Proceso de Desarrollo con IA

## Herramientas Utilizadas

* **Modelo / Asistente:** Claude Code (Fable 5.2)
* **Integraciones:** Excalidraw MCP
* **Skills instaladas:**
  * `/laravel-best-practices`
  * `/spatie-laravel-php`
  * `/livewire-development`
  * `/tailwind-4-docs`
  * `/mysql`
  * `/database-schema-designer`
  * `/documentation`
  * `/ui-ux-pro-max`

---

## Prompts Principales

### Primer Prompt (Especificación Base)

**Inicio Primer Prompt**
>
> #### Especificación Técnica: Panel Operativo de Incidencias
>
> ##### 1. Objetivo General
> Crear desde cero una aplicación web que funcione como un **panel operativo de incidencias**. No se busca una aplicación monolítica ni excesivamente grande, sino una solución **pequeña, coherente, limpia y mantenible**.
>
> ---
>
> ##### 2. Stack Tecnológico Obligatorio
> - **Backend:** PHP + Laravel
> - **Base de Datos:** MySQL
> - **Frontend / UI:** Blade + Livewire + Tailwind CSS + JavaScript
>
> ---
>
> ##### 3. Arquitectura de Base de Datos y Modelos
>
> **Tablas y Campos Requeridos**
>
> - **`incidents`**
>   - `id`
>   - `title`
>   - `description`
>   - `status_id` (FK a `statuses`)
>   - `priority` (Valores: `P1 Critical`, `P2 Alta`, `P3 Media`, `P4 Baja`)
>   - `category_id` (FK a `categories`)
>   - `requester_name`
>   - `assigned_to`
>   - `resolution_date`
>   - `created_at`, `updated_at`
>
> - **`incident_logs`**
>   - `id`
>   - `incident_id` (FK a `incidents`)
>   - `action`
>   - `old_value`
>   - `new_value`
>   - `user_name`
>   - `metadata` (JSON/Text)
>   - `created_at`, `updated_at`
>
> - **`incident_comments`**
>   - `id`
>   - `incident_id` (FK a `incidents`)
>   - `author_name`
>   - `body`
>   - `created_at`, `updated_at`
>
> - **`statuses`**
>   - `id`
>   - `name` (Valores iniciales: `Abierta`, `En Revisión`, `Bloqueada`, `Resuelta`)
>   - `created_at`
>
> - **`categories`**
>   - `id`
>   - `name` (Valores iniciales: `Seguridad`, `Redes`, `Accesos`, `Bug Informático`)
>   - `created_at`
>
> ---
>
> ##### 4. Reglas de Negocio y Modelo SLA
>
> **Matriz de SLA y Horario Laboral**
> - Genera un modelo de resolución de **SLA** con una matriz de tiempo recomendada según el estándar de prioridad (`P1` a `P4`), contemplando tanto el **tiempo de primera respuesta** como el **tiempo de resolución**.
> - El cálculo debe respetar el **horario de oficina**: Lunes a Viernes de `08:00` a `17:00`.
> - *Nota de interacción:* Antes de comenzar a codificar, **consúltame cualquier duda o sugerencia** y proponme una estructura clara para este modelo de SLA.
>
> **Integridad y Trazabilidad**
> - **Transacciones:** Toda modificación sobre una incidencia y la inserción de su respectivo registro en `incident_logs` deben ejecutarse dentro de una **transacción de base de datos (`DB::transaction`)**.
> - **Flujo de estados:** `Abierta` $\rightarrow$ `En Revisión` $\rightarrow$ `Bloqueada` o `Resuelta`.
> - **Reapertura:** Una incidencia en estado `Resuelta` no debe volver a `Abierta` sin dejar un registro explícito de reapertura en el log.
> - **Confirmaciones:** Solicitar confirmación explícita al usuario antes de pasar una incidencia a `Bloqueada` o `Resuelta`.
>
> ---
>
> ##### 5. Requerimientos Funcionales y de Interfaz (UI/UX)
>
> **Pantalla Principal**
> - **Cabecera:** Nombre del aplicativo y breve descripción operativa.
> - **Contador Interactivo:** Contador visual de incidencias agrupadas por estado (que permita filtrar o interactuar con el tablero).
> - **Filtros:** Búsqueda y filtrado dinámico por **estado**, **prioridad** y **texto libre**.
> - **Creación:** Botón y formulario modal/desplegable para crear incidencias con validación estricta (requeridos: *título, descripción, estado, prioridad*).
>
> **Tablero Dinámico (Kanban / Tarjetas)**
> - Visualización por tarjetas mostrando: **prioridad, estado, fecha y responsable**.
> - **Indicadores visuales:** Cada tarjeta/panel debe reflejar el estado de forma intuitiva y alarmante, resaltando especialmente las **incidencias vencidas (SLA) o bloqueadas**.
> - **Cambio de estado:**
>   - *Escritorio:* Acción de arrastrar y soltar (*Drag & Drop*) entre columnas/estados.
>   - *Móvil:* Botón desplegable en la tarjeta para seleccionar y cambiar el estado cómodamente.
>
> **Vista Detallada de la Incidencia**
> - Al seleccionar una tarjeta, se debe desplegar toda su información detallada.
> - **Edición en línea/interna:** Permitir editar **título**, **descripción** y **estado** desde esta vista, registrando cada cambio en `incident_logs`.
> - **Sistema de Pestañas Internas:**
>   1. **Comentarios:** Listado y formulario para agregar nuevos comentarios (`incident_comments`).
>   2. **Historial de Trazabilidad:** Cronología detallada de cambios y transacciones (`incident_logs`).
>
> **Principios de Arquitectura Frontend**
> - Desarrollar con **Blade y Livewire**, evitando CSS caótico (usar utilidades limpias de Tailwind CSS).
> - Diseño **100% responsive** (usable en escritorio y móvil).
> - Aplicar el **patrón de composición** y utilizar **componentes polimórficos** en Blade/Livewire.
> - **Desacoplamiento:** No reflejar directamente la estructura cruda del backend/base de datos en la UI.
>
> ---
> 
> ## 6. Caso Práctico: Simulación y Resolución de Incidencia de Mantenimiento
> 
> Deberás simular un escenario real de mantenimiento de datos corruptos/inconsistentes y documentar su solución:
> 
> 1. **Generación de Datos Corruptos (Seeders):**
>    Crea datos de ejemplo que contengan al menos **tres tipos de inconsistencias**:
>    * Una incidencia en estado `Resuelta` que no tenga su log de resolución correspondiente.
>    * Una incidencia cuyo estado actual no coincida con el `new_value` de su último log.
>    * Un log duplicado o cronológicamente/lógicamente incoherente.
> 
> 2. **Herramienta de Diagnóstico y Saneamiento:**
>    * Implementa una herramienta clara para detectarlas mediante SQL o Eloquent (ej. un **comando Artisan** dedicado, una clase de servicio o script documentado).
>    * Incluye un modo **`--dry-run`** (o equivalente) que muestre qué acciones se tomarían antes de alterar la base de datos.
>    * Corrige los datos de forma segura (creando logs faltantes, ajustando estados o marcando registros para revisión manual).
> 
> ---
>
> ## 7. Entregables y Documentación (`README.md`)
> 
> * Código fuente completo, migraciones y seeders.
> * Archivo `.env.example` limpio (sin credenciales reales).
> * **`README.md` exhaustivo** que permita levantar el proyecto:
>   * Requisitos del sistema (versiones de PHP, Composer, Node.js y MySQL).
>   * Pasos de instalación de dependencias.
>   * Comandos para ejecutar migraciones y cargar los seeders (incluyendo los datos inconsistentes).
>   * Comando para levantar el servidor de desarrollo.
>   * Instrucciones/comandos para ejecutar el diagnóstico y reparación de inconsistencias (incluyendo la explicación de las queries utilizadas y la justificación de las decisiones tomadas).
>   * Notas sobre decisiones técnicas y usuario de prueba (si aplica).
> 
> ---
>
> ##### 8. Exclusiones (Fuera de Alcance)
> **No es necesario implementar:**
> - Sistema de autenticación completo ni gestión avanzada de roles y permisos.
> - Notificaciones (email, push, Slack, etc.).
> - Suites de tests extensas.
> - Contenerización con Docker ni despliegue en servidores públicos.
> - Integraciones con APIs externas.
>
> **Skills invocadas:** `/laravel-best-practices` `/spatie-laravel-php` `/livewire-development` `/tailwind-4-docs` `/mysql` `/database-schema-designer` `/documentation` `/ui-ux-pro-max`
>
**Fin del Primer Prompt**

---

### Segundo Prompt (Refinamiento y Ajustes)

**Inicio Segundo Prompt**
>
> #### Requerimientos Técnicos
> - Mejora la configuración de temas y colores de la app; esta debe contar con al menos 5 temas, proponme colores para estos temas.
> - Veo un CSS default en los desplegables de los `<option>` / `<select>`, arréglalo.
> - Fallo en flujo de estado: estas se deben crear solo con estados iniciales **Abierta** (si no tiene responsable) o **Abierta / En Revisión** (si tiene responsable).
> - Agregar al flujo de estado el estado **Anulado** (cualquier incidencia puede pasar a anulado excepto desde el estado **Resuelto**).
>
**Fin del Segundo Prompt**

---

## Alcance y Decisiones de Arquitectura

### Partes Generadas con Ayuda de IA
La IA se utilizó para el desarrollo funcional completo de todo el panel, junto con su documentación, tests y ejercicios de diagnostico.

### Decisiones Técnicas Propias
* Redacción y estructuración completa del primer prompt definiendo el stack tecnológico, los modelos de base de datos y la necesidad de un sistema de SLA.
* Selección e instalación del conjunto de *skills* especializadas necesarias para guiar el desarrollo del panel.
* Definición de restricciones de arquitectura frontend (composición, componentes polimórficos y desacoplamiento) para cumplir estándares de calidad y mantenibilidad.

### ¿Por qué Blade + Livewire en lugar de Inertia + React?
Para un panel operativo pequeño, **Blade + Livewire** permite construir una interfaz reactiva sin salir de Laravel. Toda la lógica (validación, reglas del flujo de estados, transacciones y cálculo de SLA) vive en un único lugar —el servidor— y la interfaz se limita a reflejarla. Con **Inertia + React**, esa misma lógica obliga a mantener dos capas sincronizadas: controladores y *props* en PHP por un lado, y estado, tipado y componentes en JavaScript por otro.

---

## Errores Detectados y Correcciones Aplicadas

1. **Rendimiento y latencia en peticiones:** Se detectó alta latencia en cada request en entorno de desarrollo (Windows con `php artisan serve`), tardando ~0,6 s por petición. Se resolvió habilitando **OPcache** en `php.ini`, reduciendo el tiempo de respuesta a ~0,1 s.
2. **Flujo de estados:** Se identificó y corrigió la lógica de estados iniciales permitidos según asignación de responsable, además de incorporar el estado *Anulado* con sus restricciones de transición.
3. **Estilos en elementos `<select>`:** Corrección del CSS por defecto del navegador en los desplegables.
4. **Personalización visual:** Incorporación de un sistema de selección de temas (mínimo 5 paletas configurables) ausente en la primera iteración.