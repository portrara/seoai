<?php
/*
Plugin Name: KE Lubricants SEO Booster Enhanced
Description: Auto-generate SEO meta title, description, tags, and image alt-text via GPT-4 (OpenAI API). Compatible with Rank Math & Elementor. Enhanced version with better error handling and features.
Version: 1.1
Author: Krish Yadav & GPT
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class KELubricantsSEOBooster {
    
    private $option_name = 'kelubricants_seo_options';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('save_post', array($this, 'generate_seo_data'), 20, 2);
        add_action('wp_ajax_ke_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_ke_generate_seo_manual', array($this, 'manual_seo_generation'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'KE SEO Booster Enhanced',
            'KE SEO Booster',
            'manage_options',
            'ke-seo-booster',
            array($this, 'settings_page')
        );
    }
    
    public function init_settings() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_options'));
    }
    
    public function sanitize_options($input) {
        $sanitized = array();
        $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        $sanitized['auto_generate'] = isset($input['auto_generate']) ? 1 : 0;
        $sanitized['post_types'] = isset($input['post_types']) ? array_map('sanitize_text_field', $input['post_types']) : array('post');
        $sanitized['gpt_model'] = sanitize_text_field($input['gpt_model']);
        $sanitized['temperature'] = floatval($input['temperature']);
        return $sanitized;
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_ke-seo-booster') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'ke_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ke_seo_nonce')
        ));
    }
    
    public function settings_page() {
        $options = get_option($this->option_name, array(
            'api_key' => '',
            'auto_generate' => 1,
            'post_types' => array('post'),
            'gpt_model' => 'gpt-4-turbo',
            'temperature' => 0.5
        ));
        
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1>KE Lubricants SEO Booster Enhanced Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields($this->option_name); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="<?php echo $this->option_name; ?>[api_key]" 
                                   value="<?php echo esc_attr($options['api_key']); ?>" 
                                   style="width:400px;" class="regular-text" />
                            <button type="button" id="test-api" class="button">Test API Connection</button>
                            <div id="api-test-result"></div>
                            <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Auto-generate SEO on Save</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo $this->option_name; ?>[auto_generate]" 
                                       value="1" <?php checked($options['auto_generate'], 1); ?> />
                                Automatically generate SEO data when posts are saved
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Post Types</th>
                        <td>
                            <?php foreach ($post_types as $post_type): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[post_types][]" 
                                           value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $options['post_types'])); ?> />
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">GPT Model</th>
                        <td>
                            <select name="<?php echo $this->option_name; ?>[gpt_model]">
                                <option value="gpt-4-turbo" <?php selected($options['gpt_model'], 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-4" <?php selected($options['gpt_model'], 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-3.5-turbo" <?php selected($options['gpt_model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Temperature</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[temperature]" 
                                   value="<?php echo esc_attr($options['temperature']); ?>" 
                                   min="0" max="1" step="0.1" />
                            <p class="description">Controls randomness (0-1). Lower values = more focused, higher values = more creative</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2>Manual SEO Generation</h2>
            <p>Generate SEO data for existing posts:</p>
            <button type="button" id="manual-generate" class="button button-primary">Generate SEO for All Posts</button>
            <div id="manual-generation-progress"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-api').click(function() {
                var apiKey = $('input[name="<?php echo $this->option_name; ?>[api_key]"]').val();
                if (!apiKey) {
                    $('#api-test-result').html('<p style="color: red;">Please enter an API key first.</p>');
                    return;
                }
                
                $('#api-test-result').html('<p>Testing API connection...</p>');
                
                $.post(ke_ajax.ajax_url, {
                    action: 'ke_test_api',
                    api_key: apiKey,
                    nonce: ke_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $('#api-test-result').html('<p style="color: green;">✓ API connection successful!</p>');
                    } else {
                        $('#api-test-result').html('<p style="color: red;">✗ API test failed: ' + response.data + '</p>');
                    }
                });
            });
            
            $('#manual-generate').click(function() {
                if (!confirm('This will generate SEO data for all posts. Continue?')) {
                    return;
                }
                
                $('#manual-generation-progress').html('<p>Generating SEO data...</p>');
                
                $.post(ke_ajax.ajax_url, {
                    action: 'ke_generate_seo_manual',
                    nonce: ke_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        $('#manual-generation-progress').html('<p style="color: green;">✓ SEO data generated successfully!</p>');
                    } else {
                        $('#manual-generation-progress').html('<p style="color: red;">✗ Generation failed: ' + response.data + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function test_api_connection() {
        check_ajax_referer('ke_seo_nonce', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }
        
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
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
            wp_send_json_error('API error: ' . $error_message);
        }
        
        wp_send_json_success('API connection successful');
    }
    
    public function manual_seo_generation() {
        check_ajax_referer('ke_seo_nonce', 'nonce');
        
        $options = get_option($this->option_name);
        $posts = get_posts(array(
            'post_type' => $options['post_types'],
            'numberposts' => 10, // Limit for safety
            'post_status' => 'publish'
        ));
        
        $success_count = 0;
        foreach ($posts as $post) {
            if ($this->generate_seo_for_post($post->ID, $post)) {
                $success_count++;
            }
        }
        
        wp_send_json_success("Generated SEO data for {$success_count} posts");
    }
    
    public function generate_seo_data($post_id, $post) {
        // Skip if conditions not met
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        
        $options = get_option($this->option_name);
        
        // Skip if auto-generation is disabled
        if (!$options['auto_generate']) {
            return;
        }
        
        // Skip if post type not enabled
        if (!in_array($post->post_type, $options['post_types'])) {
            return;
        }
        
        $this->generate_seo_for_post($post_id, $post);
    }
    
    private function generate_seo_for_post($post_id, $post) {
        $options = get_option($this->option_name);
        $api_key = $options['api_key'];
        
        if (empty($api_key)) {
            error_log('KE SEO Booster: API key not configured');
            return false;
        }
        
        // Get post data
        $title = get_the_title($post_id);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = get_the_excerpt($post_id);
        
        // Prepare enhanced prompt
        $prompt = $this->build_seo_prompt($title, $content, $excerpt);
        
        // Call OpenAI API
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => $options['gpt_model'],
                'messages' => array(array('role' => 'user', 'content' => $prompt)),
                'temperature' => floatval($options['temperature']),
                'max_tokens' => 500
            )),
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            error_log('KE SEO Booster API Error: ' . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            error_log('KE SEO Booster: API returned HTTP ' . $http_code);
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('KE SEO Booster: Invalid API response format');
            return false;
        }
        
        $seo_json = $data['choices'][0]['message']['content'];
        
        // Clean JSON response (remove potential markdown formatting)
        $seo_json = preg_replace('/```json\s*/', '', $seo_json);
        $seo_json = preg_replace('/```\s*$/', '', $seo_json);
        
        $seo = json_decode($seo_json, true);
        
        if (!$seo || !is_array($seo)) {
            error_log('KE SEO Booster: Failed to parse SEO JSON: ' . $seo_json);
            return false;
        }
        
        // Update SEO fields
        $this->update_seo_fields($post_id, $seo);
        
        return true;
    }
    
    private function build_seo_prompt($title, $content, $excerpt) {
        return "You are an SEO expert. Generate SEO-optimized content for a lubricants/automotive product page.

Product Title: {$title}
Content: " . substr($content, 0, 1000) . "
Excerpt: {$excerpt}

Generate the following SEO elements:
1. Meta title (50-60 characters, include primary keyword)
2. Meta description (150-155 characters, compelling and keyword-rich)
3. SEO tags (5-8 relevant tags, comma-separated)
4. Image alt-text (descriptive and keyword-optimized)

Focus on lubricants, automotive, industrial applications, and performance benefits.

Return ONLY a valid JSON object with these exact keys:
{
  \"meta_title\": \"...\",
  \"meta_description\": \"...\",
  \"seo_tags\": \"...\",
  \"image_alt_text\": \"...\"
}";
    }
    
    private function update_seo_fields($post_id, $seo) {
        // Update Rank Math fields
        if (isset($seo['meta_title'])) {
            update_post_meta($post_id, '_rank_math_title', sanitize_text_field($seo['meta_title']));
        }
        
        if (isset($seo['meta_description'])) {
            update_post_meta($post_id, '_rank_math_description', sanitize_textarea_field($seo['meta_description']));
        }
        
        // Also support Yoast SEO
        if (isset($seo['meta_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo['meta_title']));
        }
        
        if (isset($seo['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($seo['meta_description']));
        }
        
        // Set post tags
        if (isset($seo['seo_tags'])) {
            $tags = array_map('trim', explode(',', sanitize_text_field($seo['seo_tags'])));
            wp_set_post_terms($post_id, $tags, 'post_tag', false);
        }
        
        // Set featured image alt text
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id && isset($seo['image_alt_text']) && !empty($seo['image_alt_text'])) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($seo['image_alt_text']));
        }
        
        // Log successful generation
        update_post_meta($post_id, '_ke_seo_generated', current_time('mysql'));
    }
}

// Initialize the plugin
new KELubricantsSEOBooster();