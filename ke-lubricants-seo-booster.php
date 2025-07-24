<?php
/*
Plugin Name: KE Lubricants SEO Booster
Description: Advanced SEO optimization plugin with AI-powered meta generation, schema markup, sitemap integration, and comprehensive SEO analysis. Compatible with Rank Math, Yoast, and Elementor.
Version: 2.0
Author: Krish Yadav & GPT
Text Domain: ke-seo-booster
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KESEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KESEO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('KESEO_VERSION', '2.0');

class KELubricantsSEOBooster {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('save_post', array($this, 'generate_seo_data'), 20, 2);
        add_action('wp_head', array($this, 'output_schema_markup'));
        add_action('wp_head', array($this, 'output_open_graph_tags'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_keseo_generate_preview', array($this, 'ajax_generate_preview'));
        add_action('wp_ajax_keseo_bulk_generate', array($this, 'ajax_bulk_generate'));
        add_filter('wp_sitemaps_posts_entry', array($this, 'enhance_sitemap_entry'), 10, 3);
    }

    public function create_admin_menu() {
        add_options_page(
            __('KE SEO Booster', 'ke-seo-booster'),
            __('KE SEO Booster', 'ke-seo-booster'),
            'manage_options',
            'ke-seo-booster',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ke-seo-booster',
            __('SEO Analysis', 'ke-seo-booster'),
            __('SEO Analysis', 'ke-seo-booster'),
            'manage_options',
            'ke-seo-analysis',
            array($this, 'analysis_page')
        );
    }

    public function register_settings() {
        register_setting('kelubricants_seo_group', 'kelubricants_openai_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('kelubricants_seo_group', 'keseo_auto_generate', array(
            'default' => '1'
        ));
        register_setting('kelubricants_seo_group', 'keseo_post_types', array(
            'default' => array('post', 'page', 'product')
        ));
        register_setting('kelubricants_seo_group', 'keseo_enable_schema', array(
            'default' => '1'
        ));
        register_setting('kelubricants_seo_group', 'keseo_enable_og_tags', array(
            'default' => '1'
        ));
        register_setting('kelubricants_seo_group', 'keseo_focus_keywords', array(
            'default' => 'lubricants, automotive oil, engine oil, industrial lubricants'
        ));
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ke-seo-booster') !== false || 
            strpos($hook, 'post.php') !== false || 
            strpos($hook, 'post-new.php') !== false) {
            
            wp_enqueue_script('keseo-admin', KESEO_PLUGIN_URL . 'admin.js', array('jquery'), KESEO_VERSION, true);
            wp_enqueue_style('keseo-admin', KESEO_PLUGIN_URL . 'admin.css', array(), KESEO_VERSION);
            
            wp_localize_script('keseo-admin', 'keseo_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('keseo_nonce'),
                'post_id' => isset($_GET['post']) ? intval($_GET['post']) : 0
            ));
        }
    }

    public function settings_page() {
        $api_key = get_option('kelubricants_openai_api_key');
        $auto_generate = get_option('keseo_auto_generate', '1');
        $post_types = get_option('keseo_post_types', array('post', 'page', 'product'));
        $enable_schema = get_option('keseo_enable_schema', '1');
        $enable_og = get_option('keseo_enable_og_tags', '1');
        $focus_keywords = get_option('keseo_focus_keywords', '');
        ?>
        <div class="wrap">
            <h1><?php _e('KE Lubricants SEO Booster Settings', 'ke-seo-booster'); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'ke-seo-booster'); ?></a>
                <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'ke-seo-booster'); ?></a>
                <a href="#bulk-actions" class="nav-tab"><?php _e('Bulk Actions', 'ke-seo-booster'); ?></a>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('kelubricants_seo_group'); ?>
                
                <div id="general" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('OpenAI API Key', 'ke-seo-booster'); ?></th>
                            <td>
                                <input type="password" name="kelubricants_openai_api_key" value="<?php echo esc_attr($api_key); ?>" style="width:400px;" />
                                <p class="description"><?php _e('Get your API key from OpenAI dashboard', 'ke-seo-booster'); ?></p>
                                <button type="button" id="test-api-key" class="button"><?php _e('Test API Key', 'ke-seo-booster'); ?></button>
                                <span id="api-test-result"></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Auto Generate SEO', 'ke-seo-booster'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="keseo_auto_generate" value="1" <?php checked($auto_generate, '1'); ?> />
                                    <?php _e('Automatically generate SEO data when posts are saved', 'ke-seo-booster'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Focus Keywords', 'ke-seo-booster'); ?></th>
                            <td>
                                <textarea name="keseo_focus_keywords" rows="3" style="width:400px;"><?php echo esc_textarea($focus_keywords); ?></textarea>
                                <p class="description"><?php _e('Comma-separated keywords to focus on for your business', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="advanced" class="tab-content" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Supported Post Types', 'ke-seo-booster'); ?></th>
                            <td>
                                <?php
                                $available_post_types = get_post_types(array('public' => true), 'objects');
                                foreach ($available_post_types as $post_type) {
                                    $checked = in_array($post_type->name, $post_types) ? 'checked' : '';
                                    echo '<label><input type="checkbox" name="keseo_post_types[]" value="' . esc_attr($post_type->name) . '" ' . $checked . '> ' . esc_html($post_type->labels->name) . '</label><br>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Schema Markup', 'ke-seo-booster'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="keseo_enable_schema" value="1" <?php checked($enable_schema, '1'); ?> />
                                    <?php _e('Enable automatic schema markup generation', 'ke-seo-booster'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Open Graph Tags', 'ke-seo-booster'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="keseo_enable_og_tags" value="1" <?php checked($enable_og, '1'); ?> />
                                    <?php _e('Enable Open Graph meta tags for social media', 'ke-seo-booster'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="bulk-actions" class="tab-content" style="display:none;">
                    <h3><?php _e('Bulk SEO Generation', 'ke-seo-booster'); ?></h3>
                    <p><?php _e('Generate SEO data for multiple posts at once.', 'ke-seo-booster'); ?></p>
                    <button type="button" id="bulk-generate-seo" class="button button-primary"><?php _e('Generate SEO for All Posts', 'ke-seo-booster'); ?></button>
                    <div id="bulk-progress" style="margin-top: 20px;"></div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>

        <style>
        .tab-content { margin-top: 20px; }
        .nav-tab-wrapper { margin-bottom: 0; }
        #api-test-result { margin-left: 10px; }
        .success { color: green; }
        .error { color: red; }
        #bulk-progress { padding: 10px; background: #f1f1f1; border-radius: 4px; display: none; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });

            // API key test
            $('#test-api-key').click(function() {
                var apiKey = $('input[name="kelubricants_openai_api_key"]').val();
                if (!apiKey) {
                    $('#api-test-result').html('<span class="error">Please enter an API key first</span>');
                    return;
                }
                
                $('#api-test-result').html('Testing...');
                $.post(keseo_ajax.ajax_url, {
                    action: 'keseo_test_api',
                    api_key: apiKey,
                    nonce: keseo_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $('#api-test-result').html('<span class="success">✓ API key is valid</span>');
                    } else {
                        $('#api-test-result').html('<span class="error">✗ ' + response.data + '</span>');
                    }
                });
            });

            // Bulk generation
            $('#bulk-generate-seo').click(function() {
                if (!confirm('This will generate SEO data for all posts. Continue?')) return;
                
                $('#bulk-progress').show().html('Starting bulk generation...');
                $.post(keseo_ajax.ajax_url, {
                    action: 'keseo_bulk_generate',
                    nonce: keseo_ajax.nonce
                }, function(response) {
                    $('#bulk-progress').html(response.data);
                });
            });
        });
        </script>
        <?php
    }

    public function analysis_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('SEO Analysis', 'ke-seo-booster'); ?></h1>
            <div id="seo-analysis-results">
                <p><?php _e('Loading SEO analysis...', 'ke-seo-booster'); ?></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $.post(ajaxurl, {
                action: 'keseo_get_analysis',
                nonce: '<?php echo wp_create_nonce('keseo_nonce'); ?>'
            }, function(response) {
                $('#seo-analysis-results').html(response.data);
            });
        });
        </script>
        <?php
    }

    public function generate_seo_data($post_id, $post) {
        // Skip if conditions not met
        if (wp_is_post_revision($post_id) || 
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !get_option('keseo_auto_generate', '1')) {
            return;
        }

        // Check if post type is supported
        $supported_types = get_option('keseo_post_types', array('post', 'page', 'product'));
        if (!in_array($post->post_type, $supported_types)) {
            return;
        }

        // Get API key
        $api_key = get_option('kelubricants_openai_api_key');
        if (empty($api_key)) return;

        // Check if SEO data already exists (avoid overwriting)
        $existing_title = get_post_meta($post_id, '_rank_math_title', true);
        $existing_yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        
        if (!empty($existing_title) || !empty($existing_yoast_title)) {
            return; // Don't overwrite existing SEO data
        }

        $seo_data = $this->call_openai_api($post_id, $post, $api_key);
        
        if ($seo_data) {
            $this->update_seo_meta($post_id, $seo_data);
        }
    }

    private function call_openai_api($post_id, $post, $api_key) {
        // Check cache first
        $cache_key = 'keseo_seo_' . md5($post_id . $post->post_modified);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $title = get_the_title($post_id);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = get_the_excerpt($post_id);
        $focus_keywords = get_option('keseo_focus_keywords', '');
        
        // Enhanced prompt with validation mechanisms
        $business_context = "lubricants, automotive oils, industrial fluids, and related automotive products";
        $market_context = $this->get_market_context();
        
        $prompt = "You are a senior SEO specialist with expertise in {$business_context}. Using current market data: {$market_context}
        
        IMPORTANT: Base keyword selection on these priority factors:
        1. Actual search demand (not assumptions)
        2. Commercial intent and conversion potential  
        3. Competition level vs. site authority
        4. Business relevance and product alignment
        5. Local market factors if applicable
        
        Analyze the following content and generate highly optimized SEO data:

CONTENT ANALYSIS:
Title: {$title}
Content: " . substr($content, 0, 1500) . "
Excerpt: {$excerpt}
Industry Keywords: {$focus_keywords}

REQUIREMENTS:
1. SEO Title (50-60 chars): Include primary keyword naturally, make it compelling for click-through
2. Meta Description (150-155 chars): Include primary keyword, add value proposition, include call-to-action
3. Primary Focus Keyword: Single most relevant keyword from the content
4. SEO Tags (5-8 tags): Mix of primary, secondary, and long-tail keywords related to {$business_context}
5. Image Alt Text: Descriptive, keyword-optimized, accessible
6. Schema Type: Choose most appropriate (Product, Article, HowTo, FAQ, etc.)
7. Open Graph Title: Social media optimized (can differ from SEO title)
8. Open Graph Description: Social sharing optimized (can differ from meta description)

INDUSTRY FOCUS: Optimize specifically for search queries related to {$business_context}, considering user intent and commercial value.

OUTPUT FORMAT: Return ONLY valid JSON with exact keys: meta_title, meta_description, focus_keyword, seo_tags, image_alt_text, schema_type, og_title, og_description";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4-turbo',
                'messages' => array(
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.3,
                'max_tokens' => 1000
            )),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            error_log('KE SEO Booster API Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('KE SEO Booster: Invalid API response');
            return false;
        }

        $seo_content = $data['choices'][0]['message']['content'];
        
        // Clean the JSON response (remove markdown formatting if present)
        $seo_content = preg_replace('/```json\s*/', '', $seo_content);
        $seo_content = preg_replace('/```\s*$/', '', $seo_content);
        
        $seo_data = json_decode($seo_content, true);

        if (!$seo_data) {
            error_log('KE SEO Booster: Failed to parse JSON response');
            return false;
        }

        // Cache the result for 24 hours
        set_transient($cache_key, $seo_data, 24 * HOUR_IN_SECONDS);

        return $seo_data;
    }

    private function get_market_context() {
        // Get current trends and market data for better keyword selection
        $context = array();
        
        // Get seasonal trends
        $month = date('n');
        if (in_array($month, [3, 4, 5])) {
            $context[] = "Spring maintenance season - higher demand for oil changes and fluid checks";
        } elseif (in_array($month, [6, 7, 8])) {
            $context[] = "Summer driving season - focus on high-temperature performance";
        } elseif (in_array($month, [9, 10, 11])) {
            $context[] = "Fall preparation season - winterization and maintenance focus";
        } else {
            $context[] = "Winter season - cold weather performance and protection emphasis";
        }
        
        // Get regional considerations
        $timezone = get_option('timezone_string');
        if (strpos($timezone, 'America') !== false) {
            $context[] = "North American market - emphasis on automotive and industrial applications";
        }
        
        // Get business focus from settings
        $focus_keywords = get_option('keseo_focus_keywords', '');
        if (!empty($focus_keywords)) {
            $context[] = "Business focus areas: " . $focus_keywords;
        }
        
        return implode('. ', $context);
    }

    private function update_seo_meta($post_id, $seo_data) {
        // Update Rank Math fields
        if (is_plugin_active('seo-by-rank-math/rank-math.php')) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['meta_title']));
            update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($seo_data['meta_description']));
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seo_data['focus_keyword']));
        }

        // Update Yoast fields
        if (is_plugin_active('wordpress-seo/wp-seo.php')) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo_data['meta_title']));
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($seo_data['meta_description']));
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($seo_data['focus_keyword']));
        }

        // Update our own fields for fallback
        update_post_meta($post_id, '_keseo_title', sanitize_text_field($seo_data['meta_title']));
        update_post_meta($post_id, '_keseo_description', sanitize_textarea_field($seo_data['meta_description']));
        update_post_meta($post_id, '_keseo_focus_keyword', sanitize_text_field($seo_data['focus_keyword']));
        update_post_meta($post_id, '_keseo_schema_type', sanitize_text_field($seo_data['schema_type']));
        update_post_meta($post_id, '_keseo_og_title', sanitize_text_field($seo_data['og_title']));
        update_post_meta($post_id, '_keseo_og_description', sanitize_textarea_field($seo_data['og_description']));

        // Set post tags
        if (!empty($seo_data['seo_tags'])) {
            $tags = array_map('trim', explode(',', $seo_data['seo_tags']));
            wp_set_post_terms($post_id, $tags, 'post_tag', false);
        }

        // Update featured image alt text
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id && !empty($seo_data['image_alt_text'])) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($seo_data['image_alt_text']));
        }

        // Update all attached images alt text
        $attachments = get_attached_media('image', $post_id);
        foreach ($attachments as $attachment) {
            $existing_alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);
            if (empty($existing_alt) && !empty($seo_data['image_alt_text'])) {
                update_post_meta($attachment->ID, '_wp_attachment_image_alt', sanitize_text_field($seo_data['image_alt_text']));
            }
        }
    }

    public function output_schema_markup() {
        if (!get_option('keseo_enable_schema', '1') || !is_singular()) return;

        global $post;
        $schema_type = get_post_meta($post->ID, '_keseo_schema_type', true);
        
        if (empty($schema_type)) $schema_type = 'Article';

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => $schema_type,
            'headline' => get_the_title(),
            'description' => get_the_excerpt() ?: get_post_meta($post->ID, '_keseo_description', true),
            'url' => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author()
            )
        );

        // Add organization for products
        if ($schema_type === 'Product') {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => get_bloginfo('name')
            );
        }

        // Add featured image
        if (has_post_thumbnail()) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
            $schema['image'] = $image[0];
        }

        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    public function output_open_graph_tags() {
        if (!get_option('keseo_enable_og_tags', '1') || !is_singular()) return;

        global $post;
        
        $og_title = get_post_meta($post->ID, '_keseo_og_title', true) ?: get_the_title();
        $og_description = get_post_meta($post->ID, '_keseo_og_description', true) ?: get_the_excerpt();
        
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        
        if (has_post_thumbnail()) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
            echo '<meta property="og:image" content="' . esc_url($image[0]) . '">' . "\n";
            
            $alt_text = get_post_meta(get_post_thumbnail_id(), '_wp_attachment_image_alt', true);
            if ($alt_text) {
                echo '<meta property="og:image:alt" content="' . esc_attr($alt_text) . '">' . "\n";
            }
        }
    }

    public function enhance_sitemap_entry($entry, $post, $post_type) {
        // Add lastmod date for better indexing
        $entry['lastmod'] = get_the_modified_date('c', $post);
        
        // Add priority based on post type and recency
        if ($post_type === 'product') {
            $entry['priority'] = '0.8';
        } elseif ($post_type === 'post') {
            // Higher priority for recent posts
            $days_old = (time() - strtotime($post->post_date)) / (60 * 60 * 24);
            $entry['priority'] = $days_old < 30 ? '0.8' : '0.6';
        }
        
        return $entry;
    }

    // AJAX handlers
    public function ajax_generate_preview() {
        check_ajax_referer('keseo_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_die('Invalid post ID');
        }

        $api_key = get_option('kelubricants_openai_api_key');
        $seo_data = $this->call_openai_api($post_id, $post, $api_key);
        
        wp_send_json_success($seo_data);
    }

    public function ajax_bulk_generate() {
        check_ajax_referer('keseo_nonce', 'nonce');
        
        $posts = get_posts(array(
            'post_type' => get_option('keseo_post_types', array('post', 'page')),
            'posts_per_page' => 50,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_keseo_title',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        $generated = 0;
        $api_key = get_option('kelubricants_openai_api_key');

        foreach ($posts as $post) {
            $seo_data = $this->call_openai_api($post->ID, $post, $api_key);
            if ($seo_data) {
                $this->update_seo_meta($post->ID, $seo_data);
                $generated++;
            }
            
            // Add delay to avoid API rate limits
            sleep(1);
        }

        wp_send_json_success("Generated SEO data for {$generated} posts.");
    }
}

// Initialize the plugin
new KELubricantsSEOBooster();

// Add AJAX handler for API testing
add_action('wp_ajax_keseo_test_api', 'keseo_test_api_key');
function keseo_test_api_key() {
    check_ajax_referer('keseo_nonce', 'nonce');
    
    $api_key = sanitize_text_field($_POST['api_key']);
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(array('role' => 'user', 'content' => 'Test')),
            'max_tokens' => 5
        )),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('Connection failed: ' . $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        wp_send_json_success('API key is valid');
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        wp_send_json_error($error);
    }
}

