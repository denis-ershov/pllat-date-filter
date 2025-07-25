<?php
/**
 * Plugin Name: PLLAT Date Filter
 * Plugin URI: https://github.com/denis-ershov/pllat-date-filter
 * Description: Date filtering functionality for Polylang Automatic AI Translation. Filter posts by date range or from specific date when running bulk translations.
 * Version: 1.1.0
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
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_admin_menu'), 99); // Поздний приоритет чтобы Polylang успел загрузиться
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('posts_where', array($this, 'filter_posts_by_date'), 10, 2);
        add_filter('posts_orderby', array($this, 'filter_posts_order'), 10, 2);
        
        // Добавляем ссылку на настройки в список плагинов
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Добавляем уведомление если Polylang не активен
        add_action('admin_notices', array($this, 'polylang_notice'));
    }
    
    /**
     * Загружаем файлы переводов
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'pllat-date-filter',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
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
        register_setting('pllat_date_filter', $this->option_name);
        
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
        $options = get_option($this->option_name);
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
        $options = get_option($this->option_name);
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
        $options = get_option($this->option_name);
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
        $options = get_option($this->option_name);
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
        $options = get_option($this->option_name);
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
        $options = get_option($this->option_name);
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
                                    $options = get_option($this->option_name);
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
                
                $('#filter_type').change(toggleEndDate);
                toggleEndDate(); // Запускаем при загрузке
                
                // Проверяем что выбран хотя бы один статус поста
                function validatePostStatus() {
                    var checkedBoxes = $('input[name=\"pllat_date_filter_settings[post_status][]\"]input:checked');
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
     * Основная функция фильтрации
     */
    public function filter_posts_by_date($where, $query) {
        global $wpdb;
        
        // Получаем настройки
        $options = get_option($this->option_name);
        
        // Проверяем, включена ли фильтрация
        if (!isset($options['enabled']) || !$options['enabled']) {
            return $where;
        }
        
        // Проверяем, что это SQL запрос содержит таблицу постов
        if (strpos($where, $wpdb->posts) === false) {
            return $where;
        }
        
        // Проверяем, что это запрос плагина перевода
        if (strpos($where, '_pllat_exclude_from_translation') === false && 
            strpos($where, '_pllat_translation_queue') === false) {
            return $where;
        }
        
        $filter_type = isset($options['filter_type']) ? $options['filter_type'] : 'from_date';
        $start_date = isset($options['start_date']) ? $options['start_date'] : '';
        $post_status = isset($options['post_status']) ? $options['post_status'] : array('publish');
        
        if (empty($start_date)) {
            return $where;
        }
        
        // Обеспечиваем что post_status это массив
        if (!is_array($post_status)) {
            $post_status = array($post_status);
        }
        
        // Добавляем фильтр по статусу постов
        if (!empty($post_status)) {
            $status_placeholders = implode(',', array_fill(0, count($post_status), '%s'));
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_status IN ($status_placeholders)", $post_status);
        }
        
        // Применяем фильтр в зависимости от типа
        if ($filter_type === 'from_date') {
            // С определенной даты
            $start_datetime = $start_date . ' 00:00:00';
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_date >= %s", $start_datetime);
            
            error_log('PLLAT DATE FILTER: Applied "from date" filter >= ' . $start_datetime . ' with statuses: ' . implode(', ', $post_status));
            
        } elseif ($filter_type === 'date_range') {
            // В интервале дат
            $end_date = isset($options['end_date']) ? $options['end_date'] : '';
            
            if (!empty($end_date)) {
                $start_datetime = $start_date . ' 00:00:00';
                $end_datetime = $end_date . ' 23:59:59';
                
                $where .= $wpdb->prepare(
                    " AND {$wpdb->posts}.post_date >= %s AND {$wpdb->posts}.post_date <= %s",
                    $start_datetime,
                    $end_datetime
                );
                
                error_log('PLLAT DATE FILTER: Applied "date range" filter: ' . $start_datetime . ' to ' . $end_datetime . ' with statuses: ' . implode(', ', $post_status));
            } else {
                // Если конечная дата не указана, работаем как "с определенной даты"
                $start_datetime = $start_date . ' 00:00:00';
                $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_date >= %s", $start_datetime);
                
                error_log('PLLAT DATE FILTER: Applied "from date" filter (no end date) >= ' . $start_datetime . ' with statuses: ' . implode(', ', $post_status));
            }
        }
        
        return $where;
    }
    
    /**
     * Фильтр для сортировки постов по дате
     */
    public function filter_posts_order($orderby, $query) {
        global $wpdb;
        
        // Получаем настройки
        $options = get_option($this->option_name);
        
        // Проверяем, включена ли фильтрация
        if (!isset($options['enabled']) || !$options['enabled']) {
            return $orderby;
        }
        
        // Проверяем, что это запрос содержит условия нашего фильтра
        // (простая проверка - если в запросе есть наш фильтр дат)
        $where_clause = $query->get('suppress_filters') ? '' : apply_filters('posts_where', '', $query);
        if (strpos($where_clause, 'PLLAT DATE FILTER') === false && 
            (strpos($where_clause, '_pllat_exclude_from_translation') === false && 
             strpos($where_clause, '_pllat_translation_queue') === false)) {
            return $orderby;
        }
        
        $date_order = isset($options['date_order']) ? $options['date_order'] : 'ASC';
        
        // Применяем сортировку по дате
        if (empty($orderby) || strpos($orderby, 'post_date') === false) {
            $orderby = "{$wpdb->posts}.post_date " . $date_order;
        } else {
            // Если уже есть сортировка по post_date, заменяем направление
            $orderby = preg_replace('/post_date\s+(ASC|DESC)/i', 'post_date ' . $date_order, $orderby);
        }
        
        error_log('PLLAT DATE FILTER: Applied date order: ' . $date_order);
        
        return $orderby;
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
        'post_status' => array('publish')
    );
    
    add_option('pllat_date_filter_settings', $default_options);
}
register_activation_hook(__FILE__, 'pllat_date_filter_activate');

/**
 * Функция деактивации плагина
 */
function pllat_date_filter_deactivate() {
    // При желании можно удалить настройки
    // delete_option('pllat_date_filter_settings');
}
register_deactivation_hook(__FILE__, 'pllat_date_filter_deactivate');