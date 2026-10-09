# Roadmap de features futuras para Despertá

Documento de planificación para convertir la alarma con retos en una experiencia de despertar consistente y personalizada.

## Problem Statement

Despertá ya permite programar alarmas, resolver un reto para apagarlas, posponerlas y consultar historial y hábitos. El siguiente reto es aumentar la retención y el valor diario sin alejarse de su promesa principal: ayudar a la persona a levantarse y comenzar mejor el día.

Actualmente falta una progresión clara para que el usuario mejore, una explicación sencilla de su comportamiento y una rutina que continúe después de apagar la alarma.

## Solution

Construir una evolución incremental basada en cuatro pilares:

1. Retos que se adapten al desempeño real del usuario.
2. Rachas y resúmenes que hagan visible el progreso.
3. Una rutina breve y configurable después de despertar.
4. Más variedad y disponibilidad de retos y alarmas especiales.

Las funciones deben conservar el enfoque local-first de la app, reutilizar el historial de ejecuciones y mantener la experiencia nativa de NativePHP.

## User Stories

1. Como usuario, quiero que la dificultad del reto se ajuste a mi desempeño, para que la alarma siga siendo efectiva sin volverse frustrante.
2. Como usuario, quiero que la dificultad suba cuando resuelvo los retos demasiado rápido, para evitar que la alarma se vuelva predecible.
3. Como usuario, quiero que la dificultad baje o aparezca una pista después de varios fallos, para poder completar el reto sin abandonar la alarma.
4. Como usuario, quiero ver mi racha actual de días despertándome a tiempo, para mantener la constancia.
5. Como usuario, quiero conocer mi mejor racha histórica, para tener una meta personal clara.
6. Como usuario, quiero definir una meta semanal de mañanas puntuales, para medir mi progreso de forma alcanzable.
7. Como usuario, quiero consultar un resumen semanal, para entender cómo estoy usando las alarmas.
8. Como usuario, quiero saber qué días y horarios me cuestan más, para ajustar mis hábitos.
9. Como usuario, quiero recibir una recomendación basada en mis resultados, para saber qué cambio probar.
10. Como usuario, quiero configurar una rutina posterior a la alarma, para convertir el despertar en el inicio de mi mañana.
11. Como usuario, quiero elegir los pasos de mi rutina, para adaptarla a mis necesidades.
12. Como usuario, quiero marcar cada paso de la rutina como completado, para hacer seguimiento sin tener que usar otra aplicación.
13. Como usuario, quiero resolver retos de memoria, secuencias y cálculo mental, para evitar acostumbrarme a un único tipo de pregunta.
14. Como usuario, quiero escoger paquetes temáticos de preguntas, para despertar con contenido que me resulte interesante.
15. Como usuario, quiero usar un paquete de preguntas sobre Nicaragua, para mantener una experiencia local y diferenciada.
16. Como usuario, quiero crear una alarma para una fecha o evento importante, para tener una protección especial en ocasiones puntuales.
17. Como usuario, quiero configurar una alarma que no permita posponer, para mañanas en las que necesito levantarme sin excepciones.
18. Como usuario, quiero recibir un recordatorio para acostarme, para preparar mejor el día siguiente.
19. Como usuario, quiero ver y controlar mi próxima alarma desde un widget, para no tener que abrir la aplicación.
20. Como usuario, quiero activar o desactivar una alarma desde una acción rápida, para hacer cambios con el menor esfuerzo posible.
21. Como usuario, quiero compartir mi progreso semanal, para celebrar mi constancia sin exponer información sensible.
22. Como usuario, quiero obtener nuevos paquetes de retos sin esperar una nueva versión de la aplicación, para mantener la experiencia fresca.

## Implementation Decisions

