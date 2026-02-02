# PLLAT Date Filter

A WordPress plugin that adds intelligent date filtering functionality for **Polylang Automatic AI Translation** plugin. Control which posts get translated by filtering them based on publication date.

## ✨ Features

- 🗓️ **Flexible Date Filtering**: Choose from specific date or date range
- 🔄 **Automatic Date Updates**: Automatically update start date on schedule to prevent exceeding Bulk Size limit
- 🎯 **Smart Integration**: Automatically integrates with Polylang admin menu
- ⚡ **Real-time UI**: Dynamic form fields based on filter type selection
- 🔧 **Easy Configuration**: Simple settings page with intuitive interface
- 📝 **Debug Logging**: Built-in logging for troubleshooting
- 🔒 **Safe & Secure**: Proper WordPress coding standards and security practices
- ⚡ **Performance Optimized**: Caching system reduces database queries, prevents memory leaks

## 🚀 Installation

### Method 1: Manual Installation
1. Download the plugin files
2. Upload to `/wp-content/plugins/pllat-date-filter/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings in **Languages → Date Filter** (if Polylang is active) or **Settings → PLLAT Date Filter**

### Method 2: Git Clone
```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/denis-ershov/pllat-date-filter.git
```

## ⚙️ Configuration

### Filter Types

#### 📅 From Specific Date
- Translates posts published **on or after** the specified date
- Perfect for processing only recent content

#### 📊 Date Range
- Translates posts published **between** two specific dates (inclusive)
- Ideal for processing content from specific time periods

### 🔄 Automatic Start Date Updates

Prevent exceeding Bulk Size limits by automatically updating the start date on a schedule. This ensures new posts are always included in the translation queue.

#### Update Intervals
- **Hourly**: Update every hour
- **Twice Daily**: Update twice per day (every 12 hours)
- **Daily**: Update once per day (recommended)
- **Weekly**: Update once per week

#### Update Methods

**Set to today minus N days**
- Always sets the start date to today minus the specified number of days
- Perfect for maintaining a rolling window of recent content
- Example: With 7 days, always processes posts from the last week

**Shift current date forward by N days**
- Shifts the existing start date forward by the specified number of days
- Useful for gradual progression through historical content
- Automatically prevents setting dates in the future

### Settings Location
- **With Polylang**: `Languages → Date Filter`
- **Without Polylang**: `Settings → PLLAT Date Filter`

## 🎯 Use Cases

- **Recent Content Only**: Translate only posts from the last month
- **Archive Processing**: Translate content from specific years
- **Incremental Translation**: Process posts in date-based batches
- **Content Migration**: Translate content from specific publication periods
- **Bulk Size Management**: Automatically adjust date range to stay within Bulk Size limits (up to 3000 items per content type)
- **Continuous Translation**: Keep translation queue updated with new content automatically

## 🔧 Requirements

- WordPress 6.0+
- PHP 8.1+
- [Polylang](https://wordpress.org/plugins/polylang/) or [Polylang Pro](https://polylang.pro/)
- [Polylang Automatic AI Translation](https://www.epicwpsolutions.com/plugins/polylang-automatic-ai-translation/) plugin

## 📖 How It Works

The plugin hooks into the WordPress query system and automatically applies date filters when the Polylang Automatic AI Translation plugin requests posts for translation. It works by:

1. Detecting PLLAT plugin queries using specific meta keys (`_pllat_exclude_from_translation`) and query parameters
2. Adding date conditions to the SQL WHERE clause via `pre_get_posts` hook or SQL filters as fallback
3. Logging filter applications for debugging
4. Using optimized caching to minimize database load
5. Preventing interference with regular frontend queries through smart detection logic

## 🐛 Debugging

Enable WordPress debug logging to see filter activity:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Look for entries like:
```
PLLAT DATE FILTER: Applied "from date" filter >= 2025-07-01 00:00:00
PLLAT DATE FILTER: Auto-updated start date from 2025-07-01 to 2025-07-08 (method: shift_days, days: 7)
```

## 🌐 Translations

If translations are not showing up in the admin panel:

1. **Compile MO files**: PO files need to be compiled to MO files for WordPress to use them
   - Use `msgfmt` command: `msgfmt -o languages/pllat-date-filter-ru_RU.mo languages/pllat-date-filter-ru_RU.po`
   - Or use WP-CLI: `wp i18n make-mo languages/`
   - Or use online converter: https://po2mo.net/
   - See [COMPILE_TRANSLATIONS.md](COMPILE_TRANSLATIONS.md) for detailed instructions

2. **Clear cache**: Clear WordPress cache and reload the admin page

3. **Check language**: Ensure WordPress language is set correctly in Settings → General

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## 📝 Changelog

## [1.4.0] - 2026-02-02

### 🚀 Performance & Optimization
- **Settings Caching**: Implemented static cache for plugin settings to reduce database queries from ~15-20 to 1 per page load
- **Memory Leak Prevention**: Replaced recursive functions with iterative approach to prevent memory leaks
- **Query Optimization**: Added early exit mechanisms for non-PLLAT queries, reducing processing overhead by ~95%
- **Double Filter Prevention**: Implemented tracking system to prevent filters from being applied multiple times to the same query
- **Automatic Cache Cleanup**: Added automatic cleanup of applied filters cache to prevent unlimited growth

### 🔧 Technical Improvements
- **Improved PLLAT Detection**: Enhanced query detection based on analysis of Polylang AI Automatic Translation plugin code
- **SQL Fallback Filters**: Added `posts_where` and `posts_orderby` filters as fallback when `pre_get_posts` is removed
- **Better Query Validation**: Added checks for empty queries before processing
- **Optimized Global Variables**: Improved usage of `global $wpdb` to minimize overhead

### 🐛 Bug Fixes
- **Fixed Frontend Query Interference**: Improved detection logic to prevent filters from affecting regular frontend queries
- **Fixed Memory Issues**: Replaced recursive closures with iterative stack-based approach
- **Fixed Double Application**: Prevented filters from being applied multiple times through different hooks

## [1.3.0] - 2026-02-02

### ✨ New Features
- **Automatic Start Date Updates**: Automatically update the start date on a schedule using WordPress cron
- **Flexible Update Intervals**: Choose from hourly, twice daily, daily, or weekly updates
- **Two Update Methods**: 
  - Set to today minus N days (rolling window)
  - Shift current date forward by N days (gradual progression)
- **Bulk Size Management**: Prevent exceeding Bulk Size limits by automatically adjusting date range
- **Next Update Display**: Shows when the next automatic update is scheduled

### 🔧 Technical Improvements
- **WordPress Cron Integration**: Proper scheduling and cleanup of cron tasks
- **Smart Date Calculation**: Prevents setting dates in the future
- **Enhanced Settings UI**: Dynamic field visibility based on auto-update status
- **Improved Logging**: Detailed logs for automatic date updates in debug mode
- **Performance Optimization**: Added caching for settings to reduce database queries
- **Memory Leak Prevention**: Optimized recursive functions and added automatic cache cleanup
- **Query Optimization**: Early exit from hooks for non-PLLAT queries, preventing double filter application
- **Better PLLAT Detection**: Improved query detection based on analysis of Polylang AI Automatic Translation plugin

### 🌐 Internationalization
- **New Translatable Strings**: Added all new strings to POT file
- **Russian Translations**: Complete Russian localization for new features
- **English Translations**: Updated English language file

### 🐛 Bug Fixes
- **Fixed Frontend Query Interference**: Improved detection logic to prevent filters from affecting regular frontend queries
- **Fixed Double Filter Application**: Added tracking system to prevent filters from being applied multiple times
- **Fixed Memory Issues**: Replaced recursive functions with iterative approach to prevent memory leaks

## [1.2.0] - 2025-08-14

### ✨ New Features
- **Untranslated Posts Filter**: Added option to filter only posts that have not been translated yet
- **Enhanced Meta Query Handling**: Improved translation status filtering using `_pllat_translation_queue` meta field
- **Better Translation Workflow**: More control over which posts are processed during bulk translation runs

### 🔧 Technical Improvements
- **Robust Filter Detection**: Enhanced detection of PLLAT translation queries
- **Meta Query Integration**: Seamless integration with existing date and status filters
- **Debug Logging**: Enhanced logging for translation status filtering

### 🌐 Internationalization
- **New Translatable Strings**: Added all new strings to POT file
- **Russian Translations**: Complete Russian localization for new features
- **English Translations**: Updated English language file

## 📄 License

This project is licensed under the GPL v3 or later - see the [LICENSE](LICENSE) file for details.

## 🙋‍♂️ Support

- **Issues**: [GitHub Issues](https://github.com/denis-ershov/pllat-date-filter/issues)

## 🔗 Related Projects

- [Polylang](https://github.com/polylang/polylang) - Multilingual WordPress plugin
- [Polylang Pro](https://polylang.pro/) - Advanced multilingual features
- [Polylang Automatic AI Translation](https://www.epicwpsolutions.com/plugins/polylang-automatic-ai-translation/) - AI extension plugin extending Polylang with the latest AI Large Language Model technology to generate the most contextual & human-like written translations

---

**Made with ❤️ for the WordPress multilingual community**