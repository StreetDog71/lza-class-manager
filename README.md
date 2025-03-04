# LZA Class Manager

A WordPress plugin for managing custom CSS classes in the Gutenberg block editor.

## Description

LZA Class Manager provides an intuitive interface for adding and managing CSS classes to blocks in the WordPress block editor (Gutenberg). The plugin allows you to create and edit custom CSS classes through a central admin panel, and then easily apply those classes to any block through a dedicated sidebar panel in the editor.

## Use case

Adding custom classes to Gutenberg blocks is a bit cumbersome. You need to open the Advanced tab and insert the class name manually. When you select a block, there's no easy way of telling if it has custom classes applied, forcing you to open the Advanced tab to confirm.

This plugin allows you to do the following:

- Add custom classes to blocks (without the need to open the Advanced tab)
- See which classes are applied to a block (without the need to open the Advanced tab)
- Remove classes applied to a block with a single click
- Reorder the classes applied to a block
- Get a list of classes from the plugin settings page, with auto-complete
- Add your own list of variables and classes in the plugin settings page
- Generate independent CSS files for the admin and frontend based on the classes added to the plugin settings page
- Minify the frontend CSS file
- Get the WordPress global variables in the plugin settings page
- Insert any WordPress global variable in the code editor with a single click
- Filter the list of WordPress global variables
- Edit the CSS variables and classes with a code editor in the plugin settings page

## Features

- **Custom CSS Editor**: Write and manage CSS classes in a dedicated admin interface with syntax highlighting
- **Class Suggestions**: Auto-suggests available classes as you type in the editor panel
- **Drag & Drop**: Reorder classes with intuitive drag and drop functionality
- **Keyboard Navigation**: Full keyboard support for suggestions with arrow keys
- **Theme Integration**: Automatically picks up CSS variables from your active theme
- **Performance Optimized**: Automatically minifies CSS for frontend delivery while keeping the source readable
- **Editor Preview**: Instantly see your custom classes applied to blocks in the editor
- **Responsive Classes**: Support for media query-based responsive classes
- **Dark Mode Support**: Editor theme switching for comfortable coding
- **Developer Friendly**: Well-organized code with hooks for customization

## Installation

- Upload the plugin files to the `/wp-content/plugins/lza-class-manager` directory, or install the plugin through the WordPress plugins screen directly.
- Activate the plugin through the 'Plugins' screen in WordPress
- Navigate to 'Tools' → 'LZA Class Manager' to create and edit your custom CSS classes
- In the block editor, select a block and find the "LZA Class Manager" panel in the block settings sidebar

## Usage

### Creating Custom Classes

- Go to 'Tools' → 'LZA Class Manager'
- Use the CSS editor to add your custom classes
- Click "Save Changes" to update your classes

Example:
```css
.p-l {
    padding: 1rem;
}

.text-center {
    text-align: center;
}

/* Responsive classes */
@media (max-width: 768px) {
    .s-p-m {
        padding: 1rem;
    }
}
```

### Applying Classes to Blocks

- In the block editor, select a block
- Find the "LZA Class Manager" panel in the block settings sidebar
- Type to search for a class or select from the suggestions
- Click a class to remove it, or drag to reorder

## Recent Updates

- **File Information Panel**: Added a dedicated panel showing file sizes and storage location
- **Improved Media Query Support**: Fixed issues with media queries to ensure editor preview matches frontend
- **Enhanced Class Handling**: Class buttons now combine drag-and-drop with click-to-delete functionality
- **Keyboard Navigation**: Added keyboard navigation for class suggestions with auto-scrolling
- **Dark Mode Theme**: Added Dracula theme option for the code editor
- **UI Improvements**: Better styling and visual feedback
- **Real Time Preview**: Preview of the CSS classes when browsing through the suggestions
- **Media Queries exclusion**: Exclude all classes inside @media queries from the editor-safe-classes.css flie to prevent incorrect override of the responsive classes in the editor
- **Code revision**: Code revision and cleanup (Nuno to the rescue!)
- **Coding standards**: Implementation of coding standards tools and first tests (Nuno to the rescue again!)

## Technical Details

- CSS files are stored in the WordPress uploads directory (`/wp-content/uploads/lza-css/`)
- The plugin generates minified CSS for the frontend and editor-safe CSS for the block editor
- Editor-safe CSS includes wrapper selectors to ensure classes work properly in the editor context
- WordPress admin design guidelines are followed for a consistent experience

## Support & Development

This plugin is maintained by Lazy Algorithm. For support requests, feature suggestions, or bug reports, please contact us at <a href="mailto:dev@lza.pt">dev@lza.pt</a> or open an issue on the plugin repository.

## Considerations

Most of this plugin was created with Claude Sonnet 3.7. I did my best to guide the AI through the process, but some parts of this plugin are a bit beyond my knowledge level.
I'm sure there's a lot of room for improvement, and the early feedback from Sérgio and Nuno was a great push in the right direction. It still puzzles me why the admin.js file is using jQuery, but when I asked Claude AI about it the answer was:

*While modern vanilla JavaScript could achieve the same functionality, using jQuery here aligns with WordPress development patterns for admin interfaces and leverages the already-loaded library that's part of the WordPress admin environment.*

As it is, this plugin is already a great addition to my workflow. Having said that, I'm open to any suggestion to improve its functionalities or the code base. All suggestions and ideas are welcome. If you find this plugin as useful as I did, enjoy it!