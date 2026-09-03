# Despertá — NativePHP Alarm App + Reusable Alarm Plugin

> Especificación técnica y guía de implementación para una app de alarma con desafíos culturales de Nicaragua, construida con NativePHP Mobile v4, junto con un plugin reusable de alarmas para iOS y Android.

---

## 1. Visión del producto

**Despertá** es una aplicación de alarma móvil cuyo objetivo es evitar que el usuario apague la alarma de forma automática o semiconsciente.

La alarma solo puede detenerse cuando el usuario completa correctamente un pequeño desafío. En la primera versión, el desafío estará basado en preguntas relacionadas con Nicaragua.

Ejemplo:

- La alarma suena a las 6:30 AM.
- La app muestra 3 preguntas.
- El usuario debe responder correctamente al menos 2.
- Mientras no alcance el mínimo requerido, la alarma continúa activa.
- Puede existir `Snooze` si el usuario lo habilita.
- La aplicación registra estadísticas de comportamiento y aprendizaje.

La aplicación se construirá sobre un plugin independiente de NativePHP:

```text
desperta-app
      │
      ▼
nativephp-alarms
      │
 ┌────┴────┐
 │         │
iOS     Android
 │         │
AlarmKit AlarmManager
```

El plugin debe poder utilizarse posteriormente en cualquier aplicación NativePHP que necesite alarmas reales.

---

# 2. Objetivos

## 2.1 Objetivos de la aplicación

La aplicación debe permitir:

- Crear alarmas.
- Editar alarmas.
- Eliminar alarmas.
- Activar/desactivar alarmas.
- Configurar una hora específica.
- Configurar repetición semanal.
- Seleccionar días específicos.
- Configurar nombre de alarma.
- Seleccionar sonido.
- Configurar vibración.
- Configurar volumen progresivo cuando la plataforma lo permita.
- Configurar snooze.
- Configurar duración del snooze.
- Configurar dificultad del challenge.
- Configurar categorías de preguntas.
- Exigir un número mínimo de respuestas correctas.
- Mostrar estadísticas.
- Mantener historial de alarmas.
- Registrar rachas.
- Mantener banco de preguntas offline.
- Funcionar sin conexión para las funciones esenciales.

---

## 2.2 Objetivos del plugin

Crear un paquete reusable:

```text
vendor/nativephp-alarms
```

Nombre tentativo:

```text
momotombo/nativephp-alarms
```

El plugin debe abstraer:

### iOS

- AlarmKit.
- Autorización de alarmas.
- Programación.
- Cancelación.
- Snooze.
- Repetición.
- Sonido.
- Presentación del sistema.
- Eventos hacia NativePHP.

### Android

- `AlarmManager`.
- `setAlarmClock()`.
- Exact alarms.
- `PendingIntent`.
- `BroadcastReceiver`.
- Boot rescheduling.
- Alarm permission handling.
- Alarm UI.
- Snooze.
- Sonido.
- Vibración.
- Eventos hacia NativePHP.

El consumidor del plugin no debe conocer las diferencias entre plataformas.

---

# 3. Principio arquitectónico principal

La aplicación **NO debe implementar la lógica de scheduling mediante timers PHP, WorkManager periódico o background jobs de NativePHP**.

Una alarma despertador necesita APIs de alarma provistas por el sistema operativo.

```text
Incorrecto

Laravel scheduler
      ↓
Background task
      ↓
¿Ya son las 6:30?
      ↓
Reproducir sonido
```

Esto no ofrece garantías adecuadas para una alarma real.

La arquitectura correcta es:

```text
Laravel / NativePHP
      ↓
Alarm Plugin
      ↓
Sistema operativo
      ↓
iOS AlarmKit / Android AlarmManager
```

Una vez registrada, la alarma pertenece al mecanismo nativo correspondiente.

---

# 4. Stack

## Application

- NativePHP Mobile v4
- Laravel
- PHP 8.x compatible con NativePHP
- NativePHP native UI / EDGE
- SQLite
- Laravel Collections
- PHPUnit o Pest
- Composer

## iOS Plugin

- Swift
- AlarmKit
- Swift Concurrency
- Foundation

## Android Plugin

- Kotlin
- AlarmManager
- BroadcastReceiver
- PendingIntent
- Android notification APIs cuando sean necesarias
- Coroutines únicamente donde aporten valor

---

# 5. Organización de repositorios

Se recomienda mantener dos repositorios independientes.

```text
desperta/
nativephp-alarms/
```

No introducir el plugin directamente dentro de la aplicación salvo durante el prototipo inicial.

Ventajas:

- Responsabilidad única.
- Versionado independiente.
- Testing independiente.
- Reutilización.
- Publicación futura.
- Posibilidad de open source.
- Evolución del plugin sin acoplarlo al dominio de Nicaragua.

---

# 6. Arquitectura de la aplicación

Aplicar una separación inspirada en Clean Architecture sin sobre-ingeniería.

```text
app/
├── Domain/
│   ├── Alarm/
│   ├── Challenge/
│   ├── Question/
│   └── Statistics/
│
├── Application/
│   ├── Alarm/
│   ├── Challenge/
│   └── Statistics/
│
├── Infrastructure/
│   ├── Persistence/
│   ├── NativeAlarm/
│   └── QuestionBank/
│
└── UI/
    ├── Screens/
    ├── Components/
    └── ViewModels/
```

La intención es mantener el dominio independiente de NativePHP.

---

# 7. Dominio

## 7.1 Alarm

Entidad principal:

```php
final class Alarm
{
    public function __construct(
        public AlarmId $id,
        public string $name,
        public LocalTime $time,
        public RepeatSchedule $repeat,
        public AlarmSound $sound,
        public bool $vibration,
        public SnoozeConfiguration $snooze,
        public ChallengeConfiguration $challenge,
        public bool $enabled,
    ) {}
}
```

