# LZA Class Manager

A WordPress plugin for managing custom CSS classes for Gutenberg blocks.

## Features

- Add and remove CSS classes to any block
- Reorder classes with drag and drop
- Auto-suggestion of available classes
- Custom CSS editor for class definitions
- Light and dark theme support for the CSS editor
- Theme.json CSS variables sidebar for easy access to theme variables
- Editor-safe class generation for proper block editor previews
- Media query support for responsive classes

## Usage

### Class Manager Panel

- Edit any block in the WordPress editor
- Find the "Class Manager" panel in the block settings sidebar
- Add, remove, or reorder classes as needed
- Changes take effect immediately in the editor

### CSS Editor

- Go to Tools > LZA Class Manager in the WordPress admin
- Edit your custom CSS classes in the editor
- Use the theme variables panel to insert theme.json CSS variables
- Changes are applied when you save

### CSS Variables Panel

The CSS Variables panel displays all available CSS variables from your theme.json file:

- Click any variable to insert it at the cursor position in the editor
- Use the filter box to quickly find specific variables
- Variables are automatically wrapped in `var()` when inserted

### Drag and Drop

Classes can be reordered using drag and drop:

- Click and hold on a class button
- Drag it to the desired position
- Release to drop it in its new position

This feature helps maintain a logical order of classes for better readability and organization.

### Theme Support

The plugin includes built-in support for different editor themes:

- Light (Default)
- Dark (Dracula)

Your theme preference is saved automatically when you switch themes.

### Media Queries

You can define responsive classes using standard CSS media queries in the editor:

```css
@media (max-width: 768px) {
    .sm-p-m {
        padding: 1rem;
    }
}
```

These classes will work correctly in both the editor and the frontend.

## Development

### Requirements

- Node.js and npm
- WordPress 5.8+

### Setup

```bash
npm install
npm run build
```

### Available Commands

- `npm run start` - Start development build with watch mode
- `npm run build` - Build for production
- `npm run format` - Format code
- `npm run lint:js` - Lint JavaScript files
