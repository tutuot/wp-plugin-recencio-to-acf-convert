<?php
/**
 * Plugin Name: Recencio para ACF - Migrador
 * Description: Converte resenhas do Recencio (rcno_review) em posts normais e copia os metadados para campos ACF, sem apagar os dados de origem.
 * Version: 1.0.0
 * Author: Livros Para Ler
 */
if (!defined('ABSPATH')) exit;

class LPL_Recencio_ACF_Migrator {
    const SOURCE_TYPE = 'rcno_review';
    const MARKER = '_lpl_migrated_from_rcno';
    const VERSION = '1.0.0';

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_lpl_rcno_preview', [$this, 'preview']);
        add_action('admin_post_lpl_rcno_migrate', [$this, 'migrate']);
    }

    public function menu() {
        add_management_page('Migrador Recencio → ACF', 'Migrador Recencio → ACF', 'manage_options', 'lpl-recencio-acf', [$this, 'page']);
    }

    private function count_sources() {
        $q = new WP_Query(['post_type'=>self::SOURCE_TYPE,'post_status'=>['publish','draft','pending','private','future'],'posts_per_page'=>1,'fields'=>'ids']);
        return (int)$q->found_posts;
    }

    public function page() {
        if (!current_user_can('manage_options')) return;
        $count = $this->count_sources();
        $done = $this->count_migrated();
        ?>
        <div class="wrap">
          <h1>Migrador Recencio → ACF</h1>
          <p>Este plugin cria posts normais a partir de <code>rcno_review</code> e copia os dados para o ACF. Os registros originais do Recencio e seus metadados <strong>não são apagados nem alterados</strong>.</p>
          <div style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:760px;margin:18px 0">
            <p><strong>Resenhas encontradas:</strong> <?php echo esc_html($count); ?></p>
            <p><strong>Já convertidas:</strong> <?php echo esc_html($done); ?></p>
            <p><strong>Restantes:</strong> <?php echo esc_html(max(0,$count-$done)); ?></p>
            <p><strong>Destino:</strong> posts normais, categoria <code>reviews</code> e, por padrão, categoria <code>impressa</code>.</p>
            <p><strong>Campos ACF:</strong> titulo, autor, paginas, editora, serie, volume, isbn, capa, ilustrador, sinopse, nota, link_amazon e tipo_midia.</p>
          </div>
          <h2>1. Simular</h2>
          <p>Gera uma prévia sem criar posts nem modificar dados.</p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="lpl_rcno_preview">
            <?php wp_nonce_field('lpl_rcno_preview'); ?>
            <?php submit_button('Executar simulação', 'secondary'); ?>
          </form>
          <hr><h2>2. Converter</h2>
          <p>Antes de converter, faça um backup completo do site. A conversão é feita em lotes pequenos e pode ser retomada: registros já convertidos não serão duplicados.</p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Confirma a criação dos posts? Os registros originais do Recencio serão mantidos. Faça um backup antes de continuar.');">
            <input type="hidden" name="action" value="lpl_rcno_migrate">
            <?php wp_nonce_field('lpl_rcno_migrate'); ?>
            <?php submit_button('Converter resenhas', 'primary'); ?>
          </form>
          <p style="color:#646970">Importante: este migrador não transfere imagens externas para a biblioteca. Para capas já existentes na Biblioteca de Mídia, ele reutiliza o ID do anexo do Recencio. Se uma capa não tiver anexo válido, ela será listada no relatório.</p>
        </div>
        <?php
        if (isset($_GET['lpl_report'])) {
            $report = get_transient('lpl_rcno_report_' . get_current_user_id());
            if ($report) {
                echo '<div class="notice notice-info"><p><strong>Relatório da última execução</strong></p><ul>';
                foreach ($report as $line) echo '<li>' . esc_html($line) . '</li>';
                echo '</ul></div>';
            }
        }
    }

    private function count_migrated() {
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s", self::MARKER));
    }

    private function source_posts() {
        return get_posts([
            'post_type'=>self::SOURCE_TYPE,
            'post_status'=>['publish','draft','pending','private','future'],
            'numberposts'=>-1,
            'orderby'=>'date',
            'order'=>'ASC'
        ]);
    }

    private function meta_first($post_id, $keys) {
        foreach ((array)$keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if ($value !== '' && $value !== null && $value !== false) return $value;
        }
        return '';
    }

    private function amazon_link($raw) {
        if (is_string($raw)) {
            $maybe = maybe_unserialize($raw);
            if ($maybe !== $raw) $raw = $maybe;
        }
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                $store = isset($item['store']) ? strtolower((string)$item['store']) : '';
                $link = isset($item['link']) ? html_entity_decode((string)$item['link'], ENT_QUOTES, 'UTF-8') : '';
                if ($link && ($store === 'amazon' || stripos($link, 'amazon.') !== false || stripos($link, 'amzn.to') !== false)) return esc_url_raw($link);
            }
            foreach ($raw as $item) if (is_array($item) && !empty($item['link'])) return esc_url_raw(html_entity_decode((string)$item['link'], ENT_QUOTES, 'UTF-8'));
        }
        if (is_string($raw) && filter_var($raw, FILTER_VALIDATE_URL)) return esc_url_raw($raw);
        return '';
    }

    private function set_acf_or_meta($post_id, $field_name, $value) {
        if ($value === '' || $value === null) return;
        if (function_exists('update_field')) update_field($field_name, $value, $post_id);
        else update_post_meta($post_id, $field_name, $value);
    }

    private function infer_type($source_id, $title) {
        $terms = wp_get_object_terms($source_id, ['rcno_genre','rcno_author','rcno_series','rcno_publisher'], ['fields'=>'names']);
        $haystack = strtolower(remove_accents($title . ' ' . implode(' ', is_wp_error($terms) ? [] : $terms)));
        if (preg_match('/\b(webtoon|web comic|webcomic)\b/', $haystack)) return 'webtoon';
        if (preg_match('/\b(manga|mangá|manhwa|manhua)\b/', $haystack)) return 'manga';
        if (preg_match('/\b(hq|graphic novel|quadrinhos)\b/', $haystack)) return 'hq';
        return 'livro';
    }

    private function source_data($source) {
        $id = $source->ID;
        $cover_id = (int)$this->meta_first($id, ['rcno_reviews_book_cover_id']);
        if ($cover_id && get_post_type($cover_id) !== 'attachment') $cover_id = 0;
        $buy = get_post_meta($id, 'rcno_review_buy_links', true);
        $amazon = $this->amazon_link($buy);
        $author_terms = wp_get_object_terms($id, 'rcno_author', ['fields'=>'names']);
        $publisher_terms = wp_get_object_terms($id, 'rcno_publisher', ['fields'=>'names']);
        $series_terms = wp_get_object_terms($id, 'rcno_series', ['fields'=>'names']);
        $author = !is_wp_error($author_terms) && $author_terms ? implode(', ', $author_terms) : '';
        $publisher = !is_wp_error($publisher_terms) && $publisher_terms ? implode(', ', $publisher_terms) : '';
        $series = !is_wp_error($series_terms) && $series_terms ? implode(', ', $series_terms) : '';
        return [
            'titulo' => $this->meta_first($id, ['rcno_book_title']) ?: $source->post_title,
            'autor' => $author,
            'paginas' => $this->meta_first($id, ['rcno_book_page_count']),
            'editora' => $publisher,
            'serie' => $series,
            'volume' => $this->meta_first($id, ['rcno_book_series_number']),
            'isbn' => $this->meta_first($id, ['rcno_book_isbn13','rcno_book_isbn']),
            'capa' => $cover_id,
            'ilustrador' => $this->meta_first($id, ['rcno_book_illustrator']),
            'sinopse' => $this->meta_first($id, ['rcno_book_description']),
            'nota' => $this->meta_first($id, ['rcno_admin_rating']),
            'link_amazon' => $amazon,
            'tipo_midia' => $this->infer_type($id, $source->post_title),
            'cover_missing' => (!$cover_id && $this->meta_first($id, ['rcno_reviews_book_cover_src'])) ? 1 : 0,
        ];
    }

    private function preview_url($report) {
        set_transient('lpl_rcno_report_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg(['page'=>'lpl-recencio-acf','lpl_report'=>1], admin_url('tools.php')));
        exit;
    }

    public function preview() {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer('lpl_rcno_preview');
        $report = [];
        $sources = $this->source_posts();
        $report[] = 'Registros de origem encontrados: ' . count($sources);
        $ready = 0; $already = 0; $no_cover = 0;
        foreach ($sources as $source) {
            $existing = get_posts(['post_type'=>'post','post_status'=>'any','meta_key'=>self::MARKER,'meta_value'=>$source->ID,'numberposts'=>1,'fields'=>'ids']);
            if ($existing) { $already++; continue; }
            $d = $this->source_data($source);
            $ready++;
            if ($d['cover_missing']) $no_cover++;
        }
        $report[] = 'Prontas para conversão: ' . $ready;
        $report[] = 'Já convertidas (serão ignoradas): ' . $already;
        $report[] = 'Com URL de capa, mas sem anexo válido na biblioteca: ' . $no_cover;
        $report[] = 'A simulação não criou nem modificou nenhum post.';
        $this->preview_url($report);
    }

    public function migrate() {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer('lpl_rcno_migrate');
        if (!post_type_exists(self::SOURCE_TYPE)) wp_die('O tipo rcno_review não está registrado. Mantenha o Recencio ativo durante a conversão.');
        $report = [];
        $created = 0; $skipped = 0; $errors = 0; $no_cover = 0;
        foreach ($this->source_posts() as $source) {
            $existing = get_posts(['post_type'=>'post','post_status'=>'any','meta_key'=>self::MARKER,'meta_value'=>$source->ID,'numberposts'=>1,'fields'=>'ids']);
            if ($existing) { $skipped++; continue; }
            $d = $this->source_data($source);
            $new_id = wp_insert_post([
                'post_type'=>'post',
                'post_status'=>$source->post_status,
                'post_title'=>$source->post_title,
                'post_content'=>$source->post_content,
                'post_excerpt'=>$source->post_excerpt,
                'post_name'=>$source->post_name,
                'post_date'=>$source->post_date,
                'post_date_gmt'=>$source->post_date_gmt,
                'post_author'=>$source->post_author,
                'comment_status'=>$source->comment_status,
                'ping_status'=>$source->ping_status,
                'menu_order'=>$source->menu_order,
                'meta_input'=>[self::MARKER=>$source->ID, '_lpl_rcno_original_slug'=>$source->post_name, '_lpl_rcno_original_url'=>get_permalink($source->ID)],
            ], true);
            if (is_wp_error($new_id)) { $errors++; $report[] = 'Erro em "'.$source->post_title.'": '.$new_id->get_error_message(); continue; }
            wp_set_object_terms($new_id, ['reviews','impressa'], 'category', false);
            $map = ['titulo','autor','paginas','editora','serie','volume','isbn','capa','ilustrador','sinopse','nota','link_amazon','tipo_midia'];
            foreach ($map as $field) {
                if ($field === 'paginas' || $field === 'volume') $d[$field] = ($d[$field] === '' ? '' : (int)$d[$field]);
                if ($field === 'nota') $d[$field] = ($d[$field] === '' ? '' : (float)$d[$field]);
                $this->set_acf_or_meta($new_id, $field, $d[$field]);
            }
            if ($d['capa']) set_post_thumbnail($new_id, (int)$d['capa']);
            elseif ($d['cover_missing']) $no_cover++;
            // Preserve original review taxonomy terms on the new post where names/slugs match.
            foreach (['rcno_author'=>'lpl_rcno_author','rcno_genre'=>'lpl_rcno_genre','rcno_series'=>'lpl_rcno_series','rcno_publisher'=>'lpl_rcno_publisher'] as $old_tax=>$new_tax) {
                $names = wp_get_object_terms($source->ID, $old_tax, ['fields'=>'names']);
                if (!is_wp_error($names) && $names) {
                    if (!taxonomy_exists($new_tax)) register_taxonomy($new_tax, 'post', ['public'=>false,'show_ui'=>true,'label'=>'Recencio - '.ucfirst(str_replace('rcno_','',$old_tax))]);
                    wp_set_object_terms($new_id, $names, $new_tax, false);
                }
            }
            // Copy source post thumbnail if Recencio used the featured image rather than its cover meta.
            $thumb = get_post_thumbnail_id($source->ID);
            if (!$d['capa'] && $thumb) set_post_thumbnail($new_id, $thumb);
            $created++;
        }
        $report[] = 'Posts criados: ' . $created;
        $report[] = 'Ignorados por já terem sido convertidos: ' . $skipped;
        $report[] = 'Erros: ' . $errors;
        $report[] = 'Capas que precisam de conferência: ' . $no_cover;
        $report[] = 'Os registros rcno_review e seus metadados foram mantidos.';
        $this->preview_url($report);
    }
}
new LPL_Recencio_ACF_Migrator();