No usar strings indiscriminadamente para conceptos importantes.

Value Objects recomendados:

```text
AlarmId
LocalTime
RepeatSchedule
AlarmSound
SnoozeConfiguration
ChallengeConfiguration
```

---

# 8. RepeatSchedule

Debe soportar:

```text
Once

Every day

Weekdays

Weekends

Custom
```

Ejemplo:

```php
RepeatSchedule::weekly([
    Weekday::MONDAY,
    Weekday::TUESDAY,
    Weekday::WEDNESDAY,
    Weekday::THURSDAY,
    Weekday::FRIDAY,
]);
```

Evitar guardar:

```php
"mon,tue,wed"
```

como representación principal del dominio.

---

# 9. ChallengeConfiguration

```php
final readonly class ChallengeConfiguration
{
    public function __construct(
        public int $questionCount,
        public int $minimumCorrectAnswers,
        public ChallengeDifficulty $difficulty,
        public array $categories,
    ) {
        if ($minimumCorrectAnswers > $questionCount) {
            throw new DomainException(
                'Minimum correct answers cannot exceed question count.'
            );
        }
    }
}
```

Configuración inicial:

```text
questions = 3
minimumCorrect = 2
difficulty = normal
```

---

# 10. Challenge Flow

```text
Alarm Triggered
      │
      ▼
Create Challenge Session
      │
      ▼
Select Random Questions
      │
      ▼
Show Question #1
      │
      ▼
Show Question #2
      │
      ▼
Show Question #3
      │
      ▼
Evaluate
      │
 ┌────┴─────┐
 │          │
PASS       FAIL
 │          │
 ▼          ▼
Stop       Continue
Alarm      Challenge
```

La regla principal:

```php
$challenge->correctAnswers() >= $challenge->requiredCorrectAnswers();
```

---

# 11. Reglas críticas

## Regla 1

El usuario no puede detener la alarma desde la pantalla principal mientras el challenge obligatorio esté activo.

## Regla 2

Cerrar la pantalla del challenge no debe marcar la alarma como completada.

## Regla 3

El estado `completed` únicamente se establece después de cumplir el challenge.

## Regla 4

Un snooze no cuenta como alarma completada.

## Regla 5

La lógica del challenge vive en el dominio/application layer, no en Swift o Kotlin.

---

# 12. Banco de preguntas

Primera versión enfocada en Nicaragua.

Categorías:

```text
history
geography
culture
food
departments
cities
nature
sports
music
personalities
tourism
language
```

Ejemplo:

```json
{
  "id": "nic-geo-001",
  "question": "¿Cuál es la capital de Nicaragua?",
  "options": [
    "León",
    "Granada",
    "Managua",
    "Masaya"
  ],
  "correct": 2,
  "category": "geography",
  "difficulty": "easy"
}
```

---

# 13. Preguntas offline-first

El banco base de preguntas debe incluirse dentro de la aplicación.

No hacer que apagar la alarma dependa de:

```text
Internet
API externa
Supabase
Firebase
Servidor propio
IA
```

El usuario debe poder apagar la alarma aunque esté:

- sin Wi-Fi;
- sin datos;
- en modo avión;
- con backend caído.

La aplicación puede sincronizar nuevas preguntas cuando haya conexión, pero debe mantener un banco local suficiente.

---

# 14. Selección de preguntas

Crear:

```php
interface QuestionSelector
{
    public function select(
        int $count,
        ChallengeDifficulty $difficulty,
        array $categories,
        array $exclude = [],
    ): array;
}
```

Implementación:

```text
SQLiteQuestionSelector
```

Evitar repetir inmediatamente las mismas preguntas.

Considerar:

```text
recent_question_ids
```

de los últimos challenges.

---

# 15. Difficulty

## Easy

```text
2 correctas de 3
Preguntas fáciles
Snooze permitido
```

## Normal

```text
2 correctas de 3
Dificultad mixta
```

## Hard

```text
4 correctas de 5
Menos repetición
Snooze configurable
```

## Extreme

Opcional para una versión posterior.

```text
5 correctas de 5
Sin snooze
Preguntas difíciles
```

No incluir un modo frustrante por defecto.

---

# 16. Pantallas

## 16.1 Home

Debe mostrar:

```text
06:30
Próxima alarma

[ + Crear alarma ]

--------------------------------

06:30  Trabajo       ON
07:30  Fin de semana ON
08:00  Domingo       OFF
```

---

## 16.2 Create Alarm

Campos:

```text
Time

Repeat
[Mon Tue Wed Thu Fri Sat Sun]

Label

Sound

Vibration

Snooze

Challenge difficulty

Questions

Required answers

Categories
```

CTA:

```text
Save Alarm
```

---

# 17. Alarm Ringing Screen

Esta pantalla debe tener máxima prioridad visual.

```text
06:30

Buenos días.

Para apagar la alarma:
respondé 2 de 3 preguntas.

[ EMPEZAR ]
```

No sobrecargarla de controles secundarios.

---

# 18. Challenge Screen

Ejemplo:

```text
Pregunta 1 de 3

¿Cuál es la capital
de Nicaragua?

○ León
● Managua
○ Granada
○ Masaya

[ Continuar ]
```

Después:

```text
2 / 3 correctas

Buenos días.

[ Apagar alarma ]
```

En caso contrario:

```text
1 / 3 correctas

Todavía no.

[ Nuevo desafío ]
```

---

# 19. Statistics

Métricas iniciales:

```text
alarms_completed
alarms_snoozed
alarms_missed
average_completion_time
questions_answered
correct_answers
accuracy
current_streak
best_streak
```

---

# 20. Daily stats

Ejemplo:

