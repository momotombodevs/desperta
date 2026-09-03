## momotombo/nativephp-appearance

A NativePHP Mobile pluginRuntime appearance control for Despertá.

### Installation

```bash
composer require momotombo/nativephp-appearance
```

### PHP Usage (Livewire/Blade)

Use the `Appearance` facade:

@verbatim
<code-snippet name="Using Appearance Facade" lang="php">
use Momotombo\NativephpAppearance\Facades\Appearance;

// Execute the plugin functionality
$result = Appearance::execute(['option1' => 'value']);

// Get the current status
$status = Appearance::getStatus();
</code-snippet>
@endverbatim

### Available Methods

- `Appearance::execute()`: Execute the plugin functionality
- `Appearance::getStatus()`: Get the current status

### Events

- `AppearanceCompleted`: Listen with `#[OnNative(AppearanceCompleted::class)]`

@verbatim
<code-snippet name="Listening for Appearance Events" lang="php">
use Native\Mobile\Attributes\OnNative;
use Momotombo\NativephpAppearance\Events\AppearanceCompleted;

#[OnNative(AppearanceCompleted::class)]
public function handleAppearanceCompleted($result, $id = null)
{
    // Handle the event
}
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Using Appearance in JavaScript" lang="javascript">
import { appearance } from '@momotombo/nativephp-appearance';

// Execute the plugin functionality
const result = await appearance.execute({ option1: 'value' });

// Get the current status
const status = await appearance.getStatus();
</code-snippet>
@endverbatim