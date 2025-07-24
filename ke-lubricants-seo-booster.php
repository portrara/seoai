<?php
/*
Plugin Name: KE Lubricants SEO Booster
Description: Auto-generate SEO meta title, description, tags, and image alt-text via GPT-4 (OpenAI API). Compatible with Rank Math & Elementor.
Version: 1.0
Author: Krish Yadav & GPT
*/

// Create settings menu
add_action('admin_menu', 'kelubricants_seo_menu');
function kelubricants_seo_menu(){
    add_options_page('KE SEO Booster', 'KE SEO Booster', 'manage_options', 'ke-seo-booster', 'kelubricants_seo_settings_page');
}

// Register settings
add_action('admin_init', 'kelubricants_seo_settings');
function kelubricants_seo_settings() {
    register_setting('kelubricants_seo_group', 'kelubricants_openai_api_key');
}

// Admin settings page HTML
function kelubricants_seo_settings_page() {
?>
<div class="wrap">
    <h1>KE Lubricants SEO Booster Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields('kelubricants_seo_group'); ?>
        <?php do_settings_sections('kelubricants_seo_group'); ?>
        <table class="form-table">
            <tr valign="top">
            <th scope="row">OpenAI API Key</th>
            <td><input type="text" name="kelubricants_openai_api_key" value="<?php echo esc_attr(get_option('kelubricants_openai_api_key')); ?>" style="width:400px;" /></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
<?php }

// Hook into post save
add_action('save_post', 'kelubricants_generate_seo_data', 20, 2);
function kelubricants_generate_seo_data($post_id, $post){
    if(wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
        return;
    }

    // Fetch API key from settings
    $api_key = get_option('kelubricants_openai_api_key');
    if(empty($api_key)) return;

    // Get post content & title
    $title = get_the_title($post_id);
    $content = wp_strip_all_tags($post->post_content);

    // Prepare API request
    $prompt = "Generate SEO-friendly meta title (max 60 chars), description (max 155 chars), SEO tags (comma-separated), and image alt-text for the following product:\n\nProduct Title: {$title}\n\nContent: {$content}\n\nReturn as JSON with keys: meta_title, meta_description, seo_tags, image_alt_text.";

    // Call OpenAI API
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => 'gpt-4-turbo',
            'messages' => [['role'=>'user','content'=>$prompt]],
            'temperature' => 0.5
        ]),
        'timeout' => 120
    ]);

    if(is_wp_error($response)) return;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $seo = json_decode($data['choices'][0]['message']['content'], true);

    if(!$seo) return;

    // Update Rank Math fields
    update_post_meta($post_id, '_rank_math_title', sanitize_text_field($seo['meta_title']));
    update_post_meta($post_id, '_rank_math_description', sanitize_textarea_field($seo['meta_description']));

    // Set post tags
    wp_set_post_terms($post_id, explode(',', sanitize_text_field($seo['seo_tags'])), 'post_tag', false);

    // Set image alt text
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if($thumbnail_id && !empty($seo['image_alt_text'])){
        update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($seo['image_alt_text']));
    }
}