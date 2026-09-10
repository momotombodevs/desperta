# Despertá

<p align="center">
  <img src="docs/assets/desperta-mark.svg" alt="Logo de Despertá" width="160">
</p>

Una alarma con reto para empezar el día con atención, construida con Laravel y NativePHP Mobile.

## Enlaces públicos

- Sitio oficial: [desperta.momotombo.dev](https://desperta.momotombo.dev/).
- Android: [Descargá Despertá en Google Play](https://play.google.com/store/apps/details?id=dev.momotombo.desperta).

La landing y la política se publican desde `docs/`. Los enlaces a páginas y recursos locales se mantienen relativos; canonical, Open Graph, JSON-LD y sitemap usan el dominio público `https://desperta.momotombo.dev/`.

## Tecnología

- **Laravel** para la lógica de la aplicación y la persistencia local con SQLite.
- **NativePHP Mobile** para ejecutar PHP en el dispositivo y renderizar interfaces nativas con SwiftUI y Jetpack Compose.
- **NativePHP Mobile UI** para construir las pantallas con componentes EDGE nativos.

## Plugins NativePHP utilizados

La aplicación registra explícitamente ocho plugins en `app/Providers/NativeServiceProvider.php`:

- `momotombo/nativephp-alarms`: programación, actualización, cancelación y posposición de alarmas; permisos de alarmas exactas, notificaciones y presentación sobre la pantalla bloqueada; recuperación de la alarma activa. Actualmente es exclusivo de Android.
- `momotombo/nativephp-appearance`: aplica la preferencia de tema del sistema, claro u oscuro en iOS y Android.
- `donmanueldev/nativephp-charts`: renderiza las gráficas nativas del seguimiento de hábitos.
- `unloc/nativephp-svg-component`: muestra recursos SVG nativos, como la marca de Despertá y las banderas de idioma.
- `victorycodedev/toastkit`: presenta confirmaciones y errores de programación de alarmas.
- `unloc/nativephp-enhanced-splash`: configura la pantalla de inicio de la aplicación durante el proceso de compilación nativa.

## Automated Updates

This repository has a GitHub Action that runs daily to keep dependencies up to date:

- Runs `composer update` and commits `composer.lock`
