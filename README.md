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

## [1.1.1] - 2025-08-11

- Change: Apply filtering via pre_get_posts/parse_query before SQL generation for compatibility with WP_Query(suppress_filters=true)
- Fix: Stable detection of PLLAT translation queries based on meta_query and tax_query signatures
- Fix: Properly set date_query, post_status and order/orderby
- Fix: Removed unreliable SQL-level hooks (posts_where/posts_orderby) and flag-flow
- Dev: Add debug logs when WP_DEBUG is enabled
- I18n: Regenerate POT file with all translatable strings

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