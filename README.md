# LZA Class Manager

A WordPress plugin for managing custom CSS classes for Gutenberg blocks.

## Features

- Add and remove CSS classes to any block
- Reorder classes with drag and drop
- Auto-suggestion of available classes
- Custom CSS editor for class definitions
- Light and dark theme support for the CSS editor

## Usage

1. Install and activate the plugin
2. Edit any block and find the "Class Manager" panel in the block settings sidebar
3. Add, remove, or reorder classes as needed
4. To customize available classes, go to Tools > LZA Classes

## Drag and Drop

Classes can be reordered using drag and drop:

1. Click and hold on the class button you want to move
2. Drag it to the desired position
3. Release to drop it in its new position

This feature helps maintain a logical order of classes for better readability and organization.

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