```text
Wake time          06:34
Alarm              06:30
Delay              +4m
Questions          3
Correct            2
Challenge time     38s
Snoozes            1
```

---

# 21. Weekly stats

```text
This week

Alarm success           6 / 7
Average wake-up         06:36
Average delay           4m
Question accuracy       83%
Current streak          5 days
```

---

# 22. Gamification

No convertir inmediatamente la app en un juego completo.

Primera versión:

```text
Streak
XP
Achievements
Knowledge level
```

Ejemplo de XP:

```text
Correct answer       +10 XP
No snooze            +20 XP
7-day streak         +100 XP
Perfect challenge    +25 XP
```

---

# 23. Achievements

Ejemplos:

```text
Primer despertar

7 días seguidos

30 días seguidos

100 preguntas

500 preguntas

Nicaragua Expert

No Snooze Week

Geography Master

History Master
```

---

# 24. Persistencia

SQLite local.

Tablas sugeridas:

```text
alarms
alarm_weekdays
questions
challenge_sessions
challenge_answers
alarm_sessions
daily_statistics
achievements
user_achievements
settings
```

---

# 25. alarms

```sql
CREATE TABLE alarms (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    time TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    vibration INTEGER NOT NULL DEFAULT 1,
    sound_id TEXT,
    snooze_enabled INTEGER NOT NULL DEFAULT 1,
    snooze_minutes INTEGER NOT NULL DEFAULT 5,
    question_count INTEGER NOT NULL DEFAULT 3,
    minimum_correct INTEGER NOT NULL DEFAULT 2,
    difficulty TEXT NOT NULL DEFAULT 'normal',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

---

# 26. alarm_weekdays

```sql
CREATE TABLE alarm_weekdays (
    alarm_id TEXT NOT NULL,
    weekday INTEGER NOT NULL,
    PRIMARY KEY (alarm_id, weekday)
);
```

---

# 27. alarm_sessions

Registra cada ejecución real.

```text
id
alarm_id
scheduled_at
triggered_at
completed_at
snooze_count
status
challenge_session_id
```

Status:

```text
scheduled
ringing
snoozed
completed
missed
cancelled
```

---

# 28. Repositories

Dominio:

```php
interface AlarmRepository
{
    public function find(AlarmId $id): ?Alarm;

    public function save(Alarm $alarm): void;

    public function remove(AlarmId $id): void;

    public function all(): array;
}
```

Infraestructura:

```text
SQLiteAlarmRepository
```

---

# 29. Native alarm abstraction en la aplicación

No utilizar el facade del plugin directamente desde todas las pantallas.

Crear un puerto:

```php
interface NativeAlarmScheduler
{
    public function schedule(Alarm $alarm): void;

    public function cancel(AlarmId $id): void;

    public function snooze(
        AlarmId $id,
        Duration $duration
    ): void;
}
```

Adapter:

```text
NativePHPAlarmScheduler
```

Este adapter usa:

```php
NativeAlarm::schedule(...);
```

Así el dominio no depende del plugin.

---

# 30. Application Use Cases

Crear clases pequeñas y enfocadas:

```text
CreateAlarm
UpdateAlarm
DeleteAlarm
EnableAlarm
DisableAlarm
SnoozeAlarm
HandleAlarmTriggered
StartChallenge
SubmitChallengeAnswer
CompleteChallenge
GetAlarmStatistics
```

---

# 31. CreateAlarm

Responsabilidades:

```text
Validate input
      ↓
Create domain Alarm
      ↓
Persist
      ↓
Schedule native alarm
```

Si el scheduling nativo falla:

```text
Alarm must NOT remain falsely active.
```

Se debe actualizar el estado o reportar el error adecuadamente.

---

# 32. UpdateAlarm

Flujo:

```text
Load existing alarm
      ↓
Apply changes
      ↓
Cancel existing native schedule
      ↓
Schedule new configuration
      ↓
Persist final state
```

Diseñar compensación si el nuevo scheduling falla.

---

# 33. Plugin

Repositorio:

```text
nativephp-alarms/
```

Estructura conceptual:

```text
nativephp-alarms/
├── composer.json
├── nativephp.json
├── src/
│   ├── AlarmServiceProvider.php
│   ├── Facades/
│   │   └── Alarm.php
│   ├── DTO/
│   ├── Enums/
│   ├── Events/
│   └── Exceptions/
│
├── resources/
│   └── native/
│       ├── ios/
│       │   └── Sources/
│       │
│       └── android/
│           └── src/main/
│
├── tests/
└── README.md
```

Ajustar los paths finales a la estructura exacta requerida por el Dev Kit / versión de NativePHP utilizada.

---

# 34. Facade público

API deseada:

```php
use Momotombo\NativePHPAlarms\Facades\Alarm;

Alarm::schedule(
    id: 'morning',
    hour: 6,
    minute: 30,
);
```

Sin embargo, preferir DTOs para configuraciones grandes.

```php
Alarm::schedule(
    AlarmConfiguration::make('morning')
        ->at('06:30')
        ->repeatOn([
            Weekday::MONDAY,
            Weekday::TUESDAY,
            Weekday::WEDNESDAY,
            Weekday::THURSDAY,
            Weekday::FRIDAY,
        ])
        ->sound('morning.mp3')
        ->vibration()
        ->snooze(minutes: 5)
);
```

---

# 35. API pública propuesta

```php
Alarm::requestAuthorization();

Alarm::authorizationStatus();

Alarm::canSchedule();

Alarm::schedule($configuration);

Alarm::update($configuration);

Alarm::cancel($alarmId);

Alarm::cancelAll();

Alarm::snooze($alarmId, $minutes);

Alarm::next();

Alarm::all();

