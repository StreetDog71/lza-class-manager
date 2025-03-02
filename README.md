# LZA Class Manager

A WordPress plugin for managing custom CSS classes for Gutenberg blocks.

## Features

- Add and remove CSS classes to any block
- Reorder classes with drag and drop (using react-beautiful-dnd)
- Auto-suggestion of available classes
- Custom CSS editor for class definitions
- Light and dark theme support for the CSS editor
- Theme.json CSS variables sidebar for easy access to theme variables
- Editor-safe class generation for proper block editor previews
- Media query support for responsive classes
- Fully implemented with modern WordPress coding standards using React

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

This feature is implemented using react-beautiful-dnd for accessibility and smooth animations.

## Technical Details

This plugin is built using modern WordPress development practices:

- React components for the UI
- React Beautiful DnD for drag and drop functionality
- WordPress coding standards throughout
- Proper Webpack build process through @wordpress/scripts
- CSS minification for production use

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
- `npm run lint:css` - Lint CSS files
