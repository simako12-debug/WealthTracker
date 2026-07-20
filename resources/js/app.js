// Livewire 4 bundles and starts its own Alpine instance.
// Importing/starting Alpine here as well would register a second instance,
// which breaks all Alpine directives (modals, dropdowns) app-wide.
// If you need Alpine plugins/components, register them on the
// `alpine:init` event against Livewire's Alpine instead of starting your own.
