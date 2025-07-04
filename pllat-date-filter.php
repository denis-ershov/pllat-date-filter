<?php
/**
 * Plugin Name: PLLAT Date Filter
 * Plugin URI: https://example.com
 * Description: Фильтрация записей по дате для плагина Polylang Automatic AI Translation
 * Version: 1.0.0
 * Author: Denis Ershov
 * License: GPL2
 */

// Предотвращаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

class PLLAT_Date_Filter {
    
    private $option_name = 'pllat_date_filter_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 99); // Поздний приоритет чтобы Polylang успел загрузиться
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('posts_where', array($this, 'filter_posts_by_date'), 10, 2);
        
        // Добавляем ссылку на настройки в список плагинов
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Добавляем уведомление если Polylang не активен
        add_action('admin_notices', array($this, 'polylang_notice'));
    }
    
    /**
     * Добавляем страницу настроек в админку
     */
    public function add_admin_menu() {
        // Пытаемся добавить в меню Polylang, если он активен
        if ($this->is_polylang_active()) {
            add_submenu_page(
                'mlang',                    // parent slug (меню Polylang)
                'Date Filter Settings',     // page title
                'Date Filter',              // menu title
                'manage_options',           // capability
                'pllat-date-filter',        // menu slug
                array($this, 'options_page') // callback
            );
        } else {
            // Если Polylang не активен, добавляем в общие настройки
            add_options_page(
                'PLLAT Date Filter',
                'PLLAT Date Filter',
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
            'Настройки фильтрации по дате',
            array($this, 'settings_section_callback'),
            'pllat_date_filter'
        );
        
        add_settings_field(
            'enabled',
            'Включить фильтрацию',
            array($this, 'enabled_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'filter_type',
            'Тип фильтрации',
            array($this, 'filter_type_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'start_date',
            'Начальная дата',
            array($this, 'start_date_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
        
        add_settings_field(
            'end_date',
            'Конечная дата',
            array($this, 'end_date_render'),
            'pllat_date_filter',
            'pllat_date_filter_section'
        );
    }
    
    /**
     * Описание секции настроек
     */
    public function settings_section_callback() {
        echo '<p>Настройте фильтрацию записей по дате для плагина Polylang Automatic AI Translation.</p>';
    }
    
    /**
     * Поле включения/выключения фильтрации
     */
    public function enabled_render() {
        $options = get_option($this->option_name);
        $enabled = isset($options['enabled']) ? $options['enabled'] : 0;
        ?>
        <input type='checkbox' name='<?php echo $this->option_name; ?>[enabled]' value='1' <?php checked($enabled, 1); ?>>
        <label>Включить фильтрацию записей по дате</label>
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
            <option value='from_date' <?php selected($filter_type, 'from_date'); ?>>С определенной даты</option>
            <option value='date_range' <?php selected($filter_type, 'date_range'); ?>>В интервале дат</option>
        </select>
        <p class="description">Выберите тип фильтрации: с определенной даты или в интервале дат</p>
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
        <p class="description">Начальная дата для фильтрации (включительно)</p>
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
        <p class="description">Конечная дата для фильтрации (включительно). Используется только при выборе "В интервале дат"</p>
        <?php
    }
    
    /**
     * Страница настроек
     */
    public function options_page() {
        ?>
        <div class="wrap">
            <h1>PLLAT Date Filter - Настройки</h1>
            
            <?php
            // Показываем сообщения о сохранении
            if (isset($_GET['settings-updated'])) {
                add_settings_error('pllat_date_filter_messages', 'pllat_date_filter_message', 'Настройки сохранены', 'updated');
            }
            settings_errors('pllat_date_filter_messages');
            ?>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <div class="postbox">
                                <h2 class="hndle"><span>Настройки фильтрации</span></h2>
                                <div class="inside">
                                    <form action='options.php' method='post'>
                                        <?php
                                        settings_fields('pllat_date_filter');
                                        do_settings_sections('pllat_date_filter');
                                        submit_button('Сохранить настройки');
                                        ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="meta-box-sortables">
                            <div class="postbox">
                                <h2 class="hndle"><span>Информация</span></h2>
                                <div class="inside">
                                    <p><strong>Как это работает:</strong></p>
                                    <ul>
                                        <li>Плагин фильтрует записи при работе Polylang Automatic AI Translation</li>
                                        <li>"С определенной даты" - берет записи >= указанной даты</li>
                                        <li>"В интервале дат" - берет записи между двумя датами</li>
                                        <li>Фильтрация применяется автоматически при включении</li>
                                    </ul>
                                    
                                    <p><strong>Текущие настройки:</strong></p>
                                    <?php
                                    $options = get_option($this->option_name);
                                    $this->display_current_settings($options);
                                    ?>
                                </div>
                            </div>
                            
                            <div class="postbox">
                                <h2 class="hndle"><span>Тестирование</span></h2>
                                <div class="inside">
                                    <p>Для проверки работы фильтра посмотрите лог ошибок WordPress. При срабатывании фильтра появятся записи:</p>
                                    <code>PLLAT DATE FILTER: Applied date filter</code>
                                    
                                    <p><strong>Расположение настроек:</strong></p>
                                    <?php if ($this->is_polylang_active()): ?>
                                        <p>Настройки находятся в меню <strong>Языки → Date Filter</strong></p>
                                    <?php else: ?>
                                        <p>Настройки находятся в меню <strong>Настройки → PLLAT Date Filter</strong></p>
                                        <p><em>Примечание: После активации Polylang настройки переместятся в меню "Языки"</em></p>
                                    <?php endif; ?>
                                    
                                    <p><strong>Расположение лога:</strong></p>
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
        
        echo '<ul>';
        echo '<li><strong>Статус:</strong> ' . ($enabled ? 'Включено' : 'Выключено') . '</li>';
        echo '<li><strong>Тип:</strong> ' . ($filter_type === 'from_date' ? 'С определенной даты' : 'В интервале дат') . '</li>';
        
        if ($start_date) {
            echo '<li><strong>Начальная дата:</strong> ' . esc_html($start_date) . '</li>';
        }
        
        if ($filter_type === 'date_range' && $end_date) {
            echo '<li><strong>Конечная дата:</strong> ' . esc_html($end_date) . '</li>';
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
                
                $('#filter_type').change(toggleEndDate);
                toggleEndDate(); // Запускаем при загрузке
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
        
        if (empty($start_date)) {
            return $where;
        }
        
        // Применяем фильтр в зависимости от типа
        if ($filter_type === 'from_date') {
            // С определенной даты
            $start_datetime = $start_date . ' 00:00:00';
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_date >= %s", $start_datetime);
            
            error_log('PLLAT DATE FILTER: Applied "from date" filter >= ' . $start_datetime);
            
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
                
                error_log('PLLAT DATE FILTER: Applied "date range" filter: ' . $start_datetime . ' to ' . $end_datetime);
            } else {
                // Если конечная дата не указана, работаем как "с определенной даты"
                $start_datetime = $start_date . ' 00:00:00';
                $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_date >= %s", $start_datetime);
                
                error_log('PLLAT DATE FILTER: Applied "from date" filter (no end date) >= ' . $start_datetime);
            }
        }
        
        return $where;
    }
    
    /**
     * Добавляем ссылку на настройки в список плагинов
     */
    public function add_settings_link($links) {
        if ($this->is_polylang_active()) {
            $settings_link = '<a href="admin.php?page=pllat-date-filter">Настройки</a>';
        } else {
            $settings_link = '<a href="options-general.php?page=pllat-date-filter">Настройки</a>';
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
                        <strong>Внимание:</strong> Плагин Polylang не обнаружен. 
                        Этот плагин предназначен для работы с Polylang Automatic AI Translation.
                        Настройки находятся в разделе "Настройки" вместо меню Polylang.
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
        'end_date' => ''
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