Alarm::exists($alarmId);
```

---

# 36. AlarmConfiguration DTO

```php
final readonly class AlarmConfiguration
{
    public function __construct(
        public string $id,
        public int $hour,
        public int $minute,
        public array $weekdays,
        public ?string $label,
        public ?string $sound,
        public bool $vibration,
        public ?int $snoozeMinutes,
        public array $metadata = [],
    ) {}
}
```

---

# 37. Metadata

El plugin no debe conocer conceptos como:

```text
Nicaragua
questions
XP
streak
challenge difficulty
```

Pero debe permitir metadata:

```php
metadata: [
    'alarmId' => 'uuid',
    'route' => '/alarm/ringing',
]
```

Mantener el plugin genérico.

---

# 38. Eventos

Eventos PHP:

```text
AlarmAuthorizationChanged
AlarmScheduled
AlarmTriggered
AlarmSnoozed
AlarmCancelled
AlarmCompleted
AlarmError
```

Ejemplo:

```php
#[On(AlarmTriggered::class)]
public function alarmTriggered(
    string $alarmId,
    array $metadata
): void {
    // Navigate to alarm challenge
}
```

---

# 39. Errores

No retornar únicamente:

```php
false
```

Definir excepciones explícitas:

```text
AlarmAuthorizationDenied
ExactAlarmPermissionDenied
InvalidAlarmConfiguration
AlarmNotFound
NativeAlarmSchedulingFailed
UnsupportedFeature
```

---

# 40. Capability API

Las plataformas no tendrán exactamente las mismas funcionalidades.

Exponer:

```php
Alarm::capabilities();
```

Resultado conceptual:

```php
[
    'exact' => true,
    'custom_sound' => true,
    'snooze' => true,
    'repeating' => true,
    'system_alarm_ui' => true,
    'volume_control' => false,
]
```

Nunca fingir paridad total entre plataformas.

---

# 41. iOS implementation

En iOS moderno utilizar:

```text
AlarmKit
```

Responsabilidades:

```text
requestAuthorization
schedule
cancel
snooze
repeat
query alarms
map native alarm state
dispatch events
```

---

# 42. iOS authorization

El plugin debe manejar:

```text
AlarmManager.requestAuthorization()
```

La app deberá incluir una descripción clara para:

```text
NSAlarmKitUsageDescription
```

Ejemplo:

```text
Despertá necesita crear las alarmas que configures
para poder avisarte a la hora seleccionada.
```

No solicitar autorización durante el primer frame de onboarding.

Solicitar cuando el usuario:

```text
crea su primera alarma
```

o cuando una pantalla previa explique el beneficio.

---

# 43. iOS AlarmKit

Soportar inicialmente:

```text
one-time alarm
weekly repeating alarm
snooze
cancel
custom presentation
custom sound where supported
```

El bridge debe traducir:

```text
PHP AlarmConfiguration
        ↓
Swift Configuration
        ↓
AlarmKit
```

---

# 44. iOS version compatibility

No asumir silenciosamente que todos los iPhone soportan AlarmKit.

Crear:

```swift
func alarmCapabilities() -> AlarmCapabilities
```

La aplicación debe decidir qué experiencia ofrecer en sistemas anteriores.

Para el MVP se puede establecer como requisito:

```text
iOS 26+
```

si esto simplifica significativamente el producto inicial.

Documentar claramente esta decisión antes de publicar.

---

# 45. Android implementation

Usar:

```text
AlarmManager
```

Para un despertador real, evaluar prioritariamente:

```kotlin
setAlarmClock(...)
```

porque el producto es explícitamente una aplicación de alarma visible para el usuario.

---

# 46. Android components

Estructura conceptual:

```text
AlarmPlugin.kt

AlarmScheduler.kt

AlarmReceiver.kt

AlarmPermissionManager.kt

BootReceiver.kt

AlarmActivity.kt

AlarmSoundController.kt
```

---

# 47. AlarmReceiver

Responsabilidades:

```text
receive scheduled alarm
      ↓
validate alarm id
      ↓
start alarm presentation
      ↓
dispatch event
```

Mantenerlo pequeño.

No colocar lógica de dominio dentro del receiver.

---

# 48. BootReceiver

Después de reiniciar Android, las alarmas de `AlarmManager` deben reprogramarse cuando corresponda.

Escuchar:

```text
BOOT_COMPLETED
```

Proceso:

```text
Device boot
   ↓
BootReceiver
   ↓
Read persisted native alarm definitions
   ↓
Reschedule enabled alarms
```

El plugin necesita almacenamiento mínimo de su propia configuración nativa o un mecanismo seguro de recuperación.

---

# 49. Exact alarms permission

Android moderno tiene requisitos específicos para exact alarms.

El plugin debe exponer:

```php
Alarm::canScheduleExactAlarms();

Alarm::requestExactAlarmPermission();
```

No intentar programar y esperar que una excepción determine el flujo normal.

---

# 50. Android permissions

Dependiendo del target SDK y diseño final, analizar y declarar únicamente los permisos realmente necesarios.

Posibles permisos:

```text
SCHEDULE_EXACT_ALARM

o

USE_EXACT_ALARM

RECEIVE_BOOT_COMPLETED

VIBRATE
```

La elección entre:

```text
SCHEDULE_EXACT_ALARM
```

y:

```text
USE_EXACT_ALARM
```

debe tomarse considerando las políticas de Google Play vigentes al momento de publicación.

No incluir permisos "por si acaso".

---

# 51. Android full-screen experience

Para una alarma real puede requerirse una experiencia de alta prioridad.

Debe revisarse el comportamiento vigente de:

```text
full-screen intents
lock screen
notification channels
foreground services
```

según la versión objetivo de Android.

La finalidad siempre debe ser legítimamente una aplicación de alarma.

---

# 52. Sonidos

Los sonidos deben poder ser seleccionados mediante un identificador estable.

```php
AlarmSound::from('morning-soft');
```

Mapeo:

```text
morning-soft
morning-classic
nica-birds
marimba
volcano
```

Evitar almacenar paths internos de plataforma directamente en el dominio.

---

# 53. Assets

El plugin debe poder trabajar con assets empacados en la aplicación.

Ejemplo:

```text
resources/
└── sounds/
    ├── morning-soft.*
    ├── classic.*
    └── birds.*
