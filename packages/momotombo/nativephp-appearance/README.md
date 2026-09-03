# Appearance Plugin for NativePHP Mobile

A NativePHP Mobile pluginRuntime appearance control for Despertá.

## Installation

```bash
composer require momotombo/nativephp-appearance
```

## Usage

```php
use Momotombo\NativephpAppearance\Facades\Appearance;

// Execute functionality
$result = Appearance::execute(['option1' => 'value']);

// Get status
$status = Appearance::getStatus();
```

## Listening for Events

```php
use Livewire\Attributes\On;

#[On('native:Momotombo\NativephpAppearance\Events\AppearanceCompleted')]
public function handleAppearanceCompleted($result, $id = null)
{
    // Handle the event
}
```

## License

MIT