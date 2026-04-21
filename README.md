# Simple Series by KWWD

*Simple Series* is a WordPress plugin for creating and displaying series to better organise your content.

## Description

*Simple Series* allows you to group related posts and pages into "series" that displays together on your site. Perfect for tutorials, multi-part articles, courses, or any related content collection.

## Features

- **Series Management** - Create unlimited series using the dedicated Series post type
- **Multi-Post Support** - Add posts and pages to multiple series (great for cross-topics) — for example a post on "Spider-man" might be in a "Marvel" series *and* a "Superhero" series
- **Drag-to-Reorder** - Easily reorder posts within a series
- **Customizable Styling** - Extensive styling options:
  - Background color and opacity
  - Text color
  - Font size
  - Border style, width, and color
  - Border radius
  - Container padding
  - List style (decimal, roman, bullets, etc.)
- **Collapsible Display** - Series can start collapsed with toggle
- **Frontend Display** - Automatically displays under post content
- **Shortcode** - Use `[series_series id=X]` to display anywhere
- **Default Settings** - Set global defaults all series inherit from
- **Per-Series Override** - Customize individual series styling

## Installation

1. Upload the plugin files to `/wp-content/plugins/'kwwd-simple-series'/` directory
2. Activate the plugin through WordPress admin
3. Navigate to **Series** in the admin menu to create your first series

## Usage

### Creating a Series

1. Go to **Series > Add New**
2. Enter a title for your series
3. Add a description in the Series Description box (optional)
4. Use the search box to find and add posts/pages to the series (optional but a series won't display until it has at least one post/page associated with it)
5. Drag posts to reorder them
6. Configure styling options in Display Settings if needed
7. Publish

### Assigning Posts to Series

Two ways to assign posts:

1. **From Series Edit Screen** - Use the search/filter box to add posts directly
2. **From Post/Page Edit Screen** - Check the series you want in the Series meta box

Posts can belong to multiple series - just check all that apply.

### Display Options

The series automatically displays above post content. Use the Settings page to configure:
- Default collapsed state
- Default styling options

Use shortcode `[simple_series id=X]` to display a series anywhere - replace X with your series ID.

You can display multiple series per page if you wish.

## Default Settings

Go to **Series > Settings** to configure global defaults:
- Background Color
- Background Opacity
- Font Size
- Text Color
- Border Color/Width/Style
- Border Radius
- List Style
- Padding
- Start Collapsed

Individual series can override these in their Display Settings.

## Credits

Created with &hearts; for WordPress by KWWDCoding

## License

GPL v3 or later