```

Verificar los formatos soportados realmente por iOS y Android antes de distribuir assets definitivos.

---

# 54. Audio ownership

Separar:

```text
system scheduled alarm
```

de:

```text
custom in-app challenge audio
```

El plugin controla la alarma.

La app controla la experiencia del challenge.

Evitar dos reproductores de audio compitiendo simultáneamente.

---

# 55. Snooze

API:

```php
Alarm::snooze(
    id: $alarmId,
    minutes: 5
);
```

Reglas:

```text
snooze < 1 minute => reject

snooze > configured max => reject
```

El plugin no define cuántos snoozes son válidos; eso pertenece a la aplicación.

---

# 56. Snooze policies en la app

Ejemplo:

```text
Unlimited

Maximum 3

Maximum 1

Disabled
```

Estadísticas deben registrar cada snooze.

---

# 57. Time zones

Nunca tratar la hora de la alarma como un timestamp UTC absoluto cuando representa:

```text
06:30 hora local
```

Separar:

```text
LocalTime

Weekday schedule

Timezone semantics
```

Definir comportamiento cuando el usuario cambia de zona horaria.

Para una alarma cotidiana:

```text
06:30
```

normalmente significa:

```text
06:30 de la zona local actual.
```

---

# 58. DST

Aunque Nicaragua no tenga cambios estacionales regulares, la app puede utilizarse internacionalmente.

Crear tests para:

```text
DST forward
DST backward
timezone change
```

No hardcodear reglas de Nicaragua en el motor de scheduling.

---

# 59. Challenge state machine

Modelar explícitamente:

```text
Pending
Active
Passed
Failed
Expired
```

Transiciones válidas:

```text
Pending -> Active

Active -> Passed

Active -> Failed

Failed -> Active

Active -> Expired
```

No permitir:

```text
Pending -> Passed
```

sin iniciar.

---

# 60. Alarm session state machine

```text
Scheduled
    ↓
Ringing
 ┌──┴────┐
 ↓       ↓
Snoozed Challenge
 ↓       ↓
Ringing Completed
```

Persistir las transiciones importantes.

---

# 61. Anti-cheat

No intentar construir seguridad hostil.

El objetivo es ayudar al usuario a despertarse, no impedir que el propietario del teléfono controle su dispositivo.

Evitar tácticas abusivas.

La app puede:

- impedir un botón de stop dentro de su UX;
- exigir challenge antes de completar;
- limitar snooze.

Pero debe respetar:

- controles del sistema operativo;
- accesibilidad;
- permisos;
- políticas de App Store;
- políticas de Google Play.

---

# 62. Accessibility

La alarma debe continuar siendo utilizable por personas con necesidades de accesibilidad.

Considerar:

```text
VoiceOver
TalkBack
Dynamic Type
large font sizes
high contrast
reduced motion
touch targets
```

No depender únicamente del color para indicar respuestas correctas.

---

# 63. Onboarding

Máximo recomendado:

### Screen 1

```text
Una alarma que realmente
te obliga a despertar.
```

### Screen 2

```text
Respondé un desafío
antes de apagarla.
```

### Screen 3

```text
Aprendé algo de Nicaragua
cada mañana.
```

### Screen 4

```text
Crear primera alarma
```

Solicitar permisos en contexto.

---

# 64. Settings

Opciones iniciales:

```text
Default snooze

Default challenge

Sounds

Vibration

Question categories

Difficulty

Theme

Statistics

Reset progress
```

---

# 65. Notifications

Las local notifications normales pueden utilizarse para funciones secundarias:

```text
streak reminders
weekly report
achievement unlocked
question pack available
```

No deben sustituir el mecanismo principal de alarma.

---

# 66. Privacy

La aplicación puede operar completamente sin cuenta en el MVP.

Preferido:

```text
No account required
No backend required
No analytics SDK required
```

La información puede mantenerse local:

```text
alarms
stats
question history
achievements
```

Esto reduce:

- complejidad;
- riesgo;
- costos;
- requisitos de privacidad;
- dependencia de infraestructura.

---

# 67. Backend

No construir backend para la primera versión salvo necesidad comprobada.

Una versión futura podría introducirlo para:

```text
cloud backup
leaderboards
question packs
cross-device sync
community questions
premium subscription
```

Pero el MVP no lo necesita.

---

# 68. Testing Strategy

Testing dividido en:

```text
Domain unit tests

Application tests

Repository integration tests

Plugin PHP tests

iOS native tests

Android native tests

End-to-end device tests
```

---

# 69. Domain unit tests

Ejemplos:

```text
Challenge requires 2/3 correct

Challenge fails with 1/3

Minimum correct cannot exceed question count

Disabled alarm cannot be scheduled

Repeat schedule validates weekdays

Snooze configuration rejects invalid duration

Streak increases after successful day

Streak resets after missed alarm
```

---

# 70. Application tests

Mock:

```text
AlarmRepository

NativeAlarmScheduler

Clock

QuestionSelector
```

Ejemplo:

```php
public function test_create_alarm_schedules_native_alarm(): void
{
    // Arrange
    // Act
    // Assert repository saved
    // Assert native scheduler invoked once
}
```

---

# 71. Clock abstraction

Nunca llenar tests con:

```php
now()
```

directamente.

Crear:

```php
interface Clock
{
    public function now(): DateTimeImmutable;
}
```

Implementaciones:

```text
SystemClock