- Mantener la aplicación como experiencia nativa de NativePHP Mobile; no convertir pantallas existentes a web views.
- Reutilizar las ejecuciones de alarma, intentos de reto, dificultad, posposiciones e indicadores de hábitos que ya existen.
- Mantener la persistencia local como fuente principal para las funciones de uso diario.
- Centralizar las reglas de dificultad adaptativa en una única política de dominio, separada de la pantalla del reto.
- La dificultad adaptativa debe considerar como mínimo: tiempo de resolución, respuestas correctas, fallos, número de intentos y uso de posponer.
- Las rachas deben tener una definición determinista: una ejecución puntual cuenta como éxito según el estado final registrado por la alarma, no según una inferencia de la interfaz.
- El resumen semanal debe poder calcularse a partir del historial existente y no debe depender de una API externa.
- La rutina de mañana debe ser opcional; una alarma seguirá funcionando aunque el usuario no configure pasos adicionales.
- Los pasos de rutina deben persistir de manera local y asociarse a la ejecución concreta de la alarma para evitar mezclar días.
- Los nuevos retos deben implementar el mismo contrato externo del reto actual: progreso recuperable, intento registrable, resultado final y posibilidad de reanudar tras salir de la app.
- Los paquetes temáticos deben validarse antes de mostrarse al usuario y no deben permitir que una respuesta inválida apague una alarma.
- Las alarmas especiales deben extender el modelo actual de programación en lugar de crear un segundo sistema de alarmas.
- El widget y las acciones rápidas deben respetar el estado real de programación; no deben modificar solo una representación visual.
- El contenido descargable debe diseñarse después de estabilizar el catálogo local. Su fuente, versionado, firma y actualización segura deberán definirse antes de implementarlo.
- No agregar cuentas obligatorias, perfiles sociales ni sincronización remota como requisito de estas features.

## Testing Decisions

- Probar comportamiento observable del usuario: resultado de una ejecución, estado de una alarma, progreso, racha, rutina y resumen.
- Priorizar pruebas de dominio y de componentes NativePHP existentes antes de agregar pruebas específicas de implementación interna.
- La dificultad adaptativa debe cubrir resolución rápida, resolución lenta, fallos, múltiples intentos, posponer y ausencia de historial suficiente.
- Las rachas deben cubrir días consecutivos, días fallidos, alarmas múltiples en un día, ejecuciones pendientes y cambios de zona horaria si el dominio los soporta.
- El resumen semanal debe cubrir semanas vacías, semanas parciales, datos mezclados y traducción de etiquetas.
- La rutina debe cubrir configuración, ejecución incompleta, ejecución completa, reanudación y eliminación de pasos.
- Cada nuevo tipo de reto debe probar selección, respuesta correcta, respuesta incorrecta, restauración de progreso y cierre de alarma.
- Las alarmas especiales deben probar programación, edición, cancelación, activación, permisos y recuperación desde una alarma activa.
- Las acciones rápidas deben probar que el estado visible coincide con el estado persistido y programado.
- El contenido descargable debe probar validación, compatibilidad de versión, duplicados, corrupción y fallback al catálogo local.
- Las pruebas deben seguir el estilo de las pruebas actuales de modelos, servicios de dominio, componentes NativePHP y ciclo de ejecución de alarmas.

## Out of Scope

- Cuenta de usuario obligatoria.
- Red social, ranking público o competencia entre usuarios.
- Chat o recomendaciones generadas por IA.
- Seguimiento real del sueño mediante sensores o wearables.
- Sincronización entre dispositivos.
- Monetización, suscripciones o pagos.
- Administración remota de contenido hasta definir una fuente autorizada y un mecanismo seguro de actualización.
- Reemplazar la programación nativa de alarmas existente.

## Further Notes

- La prioridad de producto es mejorar retención y efectividad de la alarma, no aumentar la cantidad de pantallas.
- La primera entrega recomendada es la combinación de dificultad adaptativa, rachas y resumen semanal.
- Las funciones que requieran capacidades nativas nuevas deben mantenerse como tickets independientes para limitar el riesgo de compilación y permisos por plataforma.
- Cada ticket debe poder demostrarse de forma aislada y conservar el funcionamiento de las alarmas existentes.

## Estado de integración

La implementación local de los tickets 01–10 está integrada y sus pruebas enfocadas pasan. La aceptación final de los tickets 07–10 requiere verificar en un emulador o dispositivo Android la programación, permisos, widget, acciones rápidas y diálogo nativo de compartir. El ticket 11 se descartó; la app conservará su catálogo local y no descargará paquetes de retos.