// Add SEO analysis AJAX handler
add_action('wp_ajax_keseo_get_analysis', 'keseo_get_seo_analysis');
function keseo_get_seo_analysis() {
    check_ajax_referer('keseo_nonce', 'nonce');
    
    $posts_with_seo = get_posts(array(
        'post_type' => 'any',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_keseo_title',
                'compare' => 'EXISTS'
            )
        )
    ));

    $total_posts = wp_count_posts()->publish + wp_count_posts('page')->publish;
    $posts_with_seo_count = count($posts_with_seo);
    $coverage_percent = $total_posts > 0 ? round(($posts_with_seo_count / $total_posts) * 100, 1) : 0;

    $html = '<div class="seo-analysis-widget">';
    $html .= '<h3>SEO Coverage Analysis</h3>';
    $html .= '<p><strong>Posts with SEO data:</strong> ' . $posts_with_seo_count . ' / ' . $total_posts . ' (' . $coverage_percent . '%)</p>';
    
    if ($coverage_percent < 100) {
        $missing = $total_posts - $posts_with_seo_count;
        $html .= '<p><em>' . $missing . ' posts still need SEO optimization.</em></p>';
        $html .= '<button type="button" id="generate-missing-seo" class="button button-primary">Generate SEO for Missing Posts</button>';
    } else {
        $html .= '<p style="color: green;">✓ All posts have SEO data!</p>';
    }
    
    $html .= '</div>';

    wp_send_json_success($html);
}

// Activation hook
register_activation_hook(__FILE__, 'keseo_activation');
function keseo_activation() {
    // Set default options
    add_option('keseo_auto_generate', '1');
    add_option('keseo_enable_schema', '1');
    add_option('keseo_enable_og_tags', '1');
    add_option('keseo_post_types', array('post', 'page', 'product'));
    add_option('keseo_focus_keywords', 'lubricants, automotive oil, engine oil, industrial lubricants');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'keseo_deactivation');
function keseo_deactivation() {
    // Clean up if needed
    wp_clear_scheduled_hook('keseo_daily_analysis');
}