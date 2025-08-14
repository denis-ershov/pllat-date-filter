# PLLAT Date Filter

A WordPress plugin that adds intelligent date filtering functionality for **Polylang Automatic AI Translation** plugin. Control which posts get translated by filtering them based on publication date.

## ✨ Features

- 🗓️ **Flexible Date Filtering**: Choose from specific date or date range
- 🎯 **Smart Integration**: Automatically integrates with Polylang admin menu
- ⚡ **Real-time UI**: Dynamic form fields based on filter type selection
- 🔧 **Easy Configuration**: Simple settings page with intuitive interface
- 📝 **Debug Logging**: Built-in logging for troubleshooting
- 🔒 **Safe & Secure**: Proper WordPress coding standards and security practices

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

### Settings Location
- **With Polylang**: `Languages → Date Filter`
- **Without Polylang**: `Settings → PLLAT Date Filter`

## 🎯 Use Cases

- **Recent Content Only**: Translate only posts from the last month
- **Archive Processing**: Translate content from specific years
- **Incremental Translation**: Process posts in date-based batches
- **Content Migration**: Translate content from specific publication periods

## 🔧 Requirements

- WordPress 6.0+
- PHP 8.1+
- [Polylang](https://wordpress.org/plugins/polylang/) or [Polylang Pro](https://polylang.pro/)
- [Polylang Automatic AI Translation](https://www.epicwpsolutions.com/plugins/polylang-automatic-ai-translation/) plugin

## 📖 How It Works

The plugin hooks into the WordPress query system and automatically applies date filters when the Polylang Automatic AI Translation plugin requests posts for translation. It works by:

1. Detecting PLLAT plugin queries using specific meta keys
2. Adding date conditions to the SQL WHERE clause
3. Logging filter applications for debugging

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
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

## 📝 Changelog

## [1.2.0] - 2025-08-11

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

## [1.1.1] - 2025-08-11

### 🔄 Major Changes
- **Filtering Mechanism**: Switched from SQL-level hooks to `pre_get_posts` and `parse_query` for reliable application
- **Query Detection**: Improved detection of Polylang AI translation queries by inspecting WP_Query object properties

### 🐛 Bug Fixes
- **Filter Compatibility**: Fixed filtering not working due to `suppress_filters` in main plugin's WP_Query
- **Query Detection**: More robust detection using `no_found_rows`, `meta_query`, and `tax_query` signatures
- **SQL Hooks**: Removed unreliable `posts_where` and `posts_orderby` SQL string manipulation

### 🛠️ Developer Experience
- **Debug Logging**: Added comprehensive logging to `debug.log` for easier verification
- **Error Handling**: Better error handling and validation

## [1.1.0] - 2025-08-10

### 🎉 Initial Release
- **Date Filtering**: Filter posts by start date or date range
- **Status Filtering**: Filter by post status (publish, draft, pending, etc.)
- **Order Control**: Control date ordering (ascending/descending)
- **Admin Interface**: User-friendly settings page in WordPress admin
- **Polylang Integration**: Seamless integration with Polylang menu structure

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