FakeClock
```

Esto facilita stats, streaks y scheduling.

---

# 72. Plugin contract tests

La API pública del plugin debe tener tests independientes de plataforma.

Ejemplos:

```text
invalid hour rejected

invalid minute rejected

duplicate ID handled

cancel unknown alarm handled

weekdays serialized correctly

metadata round-trip
```

---

# 73. iOS tests

Probar:

```text
configuration mapping

authorization mapping

weekly schedule mapping

metadata mapping

native errors translated correctly
```

Evitar intentar probar AlarmKit completo únicamente con unit tests.

Tener device/simulator validation.

---

# 74. Android tests

Unit tests:

```text
alarm configuration mapping

PendingIntent identity

repeat calculation

permission mapping
```

Instrumented tests:

```text
AlarmReceiver

BootReceiver

exact scheduling

alarm presentation
```

---

# 75. Device test matrix

Mínimo:

## iOS

```text
Latest supported iOS
One previous supported version if applicable
Physical iPhone
Locked device
Silent mode
Focus mode
```

## Android

```text
Pixel / Android latest
Android previous major
Samsung physical device
Locked device
Doze
Battery saver
Reboot
Permission revoked
```

Los fabricantes Android pueden tener comportamientos distintos alrededor de battery management, por lo que un único emulador no es suficiente.

---

# 76. Critical scenarios

No publicar sin probar:

```text
App killed

Phone locked

Phone restarted

Battery saver

No internet

Airplane mode

Timezone changed

Exact alarm permission revoked

Alarm permission denied

Multiple alarms same day

Two alarms near each other

Snooze followed by reboot

Alarm edited while scheduled

Alarm deleted while scheduled
```

---

# 77. Observability local

Para debugging:

```text
alarm scheduled
alarm cancelled
alarm triggered
alarm snoozed
alarm completed
native scheduling error
permission state changed
boot reschedule
```

No registrar información innecesariamente sensible.

Crear niveles:

```text
debug
info
warning
error
```

---

# 78. Plugin logging

Proveer opcionalmente:

```php
Alarm::setDebugLogging(true);
```

Solo en builds de desarrollo.

Nunca activar logging detallado por defecto en producción.

---

# 79. Feature flags

Para features experimentales:

```text
new challenge engine

new alarm presentation

new sounds

premium categories
```

No son necesarias para el primer prototipo.

---

# 80. MVP Scope

La primera versión debe concentrarse en demostrar una experiencia completa.

## MVP

```text
Create alarm
Edit alarm
Delete alarm
Enable/disable
Weekly repetition
Exact scheduling
Alarm sound
Vibration
Snooze
3-question challenge
2/3 requirement
Nicaragua question bank
Basic statistics
Offline-first
iOS
Android
```

---

# 81. Fuera del MVP

No implementar inicialmente:

```text
Social login

Cloud sync

Friends

Leaderboards

AI question generation

Custom user-created challenges

Apple Watch standalone app

Wear OS standalone app

Subscriptions

Ads

Community marketplace

Complex achievements

Social feed
```

---

# 82. Phase 0 — Technical spike

Antes de construir toda la UI:

Objetivo:

```text
NativePHP
   ↓
Plugin
   ↓
Schedule alarm
   ↓
Kill app
   ↓
Lock phone
   ↓
Alarm triggers
```

Implementar primero una única alarma hardcoded.

### Acceptance criteria

```text
iOS alarm fires

Android alarm fires

app can be closed

phone can be locked

event reaches NativePHP

alarm can be cancelled
```

Si esto no funciona de forma fiable, no construir el resto todavía.

---

# 83. Phase 1 — Alarm Plugin Core

Implementar:

```text
authorization
capabilities
schedule
cancel
query
native events
```

Tests correspondientes.

---

# 84. Phase 2 — Repetition

Agregar:

```text
weekly schedules
timezone behavior
boot rescheduling Android
```

---

# 85. Phase 3 — Snooze

Agregar:

```text
snooze
snooze events
snooze persistence
```

---

# 86. Phase 4 — Despertá Core

Implementar:

```text
Alarm domain

Alarm repository

Alarm list

Create alarm

Edit alarm

Delete alarm
```

---

# 87. Phase 5 — Challenge Engine

Implementar:

```text
Question bank

Question selector

Challenge state

Challenge UI

Scoring

Alarm completion
```

---

# 88. Phase 6 — Statistics

Implementar:

```text
Sessions

Accuracy

Wake delay

Snooze count

Streak
```

---

# 89. Phase 7 — Product polish

Agregar:

```text
Sounds

Animations

Onboarding

Accessibility

Empty states

Error states

Permission UX
```

---

# 90. Definition of Done — Plugin

Una feature del plugin está terminada cuando:

- tiene API PHP definida;
- tiene implementación iOS;
- tiene implementación Android;
- maneja errores;
- tiene tests;
- está documentada;
- funciona en dispositivo físico;
- expone capabilities cuando exista diferencia de plataforma;
- no contiene lógica específica de Despertá.

---

# 91. Definition of Done — App

Una feature de la app está terminada cuando:

- dominio implementado;
- UI funcional;
- persistencia implementada;
- tests unitarios;
- integration test relevante;
- estados de error;
- accesibilidad básica;
- comportamiento offline;
- verificación física cuando interactúa con alarmas.

---

# 92. README del plugin

El repositorio del plugin debería documentar:

```text
Requirements

Installation

Plugin registration

Permissions

Quick start

Scheduling

Repeating alarms

Snooze

Cancellation

Events

Capabilities

iOS notes

Android notes

Testing