Las listas de criterios de cada ticket se conservan como aceptación funcional completa; las pruebas automatizadas no sustituyen la verificación en Android indicada arriba.

## Tickets

### 01: Dificultad adaptativa de retos

**What to build:** El reto de la alarma ajusta su dificultad según el desempeño registrado del usuario y sigue siendo recuperable si la aplicación se interrumpe.

**Blocked by:** None (can start immediately).

**Status:** implemented; focused tests pass

- [ ] La dificultad usa señales del desempeño histórico y no solo una selección visual.
- [ ] La dificultad puede subir después de resultados consistentemente rápidos.
- [ ] La dificultad puede bajar o mostrar una ayuda después de fallos repetidos.
- [ ] La ejecución conserva y restaura el progreso correctamente.
- [ ] El resultado final continúa siendo autoritativo para apagar la alarma.
- [ ] Existen pruebas para desempeño rápido, lento, fallos, posponer y datos insuficientes.

### 02: Rachas y metas semanales

**What to build:** El usuario puede ver su racha, su mejor racha y una meta semanal basada en ejecuciones reales.

**Blocked by:** None (can start immediately).

**Status:** implemented; focused tests pass

- [ ] La pantalla de hábitos muestra racha actual y mejor racha.
- [ ] El usuario puede definir una meta semanal válida.
- [ ] Una ejecución cuenta una sola vez y según su resultado persistido.
- [ ] Las alarmas pospuestas o fallidas no se contabilizan como éxitos.
- [ ] Los estados vacíos y las semanas incompletas se muestran claramente.
- [ ] Existen pruebas para días consecutivos, fallos, múltiples alarmas y ausencia de datos.

### 03: Resumen semanal inteligente

**What to build:** La aplicación presenta un resumen semanal comprensible con métricas y una recomendación basada en el comportamiento del usuario.

**Blocked by:** 02: Rachas y metas semanales.

**Status:** implemented; focused tests pass

- [ ] El resumen muestra puntualidad, posposiciones y ejecuciones fallidas.
- [ ] Identifica el día o patrón más difícil cuando existen datos suficientes.
- [ ] La recomendación se basa en datos observables y no afirma más de lo que los datos permiten.
- [ ] El resumen funciona con semanas parciales y semanas sin actividad.
- [ ] Las fechas y etiquetas respetan el idioma configurado.
- [ ] Existen pruebas para datos completos, parciales, vacíos y mezclados.

### 04: Rutina configurable de mañana

**What to build:** Después de apagar una alarma, el usuario puede completar una lista breve de pasos configurables.

**Blocked by:** None (can start immediately).

**Status:** implemented; focused tests pass

- [ ] La rutina es opcional y no bloquea el uso normal de la alarma.
- [ ] El usuario puede crear, ordenar, editar y eliminar pasos.
- [ ] Los pasos se asocian a una ejecución concreta.
- [ ] El progreso se restaura si la aplicación se cierra.
- [ ] La aplicación distingue rutina completa, incompleta y no configurada.
- [ ] Existen pruebas para configuración, ejecución, reanudación y eliminación.

### 05: Nuevos tipos de retos

**What to build:** El usuario dispone de retos de memoria, secuencias y cálculo mental además del catálogo actual.

**Blocked by:** 01: Dificultad adaptativa de retos.

**Status:** implemented; focused tests pass

- [ ] Cada tipo de reto tiene instrucciones claras antes de comenzar.
- [ ] Cada reto registra respuestas correctas, incorrectas, intentos y resultado.
- [ ] El progreso se puede restaurar después de una interrupción.
- [ ] Los retos respetan la dificultad configurada o adaptativa.
- [ ] Una respuesta inválida no permite cerrar la alarma.
- [ ] Cada tipo tiene pruebas de éxito, fallo, restauración y cierre.

### 06: Paquetes temáticos de preguntas

**What to build:** El usuario puede elegir entre paquetes temáticos de retos, incluyendo contenido local de Nicaragua.

**Blocked by:** 05: Nuevos tipos de retos.

**Status:** implemented; focused tests pass

