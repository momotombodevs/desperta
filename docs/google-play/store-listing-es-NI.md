# Ficha de Google Play — Despertá (es-NI)

## Recursos gráficos

- Ícono de Play: `assets/desperta-play-icon-512.png` — PNG 512 × 512, 32 bits y 280 KB.
- Gráfica promocional: `assets/desperta-feature-graphic-1024x500.jpg` — JPEG 1024 × 500 y 53 KB.
- Banner con el collage de las seis capturas más representativas, recortadas al contenido de la app: `assets/desperta-screenshots-collage-1024x500.jpg` — JPEG 1024 × 500.
- Capturas: ocho capturas de Android físico, ya listas en `screenshots/`. No se incluyeron imágenes simuladas: representan la versión ejecutada en el dispositivo.

## Texto para la ficha principal

**Nombre de la app**

Despertá

**Descripción corta**

Despertá con una alarma que te reta y te ayuda a sostener tu hábito.

**Descripción completa**

Despertá es una alarma con reto para empezar el día con más atención.

Configurá tus alarmas por hora y por los días que elijás. Cuando suene, respondé las preguntas del reto según la dificultad elegida para apagarla. Si no alcanzás los aciertos necesarios, la alarma sigue activa hasta que completés el reto.

También podés activar la vibración, elegir si querés posponer y revisar un historial local de tus ejecuciones recientes para entender cómo van tus mañanas.

Tus alarmas, preferencias e historial se guardan únicamente en tu dispositivo. Despertá no requiere crear una cuenta ni incorpora publicidad o analítica en la versión publicada.

## Capturas de teléfono listas para cargar

Subir estos archivos en este orden. Son JPEG sin transparencia, de 1440 × 3088 px (9:19.3), por lo que cumplen el rango de 320 a 3840 px de Play.

1. `screenshots/01-home-light.jpg` — alarmas programadas.
2. `screenshots/02-alarm-editor.jpg` — configuración de una alarma.
3. `screenshots/03-settings-es.jpg` — idioma, apariencia y tema de reto.
4. `screenshots/04-challenge-question.jpg` — pregunta del reto de alarma.
5. `screenshots/05-challenge-answer.jpg` — respuesta y validación del reto.
6. `screenshots/06-alarm-completed.jpg` — alarma completada.
7. `screenshots/07-habits.jpg` — progreso y resultados diarios.
8. `screenshots/08-history.jpg` — historial de ejecuciones.

Las capturas muestran tanto la interfaz en inglés como la pantalla de configuración en español, coherente con los idiomas que ofrece la app. Para la ficha es-NI, preferir una futura tanda completamente en español si la localización completa está disponible en la versión que se publicará.

## Datos para Play Console

- Categoría sugerida: Herramientas.
- Correo de asistencia: `hello@momotombo.dev`.
- Sitio web: `https://desperta.momotombo.dev/`.
- Google Play: `https://play.google.com/store/apps/details?id=dev.momotombo.desperta`.
- Política de privacidad: `https://desperta.momotombo.dev/privacy.html`.
- Declaración de seguridad de datos: sin datos recopilados ni compartidos, según la versión auditada; confirmar este dato contra el AAB final y cualquier SDK incluido antes de enviarla.

## Antes de enviar a revisión

- Confirmar que la URL de privacidad abre públicamente y que identifica a Momotombo Devs y a Despertá.
- Completar App content: clasificación de contenido, público objetivo, acceso a la app y anuncios.
- Revisar cada permiso efectivo del AAB, en especial alarmas exactas, pantalla completa, notificaciones, reinicio, vibración y reproducción en primer plano, y que su uso coincida con la declaración de Play.
- Generar y firmar un Android App Bundle de lanzamiento con un `versionCode` mayor que cualquier versión publicada.
- Probar el AAB en una pista interna antes de promoverlo; tomar las capturas desde esa compilación.