Troubleshooting
```

---

# 93. Instalación esperada del plugin

Objetivo de experiencia:

```bash
composer require momotombo/nativephp-alarms
```

Después registrar el plugin siguiendo el mecanismo oficial de NativePHP v4.

La documentación actual de NativePHP requiere que los plugins con código nativo se registren explícitamente para que sus implementaciones sean compiladas dentro de la app.

---

# 94. Ejemplo de uso final

```php
use Momotombo\NativePHPAlarms\Data\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Facades\Alarm;
use Momotombo\NativePHPAlarms\Enums\Weekday;

$alarm = new AlarmConfiguration(
    id: 'work-alarm',
    hour: 6,
    minute: 30,
    weekdays: [
        Weekday::MONDAY,
        Weekday::TUESDAY,
        Weekday::WEDNESDAY,
        Weekday::THURSDAY,
        Weekday::FRIDAY,
    ],
    label: 'Buenos días',
    sound: 'morning-soft',
    vibration: true,
    snoozeMinutes: 5,
);

Alarm::schedule($alarm);
```

---

# 95. Event handling final

```php
#[On(AlarmTriggered::class)]
public function onAlarmTriggered(
    string $alarmId
): void {
    $this->handleAlarmTriggered->execute(
        AlarmId::fromString($alarmId)
    );
}
```

`HandleAlarmTriggered`:

```text
load alarm

create alarm session

create challenge

persist states

navigate to challenge
```

---

# 96. SOLID

## Single Responsibility

Separar:

```text
Alarm scheduling

Challenge rules

Question selection

Statistics

Persistence

UI
```

## Open/Closed

Permitir nuevos challenges sin modificar el alarm engine.

Ejemplo futuro:

```text
NicaraguaChallenge

MathChallenge

MemoryChallenge

EnglishChallenge

TypingChallenge
```

Todos pueden implementar:

```php
interface Challenge
{
    public function start(): void;

    public function evaluate(): ChallengeResult;
}
```

---

# 97. Dependency Inversion

Dominio depende de:

```text
AlarmRepository

QuestionRepository

Clock

NativeAlarmScheduler
```

Nunca de:

```text
SQLite

NativePHP facade

AlarmKit

AlarmManager
```

Las dependencias apuntan hacia el dominio.

---

# 98. Future challenge architecture

```text
ChallengeEngine
      │
 ┌────┼─────┬─────┐
 │    │     │     │
Nica Math Memory English
```

Esto permite que el producto evolucione sin modificar el plugin.

---

# 99. Posible modelo premium

No necesario para MVP.

Una versión futura podría ofrecer:

## Free

```text
Basic alarms
Nicaragua questions
Basic stats
Standard sounds
```

## Pro

```text
Advanced challenges
Custom challenges
Premium sound packs
Advanced stats
Cloud backup
Additional countries
```

No monetizar hasta validar retención.

---

# 100. KPIs del producto

Si se publica:

```text
D1 retention

D7 retention

D30 retention

alarms created per user

alarms completed

average snoozes

challenge completion rate

morning active users

crash-free sessions
```

La métrica más importante:

```text
¿La gente sigue utilizando Despertá como alarma
después de varias semanas?
```

No optimizar únicamente descargas.

---

# 101. Nombre

Nombre de trabajo recomendado:

```text
Despertá
```

Tagline:

```text
La alarma que no se calla
hasta que despertés.
```

Alternativa:

```text
Ya Pues
```

Tagline:

```text
Ya pues. Levantate.
```

El nombre definitivo debe validarse contra:

```text
App Store

Google Play

dominio

redes sociales

marca registrada
```

antes del lanzamiento.

---

# 102. Riesgos principales

## Riesgo #1

Diferencias de comportamiento entre iOS y Android.

Mitigación:

```text
Capability API
platform-specific tests
device matrix
```

## Riesgo #2

Restricciones de exact alarms en Android.

Mitigación:

```text
follow official Android guidance
correct permission model
Play policy review
```

## Riesgo #3

Usuarios encuentran el challenge frustrante.

Mitigación:

```text
difficulty levels
snooze options
short challenges
UX testing
```

## Riesgo #4

La app depende de internet.

Mitigación:

```text
offline-first question bank
local alarm configuration
SQLite
```

## Riesgo #5

Plugin demasiado acoplado al producto.

Mitigación:

```text
plugin exposes only alarm primitives
application owns all challenge behavior
```

---

# 103. Orden recomendado de desarrollo

No comenzar por las pantallas bonitas.

Orden:

```text
1. NativePHP v4 project

2. Plugin skeleton

3. iOS schedule alarm

4. Android schedule alarm

5. Kill-app test

6. Locked-device test

7. Plugin API

8. Plugin tests

9. Alarm domain

10. SQLite persistence

11. Alarm CRUD

12. Challenge engine

13. Nicaragua question bank

14. Statistics

15. UI polish

16. Accessibility

17. Device QA

18. Store preparation
```

---

# 104. Primer milestone

El primer milestone debe ser:

> Crear desde PHP una alarma para 2 minutos en el futuro, cerrar completamente la aplicación, bloquear el dispositivo y comprobar que la alarma se dispara correctamente tanto en un iPhone físico como en un Android físico.

Si este milestone funciona, la viabilidad técnica principal está validada.

---

# 105. Segundo milestone

```text
Create alarm from UI
      ↓
Persist SQLite
      ↓
Schedule native alarm
      ↓
Kill app
      ↓
Alarm triggers
      ↓
Open challenge
      ↓
2/3 correct
      ↓
Stop / complete alarm
      ↓
