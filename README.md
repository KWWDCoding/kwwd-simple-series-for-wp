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
- **Dedicated Series Pages** - Every series gets its own page at `/series/{series-slug}/` with featured image, description, and a list/grid of its posts
- **Series Archive** - `/series/` lists all series with configurable cards (image, post count, description; link or inline expand)
- **Series Page Link** - Optionally show a "View Series Page" link in the on-post series box
- **All Series Link** - Optionally show a "View All Series" link to the archive in the on-post series box
- **Frontend Display** - Automatically displays above or below post content (configurable)
- **Shortcode** - Use `[simple_series id=X]` to display anywhere
- **Default Settings** - Set global defaults all series inherit from
- **Per-Series Override** - Customize individual series styling and display position
- **Per-Post Override** - Override the display position on individual posts/pages

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

You can also create a brand-new series on the fly from the post/page edit screen via the **Add New Series** box in the Series meta box - it's title-only and immediately assigned to the current post. Configure its description and display settings later from the main series edit page.

Posts can belong to multiple series - just check all that apply.

### Display Options

By default the series automatically displays above post content. Use the Settings page to configure:
- Display position (Above, Below, Both, or None/shortcode only)
- Default collapsed state
- Default styling options

The position can be overridden per-series in the series Display Settings box, or per-post/page in the Series meta box on the post edit screen. Use shortcode `[simple_series id=X]` to display a series anywhere - replace X with your series ID.

You can display multiple series per page if you wish.

## Default Settings

Go to **Series > Settings** to configure global defaults. The settings page has three tabs:

**Default Display Settings**
- Display Position
- Display Series Page Link
- Display All Series Link
- Background Color
- Background Opacity
- Font Size
- Text Color
- Border Color/Width/Style
- Border Radius
- List Style
- Padding
- Start Collapsed

**Series Page Settings**
- Series Page URL Slug
- Show Series Featured Image
- Fallback to First Post's Featured Image
- Display Series Posts As (List or Grid)
- Display Post Featured Image
- Display Series Description

**Series Archive Settings**
- Show Series Featured Image
- Fallback to First Post's Featured Image
- Show Post Count
- Show Series Description
- Card Link Behavior (Link to series page / Expandable list)

## Series Pages & Archive

Each published series gets a dedicated page at `/series/{series-slug}/` and all series appear on the `/series/` index. To get the URLs working, visit **Settings > Permalinks** and click **Save** once after updating the plugin.

### Series Page Settings tab
- Show Series Featured Image (+ fallback to the first post image that has one)
- Display Series Posts As (List or Grid)
- Display Post Featured Image
- Display Series Description

### Series Archive Settings tab
- **Series Page URL Slug** - The URL base (default `series`). If it conflicts with an existing page or category, the plugin falls back to the next free slug (e.g. `series-2`) and notifies you; you can change it to anything free.
- Show Series Featured Image (+ fallback to the first post image that has one)
- Show Post Count
- Show Series Description
- Display Archive As (Grid or List)
- Card Link Behavior (Link to series page, or Expandable list shown inline)

Individual series can override the default display settings in their Display Settings. The "Series Page Link" can also be overridden per-post/page (Default / Show / Hide) in the Series meta box. Series featured images can be set from the series edit screen.

## Credits

Created with &hearts; for WordPress by KWWDCoding

## License

GPL v3 or later