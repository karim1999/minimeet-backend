# Admin UI Components

This directory contains reusable Blade components for the MiniMeet admin interface.

## Available Components

### Layout Components

#### `<x-admin.page-header>`
Page header with title, subtitle, and action buttons.
```blade
<x-admin.page-header 
    title="Dashboard"
    subtitle="Overview of your platform"
    :actions="[
        '<x-admin.button href=\'/create\'>Create New</x-admin.button>'
    ]"
/>
```

#### `<x-admin.card>`
Reusable card container with optional header and footer.
```blade
<x-admin.card header="Card Title">
    <p>Card content goes here</p>
    <x-slot name="footer">
        Footer content
    </x-slot>
</x-admin.card>
```

### UI Components

#### `<x-admin.button>`
Styled button component with variants and icons.
```blade
<x-admin.button variant="primary" href="/dashboard">Primary Button</x-admin.button>
<x-admin.button variant="danger" icon="<path...>">Delete</x-admin.button>
```

**Variants**: `primary`, `secondary`, `success`, `danger`, `warning`, `danger-outline`
**Sizes**: `sm`, `md`, `lg`, `xl`

#### `<x-admin.stats-card>`
Statistics display card with optional trends and icons.
```blade
<x-admin.stats-card 
    title="Total Users"
    :value="1250"
    color="green"
    :trend="12"
    trend-label="this month"
    :icon="'<path...>'"
/>
```

#### `<x-admin.status-badge>`
Status indicator badge with auto-color detection.
```blade
<x-admin.status-badge :status="'active'" />
<x-admin.status-badge :status="'inactive'" variant="danger" />
```

#### `<x-admin.alert>`
Alert messages with different types.
```blade
<x-admin.alert type="success" title="Success!" :dismissible="true">
    Operation completed successfully.
</x-admin.alert>
```

**Types**: `success`, `error`, `warning`, `info`

### Form Components

#### `<x-admin.form.input>`
Form input field with validation and help text.
```blade
<x-admin.form.input 
    name="email"
    type="email"
    label="Email Address"
    :required="true"
    help="We'll never share your email"
/>
```

#### `<x-admin.form.select>`
Select dropdown with options and validation.
```blade
<x-admin.form.select 
    name="role"
    label="Role"
    :required="true"
    :options="[
        'admin' => 'Administrator',
        'user' => 'User'
    ]"
/>
```

### Table Components

#### `<x-admin.table>`
Data table with headers and pagination.
```blade
<x-admin.table :headers="['Name', 'Email', 'Status']">
    <tr>
        <td>John Doe</td>
        <td>john@example.com</td>
        <td><x-admin.status-badge :status="'active'" /></td>
    </tr>
</x-admin.table>
```

## Best Practices

1. **Consistent Naming**: Use descriptive names for component props
2. **Color System**: Use the predefined color variants for consistency
3. **Accessibility**: Components include proper ARIA labels and semantic HTML
4. **Responsive**: All components are mobile-first responsive
5. **Validation**: Form components automatically handle Laravel validation errors

## Customization

Components can be customized through:
- CSS classes via the `class` attribute
- Component slots for complex content
- Props for configuration options
- Tailwind utility classes for styling overrides

## Examples

Check the following views for usage examples:
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/tenant-users/create.blade.php`
- `resources/views/admin/system-stats.blade.php`