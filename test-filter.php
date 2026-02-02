<?php
/**
 * Тестовый файл для проверки логики фильтрации
 * Запускать отдельно для отладки
 */

// Имитируем WordPress окружение
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Функция для тестирования логики фильтрации
function test_filter_logic() {
    echo "=== Тест логики фильтрации PLLAT Date Filter ===\n\n";
    
    // Тест 1: Проверка определения PLLAT запроса
    echo "Тест 1: Определение PLLAT запроса\n";
    
    $test_query = new stdClass();
    $test_query->query_vars = array(
        'no_found_rows' => true,
        'meta_query' => array(
            array(
                'key' => '_pllat_exclude_from_translation',
                'value' => '0',
                'compare' => '='
            )
        ),
        'tax_query' => array(
            array(
                'taxonomy' => 'language',
                'field' => 'slug',
                'terms' => 'en'
            )
        )
    );
    
    // Имитируем методы WP_Query
    $test_query->get = function($key) use ($test_query) {
        return $test_query->query_vars[$key] ?? null;
    };
    
    $test_query->set = function($key, $value) use ($test_query) {
        $test_query->query_vars[$key] = $value;
    };
    
    echo "Исходный запрос:\n";
    echo "- no_found_rows: " . ($test_query->get('no_found_rows') ? 'true' : 'false') . "\n";
    echo "- meta_query: " . json_encode($test_query->get('meta_query')) . "\n";
    echo "- tax_query: " . json_encode($test_query->get('tax_query')) . "\n\n";
    
    // Тест 2: Применение фильтров
    echo "Тест 2: Применение фильтров\n";
    
    $options = array(
        'enabled' => 1,
        'filter_type' => 'from_date',
        'start_date' => '2025-01-01',
        'end_date' => '',
        'date_order' => 'ASC',
        'post_status' => array('publish'),
        'untranslated_only' => 1,
        'debug_mode' => 1
    );
    
    echo "Настройки фильтра:\n";
    echo "- Тип: " . $options['filter_type'] . "\n";
    echo "- Начальная дата: " . $options['start_date'] . "\n";
    echo "- Статус: " . implode(', ', $options['post_status']) . "\n";
    echo "- Только непереведенные: " . ($options['untranslated_only'] ? 'Да' : 'Нет') . "\n\n";
    
    // Применяем фильтры
    try {
        // Устанавливаем статус
        if (!empty($options['post_status'])) {
            $test_query->set('post_status', $options['post_status']);
        }
        
        // Устанавливаем сортировку
        $test_query->set('orderby', 'date');
        $test_query->set('order', $options['date_order']);
        
        // Устанавливаем date_query
        $date_query = array('inclusive' => true);
        if ($options['filter_type'] === 'date_range' && !empty($options['end_date'])) {
            $date_query['after'] = $options['start_date'];
            $date_query['before'] = $options['end_date'];
        } else {
            $date_query['after'] = $options['start_date'];
        }
        $test_query->set('date_query', array($date_query));
        
        // Применяем фильтр непереведенных записей
        if ($options['untranslated_only']) {
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
            
            $current_meta_query = $test_query->get('meta_query');
            if (is_array($current_meta_query) && !empty($current_meta_query)) {
                $test_query->set('meta_query', array(
                    'relation' => 'AND',
                    $current_meta_query,
                    $untranslated_meta_query
                ));
            } else {
                $test_query->set('meta_query', $untranslated_meta_query);
            }
        }
        
        // Безопасные ограничения
        $test_query->set('posts_per_page', 100);
        $test_query->set('no_found_rows', true);
        
        echo "Результат применения фильтров:\n";
        echo "- post_status: " . json_encode($test_query->get('post_status')) . "\n";
        echo "- orderby: " . $test_query->get('orderby') . "\n";
        echo "- order: " . $test_query->get('order') . "\n";
        echo "- date_query: " . json_encode($test_query->get('date_query')) . "\n";
        echo "- meta_query: " . json_encode($test_query->get('meta_query')) . "\n";
        echo "- posts_per_page: " . $test_query->get('posts_per_page') . "\n";
        echo "- no_found_rows: " . ($test_query->get('no_found_rows') ? 'true' : 'false') . "\n\n";
        
        echo "✅ Тест прошел успешно!\n";
        
    } catch (Exception $e) {
        echo "❌ Ошибка: " . $e->getMessage() . "\n";
    }
}

// Запускаем тест
test_filter_logic();
