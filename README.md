# Auto Gradesheet Plugin for Moodle

A Moodle plugin that helps instructors generate and export course gradesheets, with preview and Excel export support.

## Features

- Course-level gradesheet settings
- Gradesheet preview before export
- Export gradesheet data
- Excel export support
- Moodle language string support (`lang/`)
- Plugin versioning and upgrade structure (`version.php`, `db/`)

## Repository Structure

- `index.php` — main entry point for plugin UI/workflow
- `course_settings.php` — course settings and configuration
- `preview.php` — preview gradesheet output before export
- `export.php` — export logic
- `export_excel.php` — Excel-specific export functionality
- `lib.php` — core helper functions and Moodle integration hooks
- `version.php` — plugin metadata and version definition
- `db/` — Moodle database/install/upgrade definitions
- `gradesheet/` — gradesheet-related classes/resources
- `lang/` — language packs
- `pix/` — plugin icons/images

## Requirements

- Moodle (compatible version depends on your `version.php` target)
- PHP version supported by your Moodle installation
- Proper role/capability permissions to access grade data and exports

## Installation

1. Download or clone this repository:
   ```bash
   git clone https://github.com/Gabrielkaos/auto-gradesheet-plugin-moodle.git
   ```

2. Place the plugin folder into the correct Moodle plugin directory (according to this plugin type).

3. In Moodle, go to:
   **Site administration → Notifications**

4. Complete the installation/upgrade prompts.

## Usage

1. Open a Moodle course.
2. Navigate to the plugin page (via course navigation or admin location where it is registered).
3. Configure gradesheet settings.
4. Preview the gradesheet.
5. Export as needed (including Excel export).

## Configuration

- Configure defaults and behavior in `course_settings.php`.
- Language/custom labels can be adjusted via files in `lang/`.
- Any plugin version or upgrade changes should be reflected in `version.php` and corresponding `db/` upgrade scripts.

## Development Notes

- Keep `version.php` updated for every release.
- Add DB changes through Moodle upgrade mechanisms in `db/`.
- Keep export responsibilities separated (`export.php` vs `export_excel.php`) for maintainability.
- Ensure capability checks and context validation are enforced before exposing grade exports.

## Security & Permissions

Because this plugin handles grade-related data:

- Enforce Moodle capability checks before rendering/exporting data.
- Validate course/module context on all entry points.
- Sanitize outputs and validate user inputs.
- Restrict export access to authorized roles only.

## Contributing

Contributions are welcome.

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Open a pull request

## License

Add your license here (for example, GPL-3.0 to align with Moodle ecosystem expectations).