- [ ] Los paquetes disponibles aparecen con nombre y descripción localizados.
- [ ] El paquete seleccionado se conserva para futuras alarmas.
- [ ] El catálogo evita repetir preguntas dentro de una misma ejecución cuando hay suficientes preguntas.
- [ ] El paquete de Nicaragua usa contenido revisado y no datos presentados como hechos actuales sin fuente.
- [ ] Un paquete inválido usa un fallback seguro.
- [ ] Existen pruebas de selección, persistencia, fallback y traducciones.

### 07: Alarmas especiales y días importantes

**What to build:** El usuario puede programar alarmas puntuales o protegidas para fechas importantes, con una política especial de posponer.

**Blocked by:** None (can start immediately).

**Status:** implemented; Android device acceptance pending

- [ ] El usuario puede crear una alarma para una fecha específica.
- [ ] Puede configurar una alarma sin posponer.
- [ ] La alarma puede editarse, desactivarse y cancelarse sin dejar ejecuciones abiertas.
- [ ] La programación refleja el estado real del sistema operativo.
- [ ] Los permisos requeridos se solicitan y sus errores se muestran claramente.
- [ ] Existen pruebas de creación, edición, cancelación, permisos y recuperación.

### 08: Modo descanso y recordatorio de acostarse

**What to build:** El usuario puede recibir un recordatorio para prepararse para dormir y dejar lista la alarma del día siguiente.

**Blocked by:** 07: Alarmas especiales y días importantes.

**Status:** implemented; Android device acceptance pending

- [ ] El usuario puede activar o desactivar el recordatorio nocturno.
- [ ] Puede definir la hora del recordatorio y los días de repetición.
- [ ] El recordatorio no se presenta como medición de sueño.
- [ ] La alarma del día siguiente puede revisarse o modificarse desde el flujo.
- [ ] La desactivación cancela correctamente el recordatorio nativo.
- [ ] Existen pruebas para repetición, edición, desactivación y permisos.

### 09: Widget y acciones rápidas

**What to build:** El usuario puede consultar y controlar la próxima alarma desde fuera de la aplicación.

**Blocked by:** None (can start immediately).

**Status:** implemented; Android device acceptance pending

- [ ] El widget muestra la próxima alarma real o un estado vacío correcto.
- [ ] El usuario puede activar o desactivar una alarma desde una acción rápida.
- [ ] La acción rápida actualiza la persistencia y la programación nativa.
- [ ] Los estados de permiso y error no quedan ocultos.
- [ ] El comportamiento se documenta y verifica por plataforma soportada.
- [ ] Existen pruebas del estado persistido y del comportamiento cuando no hay alarmas.

### 10: Compartir progreso semanal

**What to build:** El usuario puede generar y compartir un resumen visual de su progreso semanal sin revelar información privada.

**Blocked by:** 02: Rachas y metas semanales; 03: Resumen semanal inteligente.

**Status:** implemented; Android share sheet acceptance pending

- [ ] El resumen incluye solo datos de progreso seleccionados.
- [ ] No expone horarios exactos, etiquetas privadas ni información sensible por defecto.
- [ ] El usuario puede abrir el diálogo nativo de compartir.
- [ ] El contenido respeta idioma y tema visual de la aplicación.
- [ ] El flujo funciona cuando no hay datos suficientes.
- [ ] Existen pruebas del contenido y del estado vacío.

### 11: Contenido descargable de retos

**What to build:** La aplicación puede recibir nuevos paquetes de retos de forma versionada y segura, manteniendo el catálogo local si la actualización falla.

**Status:** descartado — se mantiene el catálogo local; no se planean descargas de retos.

**Blocked by:** 06: Paquetes temáticos de preguntas.

- [ ] Cada paquete tiene identificador, versión, idioma y compatibilidad explícitos.
- [ ] El contenido se valida antes de estar disponible para una alarma.
- [ ] Los paquetes duplicados o incompatibles no reemplazan contenido válido.
- [ ] La aplicación conserva el catálogo local si no puede descargar o validar una actualización.
- [ ] El usuario puede ver qué paquetes están disponibles y cuáles están instalados.
- [ ] Existen pruebas de validación, corrupción, duplicados, compatibilidad y fallback.
- [ ] La fuente autorizada, firma y política de actualización quedan definidas antes de activar producción.
