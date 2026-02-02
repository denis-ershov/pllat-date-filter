<?php
/**
 * Plugin Name: PLLAT Date Filter
 * Plugin URI: https://github.com/denis-ershov/pllat-date-filter
 * Description: Date filtering functionality for Polylang Automatic AI Translation. Filter posts by date range or from specific date when running bulk translations.
 * Version: 1.4.0
 * Author: Denis Ershov
 * License: GPL3
 * Text Domain: pllat-date-filter
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.7
 * Requires PHP: 8.1
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

class PLLAT_Date_Filter {
    
    private $option_name = 'pllat_date_filter_settings';
    // removed legacy SQL-based detection flag
    
    /**
     * Кэш для настроек, чтобы избежать множественных запросов к БД
     * @var array|null
     */
    private static $options_cache = null;
    
    /**
     * Флаг для отслеживания применения фильтров к текущему запросу
     * Предотвращает двойное применение фильтров
     * Очищается после каждого запроса для предотвращения утечки памяти
     * @var array
     */
    private static $applied_filters = array();
    
    /**
     * Максимальное количество записей в кэше applied_filters
     * Предотвращает неограниченный рост массива
     */
    private static $max_applied_filters_cache = 100;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'), 99); // Поздний приоритет чтобы Polylang успел загрузиться
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Применяем фильтры через WP_Query до формирования SQL (высокий приоритет)
        // Используем очень высокий приоритет, чтобы сработать до remove_all_filters
        add_action('pre_get_posts', array($this, 'maybe_apply_filters'), 99999);
        add_action('parse_query', array($this, 'maybe_apply_filters'), 99999);
        
        // Также применяем фильтры через SQL хуки на случай, если pre_get_posts был удален
        add_filter('posts_where', array($this, 'maybe_apply_date_filter_where'), 99999, 2);
        add_filter('posts_orderby', array($this, 'maybe_apply_date_filter_orderby'), 99999, 2);
        
        // Добавляем ссылку на настройки в список плагинов
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Добавляем уведомление если Polylang не активен
        add_action('admin_notices', array($this, 'polylang_notice'));
        
        // Регистрируем cron hook для автоматической смены даты
        add_action('pllat_date_filter_auto_update', array($this, 'auto_update_start_date'));
        
        // Проверяем и регистрируем cron при загрузке
        add_action('init', array($this, 'maybe_schedule_auto_update'));
        
        // Обновляем cron задачу после сохранения настроек
        add_action('updated_option', array($this, 'maybe_update_cron_on_option_save'), 10, 3);
        
        // Очищаем кэш при обновлении опций
        add_action('updated_option', array($this, 'clear_options_cache'), 5, 1);
        
        // Очищаем кэш applied_filters после выполнения запроса для предотвращения утечки памяти
        add_action('wp', array($this, 'clear_applied_filters_cache'));
        add_action('admin_init', array($this, 'clear_applied_filters_cache'));
    }
    
    /**
     * Очищает кэш примененных фильтров для предотвращения утечки памяти
     */
    public function clear_applied_filters_cache() {
        // Очищаем кэш, если он превысил лимит
        if (count(self::$applied_filters) > self::$max_applied_filters_cache) {
            self::$applied_filters = array();
        }
    }
    
    /**
     * Получает настройки с кэшированием для предотвращения множественных запросов к БД
     * @return array
     */
    private function get_options() {
        // Используем статический кэш для всех экземпляров класса
        if (self::$options_cache === null) {
            self::$options_cache = get_option($this->option_name, array());
        }
        return self::$options_cache;
    }
    
    /**
     * Очищает кэш настроек
     */
    public function clear_options_cache($option_name = null) {
        if ($option_name === null || $option_name === $this->option_name) {
            self::$options_cache = null;
        }
    }
    
    /**
     * Загружаем файлы переводов
     */
    public function load_textdomain() {
        $domain = 'pllat-date-filter';
        $locale = apply_filters('plugin_locale', determine_locale(), $domain);
        
        // Загружаем переводы из папки languages плагина
        $mofile = $domain . '-' . $locale . '.mo';
        $path = dirname(plugin_basename(__FILE__)) . '/languages/';
        
        // Пробуем загрузить из папки плагина
        load_textdomain($domain, WP_PLUGIN_DIR . '/' . $path . $mofile);
        
        // Также пробуем стандартный метод для совместимости
        load_plugin_textdomain(
            $domain,
            false,
            $path
        );
    }
    
    /**
     * Добавляем страницу настроек в админку
     */
    public function add_admin_menu() {
        // Пытаемся добавить в меню Polylang, если он активен
        if ($this->is_polylang_active()) {
            add_submenu_page(
                'mlang',                              // parent slug (меню Polylang)
                __('Date Filter Settings', 'pllat-date-filter'), // page title
                __('Date Filter', 'pllat-date-filter'),          // menu title
                'manage_options',                     // capability
                'pllat-date-filter',                  // menu slug
                array($this, 'options_page')         // callback
            );
        } else {
            // Если Polylang не активен, добавляем в общие настройки
            add_options_page(
                __('PLLAT Date Filter', 'pllat-date-filter'),
                __('PLLAT Date Filter', 'pllat-date-filter'),
                'manage_options',
                'pllat-date-filter',
                array($this, 'options_page')
            );
        }
    }
    
    /**
     * Проверяем, активен ли Polylang
     */
    private function is_polylang_active() {
        return function_exists('pll_languages_list') || 
               class_exists('Polylang') || 
               is_plugin_active('polylang/polylang.php') ||
               is_plugin_active('polylang-pro/polylang.php');
    }
    
    /**
     * Инициализация настроек
     */
    public function settings_init() {
        register_setting('pllat_date_filter', $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'pllat_date_filter_section',
            __('Date Filter Settings', 'pllat-date-filter'),
            array($this, 'settings_section_callback'),
            'pllat_date_filter'
        );
        
        add_settings_field(
            'enabled',
            __('Enable Filtering', 'pllat-date-filter'),
            array($this, 'enabled_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'filter_type',
            __('Filter Type', 'pllat-date-filter'),
            array($this, 'filter_type_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'start_date',
            __('Start Date', 'pllat-date-filter'),
            array($this, 'start_date_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'end_date',
            __('End Date', 'pllat-date-filter'),
            array($this, 'end_date_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'date_order',
            __('Date Order', 'pllat-date-filter'),
            array($this, 'date_order_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'post_status',
            __('Post Status', 'pllat-date-filter'),
            array($this, 'post_status_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'untranslated_only',
            __('Untranslated Posts Only', 'pllat-date-filter'),
            array($this, 'untranslated_only_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'pllat-date-filter'),
            array($this, 'debug_mode_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'auto_update_start_date',
            __('Auto Update Start Date', 'pllat-date-filter'),
            array($this, 'auto_update_start_date_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'auto_update_interval',
            __('Update Interval', 'pllat-date-filter'),
            array($this, 'auto_update_interval_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'auto_update_method',
            __('Update Method', 'pllat-date-filter'),
            array($this, 'auto_update_method_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'auto_update_days',
            __('Days to Shift', 'pllat-date-filter'),
            array($this, 'auto_update_days_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
    }
    
    /**
     * Санитизация настроек перед сохранением
     */
    public function sanitize_settings($input) {
        // Санитизируем все поля
        $sanitized = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? 1 : 0;
        $sanitized['filter_type'] = isset($input['filter_type']) && in_array($input['filter_type'], array('from_date', 'date_range')) ? $input['filter_type'] : 'from_date';
        $sanitized['start_date'] = isset($input['start_date']) ? sanitize_text_field($input['start_date']) : '';
        $sanitized['end_date'] = isset($input['end_date']) ? sanitize_text_field($input['end_date']) : '';
        $sanitized['date_order'] = isset($input['date_order']) && in_array(strtoupper($input['date_order']), array('ASC', 'DESC')) ? strtoupper($input['date_order']) : 'ASC';
        $sanitized['post_status'] = isset($input['post_status']) && is_array($input['post_status']) ? array_map('sanitize_text_field', $input['post_status']) : array('publish');
        $sanitized['untranslated_only'] = isset($input['untranslated_only']) ? 1 : 0;
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? 1 : 0;
        $sanitized['auto_update_start_date'] = isset($input['auto_update_start_date']) ? 1 : 0;
        $sanitized['auto_update_interval'] = isset($input['auto_update_interval']) && in_array($input['auto_update_interval'], array('hourly', 'twicedaily', 'daily', 'weekly')) ? $input['auto_update_interval'] : 'daily';
        $sanitized['auto_update_method'] = isset($input['auto_update_method']) && in_array($input['auto_update_method'], array('days_back', 'shift_days')) ? $input['auto_update_method'] : 'days_back';
        $sanitized['auto_update_days'] = isset($input['auto_update_days']) ? max(1, min(365, intval($input['auto_update_days']))) : 7;
        
        return $sanitized;
    }
    
    /**
     * Обновляет cron задачу после сохранения настроек
     */
    public function maybe_update_cron_on_option_save($option_name, $old_value, $value) {
        if ($option_name === $this->option_name) {
            // Очищаем кэш перед обновлением cron
            $this->clear_options_cache();
            $this->maybe_schedule_auto_update();
        }
    }
    
    /**
     * Описание секции настроек
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure date filtering for posts processed by Polylang Automatic AI Translation plugin.', 'pllat-date-filter') . '</p>';
    }
    
    /**
     * Поле включения/выключения фильтрации
     */
    public function enabled_render() {
        $options = $this->get_options();
        $enabled = isset($options['enabled']) ? $options['enabled'] : 0;
        ?>
        <input type='checkbox' name='<?php echo $this->option_name; ?>[enabled]' value='1' <?php checked($enabled, 1); ?>>
        <label><?php _e('Enable date filtering for posts', 'pllat-date-filter'); ?></label>
        <?php
    }
    
    /**
     * Поле выбора типа фильтрации
     */
    public function filter_type_render() {
        $options = $this->get_options();
        $filter_type = isset($options['filter_type']) ? $options['filter_type'] : 'from_date';
        ?>
        <select name='<?php echo $this->option_name; ?>[filter_type]' id='filter_type'>
            <option value='from_date' <?php selected($filter_type, 'from_date'); ?>><?php _e('From specific date', 'pllat-date-filter'); ?></option>
            <option value='date_range' <?php selected($filter_type, 'date_range'); ?>><?php _e('Date range', 'pllat-date-filter'); ?></option>
        </select>
        <p class="description"><?php _e('Choose filter type: from specific date or within date range', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле начальной даты
     */
    public function start_date_render() {
        $options = $this->get_options();
        $start_date = isset($options['start_date']) ? $options['start_date'] : '';
        ?>
        <input type='date' name='<?php echo $this->option_name; ?>[start_date]' value='<?php echo esc_attr($start_date); ?>' id='start_date' required>
        <p class="description"><?php _e('Start date for filtering (inclusive)', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле конечной даты
     */
    public function end_date_render() {
        $options = $this->get_options();
        $end_date = isset($options['end_date']) ? $options['end_date'] : '';
        ?>
        <input type='date' name='<?php echo $this->option_name; ?>[end_date]' value='<?php echo esc_attr($end_date); ?>' id='end_date'>
        <p class="description"><?php _e('End date for filtering (inclusive). Used only with "Date range" option', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле порядка сортировки по дате
     */
    public function date_order_render() {
        $options = $this->get_options();
        $date_order = isset($options['date_order']) ? $options['date_order'] : 'ASC';
        ?>
        <select name='<?php echo $this->option_name; ?>[date_order]' id='date_order'>
            <option value='ASC' <?php selected($date_order, 'ASC'); ?>><?php _e('Ascending (oldest first)', 'pllat-date-filter'); ?></option>
            <option value='DESC' <?php selected($date_order, 'DESC'); ?>><?php _e('Descending (newest first)', 'pllat-date-filter'); ?></option>
        </select>
        <p class="description"><?php _e('Order of posts by publication date', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле выбора статуса постов
     */
    public function post_status_render() {
        $options = $this->get_options();
        $post_status = isset($options['post_status']) ? $options['post_status'] : array('publish');
        
        // Если post_status не массив, делаем его массивом для обратной совместимости
        if (!is_array($post_status)) {
            $post_status = array($post_status);
        }
        
        $available_statuses = array(
            'publish' => __('Published', 'pllat-date-filter'),
            'draft' => __('Draft', 'pllat-date-filter'),
            'pending' => __('Pending Review', 'pllat-date-filter'),
            'private' => __('Private', 'pllat-date-filter'),
            'future' => __('Scheduled', 'pllat-date-filter'),
            'trash' => __('Trash', 'pllat-date-filter')
        );
        ?>
        <fieldset>
            <?php foreach ($available_statuses as $status => $label): ?>
                <label>
                    <input type='checkbox' 
                           name='<?php echo $this->option_name; ?>[post_status][]' 
                           value='<?php echo esc_attr($status); ?>' 
                           <?php checked(in_array($status, $post_status)); ?>>
                    <?php echo esc_html($label); ?>
                </label><br>
            <?php endforeach; ?>
        </fieldset>
        <p class="description"><?php _e('Select which post statuses to include in filtering. At least one status must be selected.', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле выбора только непереведенных записей
     */
    public function untranslated_only_render() {
        $options = $this->get_options();
        $untranslated_only = isset($options['untranslated_only']) ? $options['untranslated_only'] : 0;
        ?>
        <input type='checkbox' name='<?php echo $this->option_name; ?>[untranslated_only]' value='1' <?php checked($untranslated_only, 1); ?>>
        <label><?php _e('Only untranslated posts', 'pllat-date-filter'); ?></label>
        <p class="description"><?php _e('If checked, only posts that have not been translated will be processed.', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле отладки
     */
    public function debug_mode_render() {
        $options = $this->get_options();
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 0;
        ?>
        <input type='checkbox' name='<?php echo $this->option_name; ?>[debug_mode]' value='1' <?php checked($debug_mode, 1); ?>>
        <label><?php _e('Enable debug mode', 'pllat-date-filter'); ?></label>
        <p class="description"><?php _e('When enabled, detailed logs will be written to the WordPress debug log file.', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле автоматической смены начальной даты
     */
    public function auto_update_start_date_render() {
        $options = $this->get_options();
        $auto_update = isset($options['auto_update_start_date']) ? $options['auto_update_start_date'] : 0;
        ?>
        <input type='checkbox' name='<?php echo $this->option_name; ?>[auto_update_start_date]' value='1' id='auto_update_start_date' <?php checked($auto_update, 1); ?>>
        <label><?php _e('Enable automatic start date update', 'pllat-date-filter'); ?></label>
        <p class="description"><?php _e('Automatically update the start date on a schedule to prevent exceeding Bulk Size limit. This ensures new posts are always included in translation queue.', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле интервала обновления
     */
    public function auto_update_interval_render() {
        $options = $this->get_options();
        $interval = isset($options['auto_update_interval']) ? $options['auto_update_interval'] : 'daily';
        ?>
        <select name='<?php echo $this->option_name; ?>[auto_update_interval]' id='auto_update_interval'>
            <option value='hourly' <?php selected($interval, 'hourly'); ?>><?php _e('Hourly', 'pllat-date-filter'); ?></option>
            <option value='twicedaily' <?php selected($interval, 'twicedaily'); ?>><?php _e('Twice Daily', 'pllat-date-filter'); ?></option>
            <option value='daily' <?php selected($interval, 'daily'); ?>><?php _e('Daily', 'pllat-date-filter'); ?></option>
            <option value='weekly' <?php selected($interval, 'weekly'); ?>><?php _e('Weekly', 'pllat-date-filter'); ?></option>
        </select>
        <p class="description"><?php _e('How often to update the start date automatically', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле метода обновления
     */
    public function auto_update_method_render() {
        $options = $this->get_options();
        $method = isset($options['auto_update_method']) ? $options['auto_update_method'] : 'days_back';
        ?>
        <select name='<?php echo $this->option_name; ?>[auto_update_method]' id='auto_update_method'>
            <option value='days_back' <?php selected($method, 'days_back'); ?>><?php _e('Set to today minus N days', 'pllat-date-filter'); ?></option>
            <option value='shift_days' <?php selected($method, 'shift_days'); ?>><?php _e('Shift current date forward by N days', 'pllat-date-filter'); ?></option>
        </select>
        <p class="description"><?php _e('Method for calculating the new start date', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Поле количества дней для сдвига
     */
    public function auto_update_days_render() {
        $options = $this->get_options();
        $days = isset($options['auto_update_days']) ? intval($options['auto_update_days']) : 7;
        ?>
        <input type='number' name='<?php echo $this->option_name; ?>[auto_update_days]' value='<?php echo esc_attr($days); ?>' id='auto_update_days' min='1' max='365' step='1'>
        <p class="description"><?php _e('Number of days to use for date calculation (1-365)', 'pllat-date-filter'); ?></p>
        <?php
    }
    
    /**
     * Страница настроек
     */
    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('PLLAT Date Filter - Settings', 'pllat-date-filter'); ?></h1>
            
            <?php
            // Показываем сообщения о сохранении
            if (isset($_GET['settings-updated'])) {
                add_settings_error('pllat_date_filter_messages', 'pllat_date_filter_message', __('Settings saved', 'pllat-date-filter'), 'updated');
            }
            settings_errors('pllat_date_filter_messages');
            ?>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e('Filter Settings', 'pllat-date-filter'); ?></span></h2>
                                <div class="inside">
                                    <form action='options.php' method='post'>
                                        <?php
                                        settings_fields('pllat_date_filter');
                                        do_settings_sections('pllat_date_filter');
                                        submit_button(__('Save Settings', 'pllat-date-filter'));
                                        ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e('Information', 'pllat-date-filter'); ?></span></h2>
                                <div class="inside">
                                    <p><strong><?php _e('How it works:', 'pllat-date-filter'); ?></strong></p>
                                    <ul>
                                        <li><?php _e('Plugin filters posts when Polylang Automatic AI Translation runs', 'pllat-date-filter'); ?></li>
                                        <li><?php _e('"From specific date" - processes posts published on or after specified date', 'pllat-date-filter'); ?></li>
                                        <li><?php _e('"Date range" - processes posts published between two specific dates', 'pllat-date-filter'); ?></li>
                                        <li><?php _e('Date order controls the sequence of post processing', 'pllat-date-filter'); ?></li>
                                        <li><?php _e('Post status filter allows targeting specific post types by their publication status', 'pllat-date-filter'); ?></li>
                                        <li><?php _e('Filtering is applied automatically when enabled', 'pllat-date-filter'); ?></li>
                                    </ul>
                                    
                                    <p><strong><?php _e('Current settings:', 'pllat-date-filter'); ?></strong></p>
                                    <?php
                                    $options = $this->get_options();
                                    $this->display_current_settings($options);
                                    ?>
                                </div>
                            </div>
                            
                            <div class="postbox">
                                <h2 class="hndle"><span><?php _e('Testing', 'pllat-date-filter'); ?></span></h2>
                                <div class="inside">
                                    <p><?php _e('To check if filter is working, look at WordPress error log. When filter is applied, you will see entries like:', 'pllat-date-filter'); ?></p>
                                    <code>PLLAT DATE FILTER: Applied date filter</code>
                                    
                                    <p><strong><?php _e('Settings location:', 'pllat-date-filter'); ?></strong></p>
                                    <?php if ($this->is_polylang_active()): ?>
                                        <p><?php printf(__('Settings are located in %s menu', 'pllat-date-filter'), '<strong>' . __('Languages → Date Filter', 'pllat-date-filter') . '</strong>'); ?></p>
                                    <?php else: ?>
                                        <p><?php printf(__('Settings are located in %s menu', 'pllat-date-filter'), '<strong>' . __('Settings → PLLAT Date Filter', 'pllat-date-filter') . '</strong>'); ?></p>
                                        <p><em><?php _e('Note: After activating Polylang, settings will move to "Languages" menu', 'pllat-date-filter'); ?></em></p>
                                    <?php endif; ?>
                                    
                                    <p><strong><?php _e('Log location:', 'pllat-date-filter'); ?></strong></p>
                                    <code>/wp-content/debug.log</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Отображение текущих настроек
     */
    private function display_current_settings($options) {
        $enabled = isset($options['enabled']) ? $options['enabled'] : 0;
        $filter_type = isset($options['filter_type']) ? $options['filter_type'] : 'from_date';
        $start_date = isset($options['start_date']) ? $options['start_date'] : '';
        $end_date = isset($options['end_date']) ? $options['end_date'] : '';
        $date_order = isset($options['date_order']) ? $options['date_order'] : 'ASC';
        $post_status = isset($options['post_status']) ? $options['post_status'] : array('publish');
        $untranslated_only = isset($options['untranslated_only']) ? $options['untranslated_only'] : 0;
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 0;
        
        // Обеспечиваем что post_status это массив
        if (!is_array($post_status)) {
            $post_status = array($post_status);
        }
        
        echo '<ul>';
        echo '<li><strong>' . __('Status:', 'pllat-date-filter') . '</strong> ' . ($enabled ? __('Enabled', 'pllat-date-filter') : __('Disabled', 'pllat-date-filter')) . '</li>';
        echo '<li><strong>' . __('Type:', 'pllat-date-filter') . '</strong> ' . ($filter_type === 'from_date' ? __('From specific date', 'pllat-date-filter') : __('Date range', 'pllat-date-filter')) . '</li>';
        
        if ($start_date) {
            echo '<li><strong>' . __('Start date:', 'pllat-date-filter') . '</strong> ' . esc_html($start_date) . '</li>';
        }
        
        if ($filter_type === 'date_range' && $end_date) {
            echo '<li><strong>' . __('End date:', 'pllat-date-filter') . '</strong> ' . esc_html($end_date) . '</li>';
        }
        
        echo '<li><strong>' . __('Date order:', 'pllat-date-filter') . '</strong> ' . ($date_order === 'ASC' ? __('Ascending (oldest first)', 'pllat-date-filter') : __('Descending (newest first)', 'pllat-date-filter')) . '</li>';
        
        // Отображаем выбранные статусы постов
        $status_labels = array(
            'publish' => __('Published', 'pllat-date-filter'),
            'draft' => __('Draft', 'pllat-date-filter'),
            'pending' => __('Pending Review', 'pllat-date-filter'),
            'private' => __('Private', 'pllat-date-filter'),
            'future' => __('Scheduled', 'pllat-date-filter'),
            'trash' => __('Trash', 'pllat-date-filter')
        );
        
        $selected_labels = array();
        foreach ($post_status as $status) {
            if (isset($status_labels[$status])) {
                $selected_labels[] = $status_labels[$status];
            }
        }
        
        echo '<li><strong>' . __('Post statuses:', 'pllat-date-filter') . '</strong> ' . esc_html(implode(', ', $selected_labels)) . '</li>';
        echo '<li><strong>' . __('Untranslated only:', 'pllat-date-filter') . '</strong> ' . ($untranslated_only ? __('Yes', 'pllat-date-filter') : __('No', 'pllat-date-filter')) . '</li>';
        echo '<li><strong>' . __('Debug mode:', 'pllat-date-filter') . '</strong> ' . ($debug_mode ? __('Enabled', 'pllat-date-filter') : __('Disabled', 'pllat-date-filter')) . '</li>';
        
        // Отображаем настройки автоматического обновления
        $auto_update = isset($options['auto_update_start_date']) ? $options['auto_update_start_date'] : 0;
        if ($auto_update) {
            $interval = isset($options['auto_update_interval']) ? $options['auto_update_interval'] : 'daily';
            $method = isset($options['auto_update_method']) ? $options['auto_update_method'] : 'days_back';
            $days = isset($options['auto_update_days']) ? intval($options['auto_update_days']) : 7;
            
            $interval_labels = array(
                'hourly' => __('Hourly', 'pllat-date-filter'),
                'twicedaily' => __('Twice Daily', 'pllat-date-filter'),
                'daily' => __('Daily', 'pllat-date-filter'),
                'weekly' => __('Weekly', 'pllat-date-filter')
            );
            
            $method_labels = array(
                'days_back' => __('Set to today minus N days', 'pllat-date-filter'),
                'shift_days' => __('Shift current date forward by N days', 'pllat-date-filter')
            );
            
            echo '<li><strong>' . __('Auto update:', 'pllat-date-filter') . '</strong> ' . __('Enabled', 'pllat-date-filter') . '</li>';
            echo '<li><strong>' . __('Update interval:', 'pllat-date-filter') . '</strong> ' . (isset($interval_labels[$interval]) ? $interval_labels[$interval] : $interval) . '</li>';
            echo '<li><strong>' . __('Update method:', 'pllat-date-filter') . '</strong> ' . (isset($method_labels[$method]) ? $method_labels[$method] : $method) . '</li>';
            echo '<li><strong>' . __('Days:', 'pllat-date-filter') . '</strong> ' . $days . '</li>';
            
            // Показываем следующее запланированное обновление
            $next_scheduled = wp_next_scheduled('pllat_date_filter_auto_update');
            if ($next_scheduled) {
                $next_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled);
                echo '<li><strong>' . __('Next update:', 'pllat-date-filter') . '</strong> ' . $next_date . '</li>';
            }
        } else {
            echo '<li><strong>' . __('Auto update:', 'pllat-date-filter') . '</strong> ' . __('Disabled', 'pllat-date-filter') . '</li>';
        }
        
        echo '</ul>';
    }
    
    /**
     * Подключаем JS для админки
     */
    public function enqueue_admin_scripts($hook) {
        $polylang_hook = $this->is_polylang_active() ? 'polylang_page_pllat-date-filter' : 'settings_page_pllat-date-filter';
        
        if ($polylang_hook !== $hook) {
            return;
        }
        
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                function toggleEndDate() {
                    var filterType = $('#filter_type').val();
                    var endDateRow = $('#end_date').closest('tr');
                    
                    if (filterType === 'date_range') {
                        endDateRow.show();
                        $('#end_date').prop('required', true);
                    } else {
                        endDateRow.hide();
                        $('#end_date').prop('required', false);
                    }
                }
                
                function toggleAutoUpdateFields() {
                    var autoUpdateEnabled = $('#auto_update_start_date').is(':checked');
                    var intervalRow = $('#auto_update_interval').closest('tr');
                    var methodRow = $('#auto_update_method').closest('tr');
                    var daysRow = $('#auto_update_days').closest('tr');
                    
                    if (autoUpdateEnabled) {
                        intervalRow.show();
                        methodRow.show();
                        daysRow.show();
                    } else {
                        intervalRow.hide();
                        methodRow.hide();
                        daysRow.hide();
                    }
                }
                
                $('#filter_type').change(toggleEndDate);
                $('#auto_update_start_date').change(toggleAutoUpdateFields);
                
                toggleEndDate(); // Запускаем при загрузке
                toggleAutoUpdateFields(); // Запускаем при загрузке
                
                // Проверяем что выбран хотя бы один статус поста
                function validatePostStatus() {
                    var checkedBoxes = $('input[name=\"pllat_date_filter_settings[post_status][]\"]:checked');
                    if (checkedBoxes.length === 0) {
                        alert('" . esc_js(__('Please select at least one post status.', 'pllat-date-filter')) . "');
                        return false;
                    }
                    return true;
                }
                
                $('form').submit(function(e) {
                    if (!validatePostStatus()) {
                        e.preventDefault();
                    }
                });
            });
        ");
    }
    
    /**
     * Применяем фильтры к WP_Query до генерации SQL
     */
    public function maybe_apply_filters($query) {
        // Убедимся, что это именно WP_Query
        if (!($query instanceof \WP_Query)) {
            return;
        }
        
        // Ранний выход: проверяем, не применены ли уже фильтры к этому запросу
        $query_id = spl_object_hash($query);
        if (isset(self::$applied_filters[$query_id])) {
            return;
        }
        
        // Ранний выход: проверяем, что запрос не пустой
        // Это предотвращает обработку пустых или некорректных запросов
        if ($query->get('post_type') === null && $query->get('fields') === null && empty($query->get('meta_query'))) {
            // Это может быть пустой запрос, пропускаем его
            return;
        }

        // Настройки (используем кэшированный метод)
        $options = $this->get_options();
        if (empty($options) || empty($options['enabled'])) {
            return;
        }

        // Должны быть признаки запроса сборщика постов из основного плагина
        // Эта проверка должна быть достаточно строгой, чтобы не затронуть обычные запросы
        $is_pllat_query = $this->is_pllat_translation_query($query);
        
        // Логирование для отладки (если включен debug_mode)
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 0;
        if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
            $meta_query = $query->get('meta_query');
            $tax_query = $query->get('tax_query');
            error_log('PLLAT DATE FILTER: Query check. is_pllat=' . ($is_pllat_query ? 'true' : 'false') . 
                     ', is_admin=' . (is_admin() ? 'true' : 'false') . 
                     ', is_ajax=' . (wp_doing_ajax() ? 'true' : 'false') . 
                     ', is_main_query=' . (is_main_query() ? 'true' : 'false') .
                     ', has_meta=' . (is_array($meta_query) ? 'yes' : 'no') .
                     ', has_tax=' . (is_array($tax_query) ? 'yes' : 'no'));
        }
        
        if (!$is_pllat_query) {
            return;
        }

        $filter_type = $options['filter_type'] ?? 'from_date';
        $start_date  = $options['start_date'] ?? '';
        $end_date    = $options['end_date'] ?? '';
        $date_order  = strtoupper($options['date_order'] ?? 'ASC');
        $post_status = $options['post_status'] ?? array('publish');
        $untranslated_only = isset($options['untranslated_only']) ? $options['untranslated_only'] : 0;
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 0;

        // Нужна хотя бы стартовая дата
        if (empty($start_date)) {
            return;
        }

        // Лог перед применением
        if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PLLAT DATE FILTER: detected translation query. type=' . $filter_type . ', start=' . $start_date . ', end=' . $end_date . ', order=' . $date_order . ', statuses=' . (is_array($post_status) ? implode(',', $post_status) : (string) $post_status) . ', untranslated_only=' . $untranslated_only);
        }

        try {
            // Устанавливаем статус, если явно задан (не 'any')
            if (!empty($post_status) && !(is_array($post_status) && in_array('any', $post_status, true)) && $post_status !== 'any') {
                $query->set('post_status', $post_status);
            }

            // Устанавливаем сортировку по дате
            // PLLAT по умолчанию использует orderby => 'ID', но мы меняем на дату для фильтрации
            $query->set('orderby', 'date');
            $query->set('order', $date_order === 'DESC' ? 'DESC' : 'ASC');

            // Устанавливаем date_query
            $date_query = array('inclusive' => true);
            if ($filter_type === 'date_range' && !empty($end_date)) {
                $date_query['after']  = $start_date;
                $date_query['before'] = $end_date;
            } else {
                $date_query['after'] = $start_date;
            }
            $query->set('date_query', array($date_query));

            // Применяем фильтр только непереведенных записей, если включен
            if ($untranslated_only) {
                // Создаем простой meta_query для непереведенных записей
                $untranslated_meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_pllat_translation_queue',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_pllat_translation_queue',
                        'value' => '',
                        'compare' => '='
                    )
                );
                
                // Получаем текущий meta_query и объединяем с новым
                $current_meta_query = $query->get('meta_query');
                if (is_array($current_meta_query) && !empty($current_meta_query)) {
                    // Если уже есть meta_query, объединяем их
                    $query->set('meta_query', array(
                        'relation' => 'AND',
                        $current_meta_query,
                        $untranslated_meta_query
                    ));
                } else {
                    // Если meta_query пустой, устанавливаем только наш
                    $query->set('meta_query', $untranslated_meta_query);
                }
            }

            // НЕ изменяем posts_per_page и numberposts - плагин сам управляет bulk size
            // PLLAT использует numberposts для ограничения количества записей
            
            // Устанавливаем no_found_rows для оптимизации запросов PLLAT
            // Это безопасно, так как мы уже проверили, что это запрос PLLAT
            // Но только если он еще не установлен
            if ($query->get('no_found_rows') === null) {
                $query->set('no_found_rows', true); // Отключаем подсчет общего количества
            }

            // Помечаем запрос как обработанный
            self::$applied_filters[$query_id] = true;
            
            if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PLLAT DATE FILTER: applied filters successfully. date_query=' . json_encode($query->get('date_query')) . ', post_status=' . json_encode($query->get('post_status')) . ', order=' . $query->get('order') . ', untranslated_only=' . $untranslated_only . ', posts_per_page=' . $query->get('posts_per_page'));
            }
            
        } catch (Exception $e) {
            // Логируем ошибки для отладки
            if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PLLAT DATE FILTER ERROR: ' . $e->getMessage());
            }
            // В случае ошибки не применяем фильтры
            return;
        }
    }

    /**
     * Определяем, что это запрос сбора постов для перевода в PLLAT
     * На основе анализа кода Bulk_Post_Manager.php
     * Оптимизировано для предотвращения утечек памяти
     */
    private function is_pllat_translation_query(\WP_Query $query): bool {
        // Основной признак PLLAT: наличие meta_query с ключом _pllat_exclude_from_translation
        // Это используется в Bulk_Post_Manager::get_all_ids() и get_all_ids_with_queue()
        $meta_query = $query->get('meta_query');
        
        if (is_array($meta_query)) {
            // Используем итеративный подход вместо рекурсии для предотвращения утечек памяти
            $stack = array($meta_query);
            
            while (!empty($stack)) {
                $current = array_pop($stack);
                
                if (!is_array($current)) {
                    continue;
                }
                
                foreach ($current as $item) {
                    if (is_array($item)) {
                        // Проверяем наличие ключа _pllat_exclude_from_translation
                        // PLLAT использует его с compare => 'NOT EXISTS' или 'EXISTS'
                        if (isset($item['key']) && $item['key'] === '_pllat_exclude_from_translation') {
                            return true; // Ранний выход при нахождении
                        }
                        // Добавляем вложенные массивы в стек для обработки
                        $stack[] = $item;
                    }
                }
            }
        }
        
        // Дополнительные признаки PLLAT запроса (из анализа Bulk_Post_Manager.php):
        // - fields => 'ids' (PLLAT запрашивает только ID)
        // - numberposts установлен (bulk size)
        // - lang параметр установлен (Polylang язык)
        // - orderby => 'ID' и order => 'ASC'
        
        $fields = $query->get('fields');
        $numberposts = $query->get('numberposts');
        $lang = $query->get('lang');
        $orderby = $query->get('orderby');
        $order = $query->get('order');
        
        // Проверяем комбинацию признаков PLLAT
        $has_pllat_signs = false;
        
        // Если fields = 'ids' И numberposts установлен И lang установлен
        if ($fields === 'ids' && !empty($numberposts) && !empty($lang)) {
            // Дополнительная проверка: orderby должен быть 'ID'
            if ($orderby === 'ID' || (is_array($orderby) && isset($orderby['ID']))) {
                $has_pllat_signs = true;
            }
        }
        
        // Если есть признаки PLLAT, но это не основной запрос на фронтенде
        if ($has_pllat_signs) {
            // Защита от применения к обычным запросам на фронтенде
            if (is_main_query() && !is_admin() && !wp_doing_ajax()) {
                return false;
            }
            return true;
        }
        
        return false;
    }
    
    /**
     * Применяем фильтр даты через SQL WHERE (резервный метод)
     * Используется если pre_get_posts был удален через remove_all_filters
     */
    public function maybe_apply_date_filter_where($where, $query) {
        // Проверяем, что это WP_Query
        if (!($query instanceof \WP_Query)) {
            return $where;
        }
        
        // Ранний выход: проверяем, не применены ли уже фильтры
        $query_id = spl_object_hash($query);
        if (isset(self::$applied_filters[$query_id])) {
            return $where;
        }
        
        // Проверяем настройки (используем кэшированный метод)
        $options = $this->get_options();
        if (empty($options) || empty($options['enabled'])) {
            return $where;
        }
        
        // Проверяем, что это PLLAT запрос
        if (!$this->is_pllat_translation_query($query)) {
            return $where;
        }
        
        $start_date = $options['start_date'] ?? '';
        if (empty($start_date)) {
            return $where;
        }
        
        // Проверяем, не применен ли уже фильтр через pre_get_posts
        $date_query = $query->get('date_query');
        if (!empty($date_query)) {
            // Фильтр уже применен через pre_get_posts
            self::$applied_filters[$query_id] = true;
            return $where;
        }
        
        // Применяем фильтр через SQL
        // Используем глобальную переменную только один раз
        global $wpdb;
        $filter_type = $options['filter_type'] ?? 'from_date';
        $end_date = $options['end_date'] ?? '';
        
        // Безопасная подготовка дат
        $start_datetime = $start_date . ' 00:00:00';
        $date_condition = '';
        
        if ($filter_type === 'date_range' && !empty($end_date)) {
            $end_datetime = $end_date . ' 23:59:59';
            $date_condition = $wpdb->prepare(
                " AND {$wpdb->posts}.post_date >= %s AND {$wpdb->posts}.post_date <= %s",
                $start_datetime,
                $end_datetime
            );
        } else {
            $date_condition = $wpdb->prepare(
                " AND {$wpdb->posts}.post_date >= %s",
                $start_datetime
            );
        }
        
        // Помечаем запрос как обработанный
        self::$applied_filters[$query_id] = true;
        
        return $where . $date_condition;
    }
    
    /**
     * Применяем сортировку по дате через SQL ORDER BY (резервный метод)
     */
    public function maybe_apply_date_filter_orderby($orderby, $query) {
        // Проверяем, что это WP_Query
        if (!($query instanceof \WP_Query)) {
            return $orderby;
        }
        
        // Ранний выход: если фильтры уже применены, сортировка тоже должна быть применена
        $query_id = spl_object_hash($query);
        if (isset(self::$applied_filters[$query_id])) {
            // Проверяем, не применена ли уже сортировка через pre_get_posts
            $current_orderby = $query->get('orderby');
            if ($current_orderby === 'date') {
                return $orderby;
            }
        }
        
        // Проверяем настройки (используем кэшированный метод)
        $options = $this->get_options();
        if (empty($options) || empty($options['enabled'])) {
            return $orderby;
        }
        
        // Проверяем, что это PLLAT запрос
        if (!$this->is_pllat_translation_query($query)) {
            return $orderby;
        }
        
        // Проверяем, не применена ли уже сортировка через pre_get_posts
        $current_orderby = $query->get('orderby');
        if ($current_orderby === 'date') {
            // Сортировка уже применена через pre_get_posts
            return $orderby;
        }
        
        // Применяем сортировку по дате
        // Используем глобальную переменную только один раз
        global $wpdb;
        $date_order = strtoupper($options['date_order'] ?? 'ASC');
        $order_direction = ($date_order === 'DESC') ? 'DESC' : 'ASC';
        $date_orderby = "{$wpdb->posts}.post_date {$order_direction}";
        
        // Если уже есть orderby, добавляем нашу сортировку
        if (!empty($orderby) && is_string($orderby)) {
            return $date_orderby . ', ' . $orderby;
        }
        
        return $date_orderby;
    }
    
    /**
     * Добавляем ссылку на настройки в список плагинов
     */
    public function add_settings_link($links) {
        if ($this->is_polylang_active()) {
            $settings_link = '<a href="admin.php?page=pllat-date-filter">' . __('Settings', 'pllat-date-filter') . '</a>';
        } else {
            $settings_link = '<a href="options-general.php?page=pllat-date-filter">' . __('Settings', 'pllat-date-filter') . '</a>';
        }
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Уведомление если Polylang не активен
     */
    public function polylang_notice() {
        if (!$this->is_polylang_active() && current_user_can('manage_options')) {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'pllat-date-filter') !== false) {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Warning:', 'pllat-date-filter'); ?></strong> 
                        <?php _e('Polylang plugin not detected. This plugin is designed to work with Polylang Automatic AI Translation.', 'pllat-date-filter'); ?>
                        <?php _e('Settings are located in the "Settings" section instead of Polylang menu.', 'pllat-date-filter'); ?>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Проверяет настройки и регистрирует/удаляет cron задачу для автоматической смены даты
     */
    public function maybe_schedule_auto_update() {
        $options = $this->get_options();
        
        if (empty($options) || empty($options['auto_update_start_date'])) {
            // Если автоматическое обновление отключено, удаляем cron задачу
            $timestamp = wp_next_scheduled('pllat_date_filter_auto_update');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'pllat_date_filter_auto_update');
            }
            return;
        }
        
        // Получаем интервал обновления
        $interval = isset($options['auto_update_interval']) ? $options['auto_update_interval'] : 'daily';
        
        // Проверяем, зарегистрирована ли уже cron задача
        if (!wp_next_scheduled('pllat_date_filter_auto_update')) {
            // Регистрируем cron задачу
            wp_schedule_event(time(), $interval, 'pllat_date_filter_auto_update');
        } else {
            // Проверяем, изменился ли интервал
            $scheduled = wp_get_scheduled_event('pllat_date_filter_auto_update');
            if ($scheduled && $scheduled->schedule !== $interval) {
                // Удаляем старую задачу и создаем новую с новым интервалом
                wp_unschedule_event($scheduled->timestamp, 'pllat_date_filter_auto_update');
                wp_schedule_event(time(), $interval, 'pllat_date_filter_auto_update');
            }
        }
    }
    
    /**
     * Автоматически обновляет начальную дату согласно настройкам
     */
    public function auto_update_start_date() {
        $options = $this->get_options();
        
        // Проверяем, включено ли автоматическое обновление
        if (empty($options) || empty($options['auto_update_start_date'])) {
            return;
        }
        
        // Проверяем, что фильтр включен и есть начальная дата
        if (empty($options['enabled']) || empty($options['start_date'])) {
            return;
        }
        
        // Получаем параметры обновления
        $method = isset($options['auto_update_method']) ? $options['auto_update_method'] : 'days_back';
        $days = isset($options['auto_update_days']) ? intval($options['auto_update_days']) : 7;
        $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 0;
        
        // Сохраняем текущую дату для логирования
        $old_start_date = $options['start_date'];
        
        // Вычисляем новую дату
        $new_start_date = '';
        
        if ($method === 'days_back') {
            // Устанавливаем дату на сегодня минус N дней
            $new_start_date = date('Y-m-d', strtotime("-{$days} days"));
        } else {
            // Сдвигаем текущую дату вперед на N дней
            $current_start_date = $options['start_date'];
            $new_start_date = date('Y-m-d', strtotime($current_start_date . " +{$days} days"));
            
            // Не позволяем установить дату в будущем
            $today = date('Y-m-d');
            if ($new_start_date > $today) {
                $new_start_date = $today;
            }
        }
        
        // Обновляем настройки
        $options['start_date'] = $new_start_date;
        update_option($this->option_name, $options);
        
        // Очищаем кэш после обновления
        $this->clear_options_cache();
        
        // Логируем обновление
        if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'PLLAT DATE FILTER: Auto-updated start date from %s to %s (method: %s, days: %d)',
                $old_start_date,
                $new_start_date,
                $method,
                $days
            ));
        }
    }
}

// Инициализируем плагин
new PLLAT_Date_Filter();

/**
 * Функция активации плагина
 */
function pllat_date_filter_activate() {
    // Устанавливаем настройки по умолчанию
    $default_options = array(
        'enabled' => 0,
        'filter_type' => 'from_date',
        'start_date' => '',
        'end_date' => '',
        'date_order' => 'ASC',
        'post_status' => array('publish'),
        'untranslated_only' => 0,
        'debug_mode' => 0,
        'auto_update_start_date' => 0,
        'auto_update_interval' => 'daily',
        'auto_update_method' => 'days_back',
        'auto_update_days' => 7
    );
    
    add_option('pllat_date_filter_settings', $default_options);
    
    // Регистрируем cron задачу, если автоматическое обновление включено
    // Используем прямой вызов get_option здесь, так как кэш еще не инициализирован
    $options = get_option('pllat_date_filter_settings');
    if (!empty($options['auto_update_start_date'])) {
        $interval = isset($options['auto_update_interval']) ? $options['auto_update_interval'] : 'daily';
        if (!wp_next_scheduled('pllat_date_filter_auto_update')) {
            wp_schedule_event(time(), $interval, 'pllat_date_filter_auto_update');
        }
    }
}
register_activation_hook(__FILE__, 'pllat_date_filter_activate');

/**
 * Функция деактивации плагина
 */
function pllat_date_filter_deactivate() {
    // Удаляем cron задачу при деактивации
    $timestamp = wp_next_scheduled('pllat_date_filter_auto_update');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pllat_date_filter_auto_update');
    }
    
    // При желании можно удалить настройки
    // delete_option('pllat_date_filter_settings');
}
register_deactivation_hook(__FILE__, 'pllat_date_filter_deactivate');