# Ficha de Google Play — Despertá (es-NI)

## Recursos gráficos

- Ícono de Play: `assets/desperta-play-icon-512.png` — PNG 512 × 512, 32 bits y 280 KB.
- Gráfica promocional: `assets/desperta-feature-graphic-1024x500.jpg` — JPEG 1024 × 500 y 53 KB.
- Capturas: guardarlas en `screenshots/` después de capturarlas desde una compilación Android real. No se incluyeron imágenes simuladas: deben representar exactamente la versión que se envía a revisión.

## Texto para la ficha principal

**Nombre de la app**

Despertá

**Descripción corta**

Despertá con una alarma que te reta y te ayuda a sostener tu hábito.

**Descripción completa**

Despertá es una alarma con reto para empezar el día con más atención.

Configurá tus alarmas por hora y por los días que elijás. Cuando suene, completá una serie de tres preguntas para apagarla. Si respondés mal, la alarma sigue activa hasta que terminés el reto.

También podés activar la vibración, elegir si querés posponer y revisar un historial local de tus ejecuciones recientes para entender cómo van tus mañanas.

Tus alarmas, preferencias e historial se guardan únicamente en tu dispositivo. Despertá no requiere crear una cuenta ni incorpora publicidad o analítica en la versión publicada.

## Capturas obligatorias

Subir al menos dos capturas PNG o JPEG de la app real, sin marcos de teléfono ni texto promocional agregado. Para esta ficha, capturar en vertical y en este orden:

1. Inicio con al menos dos alarmas programadas y activas.
2. Editor de alarma mostrando hora, repetición, vibración y reto.
3. Reto de alarma activo, antes de completarlo.
4. Historial con ejecuciones reales de ejemplo.

Mantener cada captura entre 320 y 3840 px por lado y sin transparencia. Para una presentación óptima en teléfonos, exportarlas a 1080 × 1920 px (relación 9:16).

## Datos para Play Console

- Categoría sugerida: Herramientas.
- Correo de asistencia: `hello@momotombo.dev`.
- Política de privacidad: `https://donmanueldev.github.io/desperta/privacy.html`.
- Declaración de seguridad de datos: sin datos recopilados ni compartidos, según la versión auditada; confirmar este dato contra el AAB final y cualquier SDK incluido antes de enviarla.

## Antes de enviar a revisión

- Confirmar que la URL de privacidad abre públicamente y que identifica a Momotombo Devs y a Despertá.
- Completar App content: clasificación de contenido, público objetivo, acceso a la app y anuncios.
- Revisar cada permiso efectivo del AAB, en especial alarmas exactas, pantalla completa, notificaciones, reinicio, vibración y reproducción en primer plano, y que su uso coincida con la declaración de Play.
- Generar y firmar un Android App Bundle de lanzamiento con un `versionCode` mayor que cualquier versión publicada.
- Probar el AAB en una pista interna antes de promoverlo; tomar las capturas desde esa compilación.