Save statistics
```

Este milestone valida el MVP completo de punta a punta.

---

# 106. Reglas de ingeniería

- No poner lógica de dominio en controllers o NativeComponents.
- No llamar APIs nativas directamente desde UI.
- No duplicar business rules entre PHP, Swift y Kotlin.
- No convertir todas las clases en interfaces sin motivo.
- No crear microservicios.
- No introducir backend antes de necesitarlo.
- No utilizar un scheduler genérico para sustituir APIs reales de alarma.
- No asumir igualdad absoluta entre iOS y Android.
- No depender de red para apagar la alarma.
- No utilizar strings mágicos para estados críticos.
- No ignorar errores de scheduling.
- No considerar terminado un feature de alarmas sin prueba en dispositivo físico.

---

# 107. Branch strategy sugerida

```text
main
develop
feature/*
fix/*
```

Ejemplos:

```text
feature/plugin-ios-alarmkit

feature/plugin-android-alarm-manager

feature/alarm-domain

feature/challenge-engine

feature/statistics
```

Mantener PRs pequeños.

---

# 108. Commit examples

```text
feat(plugin): add iOS alarm scheduling

feat(plugin): add Android exact alarm support

feat(alarm): implement weekly repeat schedule

feat(challenge): require minimum correct answers

test(challenge): cover failed challenge attempts

fix(android): reschedule alarms after reboot
```

---

# 109. CI

Plugin:

```text
PHP lint

PHP static analysis

PHP tests

Android unit tests

iOS unit tests where CI environment permits
```

App:

```text
PHP lint

static analysis

unit tests

integration tests
```

Mantener device/E2E testing en una pipeline separada si el costo o disponibilidad de hardware lo requiere.

---

# 110. Static analysis

Considerar:

```text
PHPStan
```

con nivel progresivamente estricto.

La meta es detectar:

```text
invalid nullable states

incorrect DTO mappings

dead branches

type inconsistencies
```

sin hacer que la configuración sea un obstáculo durante el spike inicial.

---

# 111. Seguridad

No ejecutar contenido remoto arbitrario desde preguntas.

Si en el futuro las preguntas llegan de backend:

```text
validate schema

sanitize text

limit lengths

validate option counts

validate correct index

version question packs

verify integrity where appropriate
```

---

# 112. Question pack format futuro

```json
{
  "version": 1,
  "country": "NI",
  "language": "es-NI",
  "questions": []
}
```

Así el motor puede crecer a:

```text
Costa Rica
Honduras
El Salvador
Guatemala
México
```

sin cambiar su arquitectura principal.

---

# 113. Localization

Aunque el MVP sea Nicaragua:

No hardcodear textos en componentes.

Preparar:

```text
es-NI
```

como locale inicial.

Posible futuro:

```text
en
es-CR
es-MX
```

---

# 114. Product direction

La app no debe posicionarse solamente como:

```text
"quiz sobre Nicaragua"
```

El verdadero producto es:

```text
una alarma que requiere
actividad cognitiva para apagarse.
```

Nicaragua es el primer challenge pack y el principal diferenciador cultural del lanzamiento.

Esto deja abierta la evolución hacia:

```text
Despertá Nicaragua

Despertá Math

Despertá English

Despertá Memory

Despertá Focus
```

sin reescribir el core.

---

# 115. Separación definitiva

```text
┌─────────────────────────────────┐
│            DESPERTÁ             │
│                                 │
│ Alarm configuration             │
│ Challenge engine                │
│ Nicaragua questions             │
│ Statistics                      │
│ Gamification                    │
│ UX                              │
└───────────────┬─────────────────┘
                │
                │ NativeAlarmScheduler
                ▼
┌─────────────────────────────────┐
│       NATIVEPHP-ALARMS          │
│                                 │
│ schedule                        │
│ cancel                          │
│ snooze                          │
│ permissions                     │
│ capabilities                    │
│ native events                   │
└───────────────┬─────────────────┘
                │
        ┌───────┴────────┐
        ▼                ▼
      iOS              Android
    AlarmKit         AlarmManager
```

Ésta debe ser la frontera arquitectónica principal del proyecto.

---

# 116. Fuentes técnicas verificadas

Documentación revisada para esta especificación:

- NativePHP Mobile v4 — Plugins Introduction  
  https://nativephp.com/docs/mobile/4/plugins/introduction

- NativePHP Mobile v4 — Using Plugins  
  https://nativephp.com/docs/mobile/4/plugins/using-plugins

- NativePHP Mobile v4 — Introduction  
  https://nativephp.com/docs/mobile/4/getting-started/introduction

- NativePHP — Local Notifications Plugin  
  https://nativephp.com/plugins/nativephp/mobile-local-notifications

- Apple Developer — AlarmKit  
  https://developer.apple.com/documentation/alarmkit

- Apple Developer — Scheduling an alarm with AlarmKit  
  https://developer.apple.com/documentation/AlarmKit/scheduling-an-alarm-with-alarmkit

- Android Developers — Schedule alarms  
  https://developer.android.com/develop/background-work/services/alarms

- Android Developers — AlarmManager  
  https://developer.android.com/reference/kotlin/android/app/AlarmManager

---

# 117. Resultado esperado del proyecto

Al completar esta guía deben existir dos entregables independientes:

```text
1. Despertá
   Aplicación publicable para iOS y Android.

2. nativephp-alarms
   Plugin reusable para cualquier proyecto NativePHP.
```

El plugin debe resolver infraestructura.

La aplicación debe resolver producto.

Esa separación permitirá que **Despertá sea una aplicación real** y que simultáneamente **nativephp-alarms pueda convertirse en una contribución reusable para el ecosistema NativePHP**.

---

## Final product statement

> **Despertá** es una alarma offline-first construida con NativePHP que utiliza APIs nativas reales de iOS y Android y obliga al usuario a completar un desafío cognitivo antes de considerar la alarma completada.

> **nativephp-alarms** es el plugin cross-platform que abstrae AlarmKit y AlarmManager detrás de una API PHP estable, tipada, testeable y reutilizable.
