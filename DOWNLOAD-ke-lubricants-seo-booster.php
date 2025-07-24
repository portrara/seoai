<?php
/*
Plugin Name: KE Lubricants SEO Booster (All-in-One)
Description: Complete AI-powered SEO optimization plugin with Google Keyword Planner integration, schema markup, and industry-specific optimization for lubricants businesses.
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

// Google Ads API Integration Class
class KELubricantsGoogleAdsAPI {
    
    private $customer_id;
    private $developer_token;
    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $access_token;
    
    public function __construct() {
        $this->customer_id = get_option('keseo_google_customer_id');
        $this->developer_token = get_option('keseo_google_developer_token');
        $this->client_id = get_option('keseo_google_client_id');
        $this->client_secret = get_option('keseo_google_client_secret');
        $this->refresh_token = get_option('keseo_google_refresh_token');
        $this->access_token = '';
    }
    
    public function get_keyword_data($keywords, $location = 'US', $language = 'en') {
        if (empty($keywords) || !$this->is_configured()) {
            return false;
        }
        
        if (!$this->get_access_token()) {
            error_log('KE SEO: Failed to get Google Ads access token');
            return false;
        }
        
        $keyword_data = array();
        $keyword_batches = array_chunk($keywords, 10);
        
        foreach ($keyword_batches as $batch) {
            $batch_data = $this->fetch_keyword_batch($batch, $location, $language);
            if ($batch_data) {
                $keyword_data = array_merge($keyword_data, $batch_data);
            }
            sleep(1);
        }
        
        return $keyword_data;
    }
    
    private function fetch_keyword_batch($keywords, $location, $language) {
        $url = "https://googleads.googleapis.com/v15/customers/{$this->customer_id}/keywordPlanIdeas:generateKeywordIdeas";
        
        $keyword_seeds = array();
        foreach ($keywords as $keyword) {
            $keyword_seeds[] = array('text' => trim($keyword));
        }
        
        $request_body = array(
            'keywordPlanNetwork' => 'GOOGLE_SEARCH',
            'geoTargetConstants' => array("geoTargets/{$this->get_location_id($location)}"),
            'language' => "languageConstants/{$this->get_language_id($language)}",
            'keywordSeed' => array(
                'keywords' => $keyword_seeds
            ),
            'includeAdultKeywords' => false
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Developer-Token' => $this->developer_token,
            'Content-Type' => 'application/json'
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($request_body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('KE SEO: Google Ads API error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('KE SEO: Google Ads API returned code ' . $response_code . ': ' . $response_body);
            return false;
        }
        
        return $this->parse_keyword_response($response_body);
    }
    
    private function parse_keyword_response($response_body) {
        $data = json_decode($response_body, true);
        $keyword_data = array();
        
        if (isset($data['results'])) {
            foreach ($data['results'] as $result) {
                $keyword_text = $result['text'] ?? '';
                $search_volume = $result['keywordIdeaMetrics']['avgMonthlySearches'] ?? 0;
                $competition = $result['keywordIdeaMetrics']['competition'] ?? 'UNKNOWN';
                $low_top_bid = $result['keywordIdeaMetrics']['lowTopOfPageBidMicros'] ?? 0;
                $high_top_bid = $result['keywordIdeaMetrics']['highTopOfPageBidMicros'] ?? 0;
                
                $low_cpc = $low_top_bid / 1000000;
                $high_cpc = $high_top_bid / 1000000;
                $avg_cpc = ($low_cpc + $high_cpc) / 2;
                
                $competition_score = $this->map_competition_level($competition);
                
                $keyword_data[$keyword_text] = array(
                    'keyword' => $keyword_text,
                    'search_volume' => intval($search_volume),
                    'competition' => $competition,
                    'competition_score' => $competition_score,
                    'avg_cpc' => round($avg_cpc, 2),
                    'low_cpc' => round($low_cpc, 2),
                    'high_cpc' => round($high_cpc, 2),
                    'commercial_intent' => $this->calculate_commercial_intent($avg_cpc, $competition_score),
                    'opportunity_score' => $this->calculate_opportunity_score($search_volume, $competition_score, $avg_cpc)
                );
            }
        }
        
        return $keyword_data;
    }
    
    private function get_access_token() {
        $cached_token = get_transient('keseo_google_access_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return true;
        }
        
        $url = 'https://oauth2.googleapis.com/token';
        
        $body = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $this->refresh_token,
            'grant_type' => 'refresh_token'
        );
        
        $response = wp_remote_post($url, array(
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['access_token'])) {
            $this->access_token = $response_body['access_token'];
            $expires_in = $response_body['expires_in'] ?? 3600;
            
            set_transient('keseo_google_access_token', $this->access_token, $expires_in - 300);
            return true;
        }
        
        return false;
    }
    
    private function is_configured() {
        return !empty($this->customer_id) && 
               !empty($this->developer_token) && 
               !empty($this->client_id) && 
               !empty($this->client_secret) && 
               !empty($this->refresh_token);
    }
    
    private function get_location_id($location) {
        $locations = array(
            'US' => '2840', 'CA' => '2124', 'UK' => '2826', 'AU' => '2036',
            'DE' => '2276', 'FR' => '2250', 'IT' => '2380', 'ES' => '2724',
            'BR' => '2076', 'IN' => '2356', 'JP' => '2392'
        );
        return $locations[$location] ?? '2840';
    }
    
    private function get_language_id($language) {
        $languages = array(
            'en' => '1000', 'es' => '1003', 'fr' => '1002', 'de' => '1001',
            'it' => '1004', 'pt' => '1014', 'ja' => '1005', 'zh' => '1017'
        );
        return $languages[$language] ?? '1000';
    }
    
    private function map_competition_level($competition) {
        switch ($competition) {
            case 'LOW': return 25;
            case 'MEDIUM': return 50;
            case 'HIGH': return 75;
            default: return 50;
        }
    }
    
    private function calculate_commercial_intent($avg_cpc, $competition_score) {
        $cpc_score = min($avg_cpc * 10, 50);
        $intent_score = ($cpc_score + $competition_score) / 2;
        return min(round($intent_score), 100);
    }
    
    private function calculate_opportunity_score($search_volume, $competition_score, $avg_cpc) {
        $volume_score = min($search_volume / 100, 50);
        $competition_penalty = $competition_score * 0.3;
        $cpc_score = min($avg_cpc * 5, 25);
        $opportunity = $volume_score - $competition_penalty + $cpc_score;
        return max(0, min(round($opportunity), 100));
    }
    
    public function test_connection() {
        if (!$this->is_configured()) {
            return array('success' => false, 'message' => 'API credentials not configured');
        }
        
        if (!$this->get_access_token()) {
            return array('success' => false, 'message' => 'Failed to get access token');
        }
        
        $test_data = $this->get_keyword_data(array('lubricants'));
        
        if ($test_data) {
            return array('success' => true, 'message' => 'Google Keyword Planner API connected successfully');
        } else {
            return array('success' => false, 'message' => 'API connection failed');
        }
    }
}

// Main Plugin Class
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
        register_setting('kelubricants_seo_group', 'keseo_auto_generate', array('default' => '1'));
        register_setting('kelubricants_seo_group', 'keseo_post_types', array('default' => array('post', 'page', 'product')));
        register_setting('kelubricants_seo_group', 'keseo_enable_schema', array('default' => '1'));
        register_setting('kelubricants_seo_group', 'keseo_enable_og_tags', array('default' => '1'));
        register_setting('kelubricants_seo_group', 'keseo_focus_keywords', array('default' => 'lubricants, automotive oil, engine oil, industrial lubricants'));
        
        // Google Ads API settings
        register_setting('kelubricants_seo_group', 'keseo_google_customer_id', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('kelubricants_seo_group', 'keseo_google_developer_token', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('kelubricants_seo_group', 'keseo_google_client_id', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('kelubricants_seo_group', 'keseo_google_client_secret', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('kelubricants_seo_group', 'keseo_google_refresh_token', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('kelubricants_seo_group', 'keseo_enable_google_validation', array('default' => '0'));
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'ke-seo-booster') !== false || 
            strpos($hook, 'post.php') !== false || 
            strpos($hook, 'post-new.php') !== false) {
            
            wp_add_inline_style('admin-bar', $this->get_admin_css());
            wp_add_inline_script('jquery', $this->get_admin_js());
            
            wp_localize_script('jquery', 'keseo_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('keseo_nonce'),
                'post_id' => isset($_GET['post']) ? intval($_GET['post']) : 0
            ));
        }
    }

    private function get_admin_css() {
        return '
        .wrap h1 { color: #1e3a8a; border-bottom: 3px solid #3b82f6; padding-bottom: 10px; margin-bottom: 30px; }
        .nav-tab-wrapper { border-bottom: 1px solid #ccc; margin-bottom: 20px; }
        .nav-tab { background: #f1f1f1; border: 1px solid #ccc; border-bottom: none; color: #555; text-decoration: none; padding: 8px 12px; margin-right: 5px; transition: all 0.3s ease; }
        .nav-tab:hover { background: #e9e9e9; color: #333; }
        .nav-tab-active { background: #fff !important; color: #000 !important; border-bottom: 1px solid #fff; margin-bottom: -1px; position: relative; z-index: 1; }
        .tab-content { background: #fff; padding: 20px; border: 1px solid #ccc; border-top: none; margin-top: -1px; }
        .form-table th { width: 200px; font-weight: 600; color: #333; }
        .form-table input[type="text"], .form-table input[type="password"], .form-table textarea { border: 1px solid #ddd; border-radius: 4px; padding: 8px 12px; font-size: 14px; transition: border-color 0.3s ease; }
        .form-table input[type="text"]:focus, .form-table input[type="password"]:focus, .form-table textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; outline: none; }
        .button { border-radius: 4px; font-weight: 500; transition: all 0.3s ease; }
        .button-primary { background: #3b82f6; border-color: #2563eb; }
        .button-primary:hover { background: #2563eb; border-color: #1d4ed8; }
        #test-api-key, #test-google-api { margin-left: 10px; background: #10b981; border-color: #059669; color: white; }
        #test-api-key:hover, #test-google-api:hover { background: #059669; border-color: #047857; }
        .success { color: #059669; font-weight: 500; }
        .error { color: #dc2626; font-weight: 500; }
        .testing { color: #f59e0b; font-weight: 500; }
        #api-test-result, #google-api-test-result { margin-left: 10px; font-size: 14px; }
        #bulk-progress { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-top: 20px; display: none; }
        .progress-bar { width: 100%; height: 20px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 10px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #1d4ed8); width: 0%; transition: width 0.3s ease; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }
        .success-message { color: #059669; font-weight: 500; padding: 10px; background: #d1fae5; border: 1px solid #a7f3d0; border-radius: 4px; animation: fadeInSuccess 0.5s ease-out; }
        .error-message { color: #dc2626; font-weight: 500; padding: 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 4px; }
        @keyframes fadeInSuccess { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .seo-analysis-widget { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin: 20px 0; }
        .seo-analysis-widget h3 { margin-top: 0; color: #1e3a8a; border-bottom: 2px solid #3b82f6; padding-bottom: 8px; }
        input[type="checkbox"] { margin-right: 8px; transform: scale(1.1); }
        .description { color: #6b7280; font-style: italic; margin-top: 5px; }
        ';
    }

    private function get_admin_js() {
        return '
        jQuery(document).ready(function($) {
            $(".nav-tab").on("click", function(e) {
                e.preventDefault();
                $(".nav-tab").removeClass("nav-tab-active");
                $(this).addClass("nav-tab-active");
                $(".tab-content").hide();
                $($(this).attr("href")).show();
            });

            $("#test-api-key").on("click", function() {
                var $button = $(this);
                var $result = $("#api-test-result");
                var apiKey = $("input[name=\"kelubricants_openai_api_key\"]").val().trim();
                
                if (!apiKey) {
                    $result.html("<span class=\"error\">Please enter an API key first</span>");
                    return;
                }
                
                $button.prop("disabled", true).text("Testing...");
                $result.html("<span class=\"testing\">Testing API connection...</span>");
                
                $.ajax({
                    url: keseo_ajax.ajax_url,
                    type: "POST",
                    data: {
                        action: "keseo_test_api",
                        api_key: apiKey,
                        nonce: keseo_ajax.nonce
                    },
                    timeout: 30000,
                    success: function(response) {
                        if (response.success) {
                            $result.html("<span class=\"success\">✓ API key is valid and working</span>");
                        } else {
                            $result.html("<span class=\"error\">✗ " + response.data + "</span>");
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html("<span class=\"error\">✗ Connection failed: " + error + "</span>");
                    },
                    complete: function() {
                        $button.prop("disabled", false).text("Test API Key");
                    }
                });
            });

            $("#test-google-api").on("click", function() {
                var $button = $(this);
                var $result = $("#google-api-test-result");
                
                $button.prop("disabled", true).text("Testing...");
                $result.html("<span class=\"testing\">Testing Google API connection...</span>");
                
                $.ajax({
                    url: keseo_ajax.ajax_url,
                    type: "POST",
                    data: {
                        action: "keseo_test_google_api",
                        nonce: keseo_ajax.nonce
                    },
                    timeout: 60000,
                    success: function(response) {
                        if (response.success) {
                            $result.html("<span class=\"success\">✓ " + response.data + "</span>");
                        } else {
                            $result.html("<span class=\"error\">✗ " + response.data + "</span>");
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html("<span class=\"error\">✗ Connection failed: " + error + "</span>");
                    },
                    complete: function() {
                        $button.prop("disabled", false).text("Test Google API Connection");
                    }
                });
            });

            $("#bulk-generate-seo").on("click", function() {
                var $button = $(this);
                var $progress = $("#bulk-progress");
                
                if (!confirm("This will generate SEO data for all posts that don\'t have it yet. This may take several minutes. Continue?")) {
                    return;
                }
                
                $button.prop("disabled", true).text("Generating...");
                $progress.show().html("<div class=\"progress-bar\"><div class=\"progress-fill\"></div></div><p>Starting bulk generation...</p>");
                
                $.ajax({
                    url: keseo_ajax.ajax_url,
                    type: "POST",
                    data: {
                        action: "keseo_bulk_generate",
                        nonce: keseo_ajax.nonce
                    },
                    timeout: 300000,
                    success: function(response) {
                        if (response.success) {
                            $progress.html("<div class=\"success-message\">✓ " + response.data + "</div>");
                        } else {
                            $progress.html("<div class=\"error-message\">✗ " + response.data + "</div>");
                        }
                    },
                    error: function(xhr, status, error) {
                        $progress.html("<div class=\"error-message\">✗ Generation failed: " + error + "</div>");
                    },
                    complete: function() {
                        $button.prop("disabled", false).text("Generate SEO for All Posts");
                    }
                });
            });
        });
        ';
    }

    public function settings_page() {
        $api_key = get_option('kelubricants_openai_api_key');
        $auto_generate = get_option('keseo_auto_generate', '1');
        $post_types = get_option('keseo_post_types', array('post', 'page', 'product'));
        $enable_schema = get_option('keseo_enable_schema', '1');
        $enable_og = get_option('keseo_enable_og_tags', '1');
        $focus_keywords = get_option('keseo_focus_keywords', '');
        $enable_google = get_option('keseo_enable_google_validation', '0');
        ?>
        <div class="wrap">
            <h1><?php _e('KE Lubricants SEO Booster Settings', 'ke-seo-booster'); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'ke-seo-booster'); ?></a>
                <a href="#google-api" class="nav-tab"><?php _e('Google API', 'ke-seo-booster'); ?></a>
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

                <div id="google-api" class="tab-content" style="display:none;">
                    <h3><?php _e('Google Keyword Planner Integration', 'ke-seo-booster'); ?></h3>
                    <p><?php _e('Connect to Google Keyword Planner for real-time keyword data validation and enhanced search volume accuracy.', 'ke-seo-booster'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Google Validation', 'ke-seo-booster'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="keseo_enable_google_validation" value="1" <?php checked($enable_google, '1'); ?> />
                                    <?php _e('Validate AI keywords with real Google Keyword Planner data', 'ke-seo-booster'); ?>
                                </label>
                                <p class="description"><?php _e('This will enhance keyword selection accuracy but requires Google Ads API access.', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Customer ID', 'ke-seo-booster'); ?></th>
                            <td>
                                <input type="text" name="keseo_google_customer_id" value="<?php echo esc_attr(get_option('keseo_google_customer_id')); ?>" style="width:300px;" placeholder="123-456-7890" />
                                <p class="description"><?php _e('Your Google Ads Customer ID (found in your Google Ads account)', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Developer Token', 'ke-seo-booster'); ?></th>
                            <td>
                                <input type="password" name="keseo_google_developer_token" value="<?php echo esc_attr(get_option('keseo_google_developer_token')); ?>" style="width:400px;" />
                                <p class="description"><?php _e('Google Ads API Developer Token', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Client ID', 'ke-seo-booster'); ?></th>
                            <td>
                                <input type="text" name="keseo_google_client_id" value="<?php echo esc_attr(get_option('keseo_google_client_id')); ?>" style="width:400px;" />
                                <p class="description"><?php _e('OAuth 2.0 Client ID from Google Cloud Console', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Client Secret', 'ke-seo-booster'); ?></th>
                            <td>
                                <input type="password" name="keseo_google_client_secret" value="<?php echo esc_attr(get_option('keseo_google_client_secret')); ?>" style="width:400px;" />
                                <p class="description"><?php _e('OAuth 2.0 Client Secret from Google Cloud Console', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Refresh Token', 'ke-seo-booster'); ?></th>
                            <td>
                                <input type="password" name="keseo_google_refresh_token" value="<?php echo esc_attr(get_option('keseo_google_refresh_token')); ?>" style="width:400px;" />
                                <p class="description"><?php _e('OAuth 2.0 Refresh Token for API access', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Test Connection', 'ke-seo-booster'); ?></th>
                            <td>
                                <button type="button" id="test-google-api" class="button"><?php _e('Test Google API Connection', 'ke-seo-booster'); ?></button>
                                <span id="google-api-test-result"></span>
                                <p class="description"><?php _e('Test your Google Ads API configuration', 'ke-seo-booster'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="google-api-setup-guide">
                        <h4><?php _e('Setup Instructions', 'ke-seo-booster'); ?></h4>
                        <ol>
                            <li><?php _e('Create a Google Cloud Project and enable the Google Ads API', 'ke-seo-booster'); ?></li>
                            <li><?php _e('Set up OAuth 2.0 credentials in Google Cloud Console', 'ke-seo-booster'); ?></li>
                            <li><?php _e('Apply for Google Ads API access and get your Developer Token', 'ke-seo-booster'); ?></li>
                            <li><?php _e('Generate a refresh token using the OAuth 2.0 playground', 'ke-seo-booster'); ?></li>
                            <li><?php _e('Enter your Google Ads Customer ID from your Google Ads account', 'ke-seo-booster'); ?></li>
                        </ol>
                        <p><a href="https://developers.google.com/google-ads/api/docs/first-call/overview" target="_blank"><?php _e('View detailed setup guide →', 'ke-seo-booster'); ?></a></p>
                    </div>
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
        if (wp_is_post_revision($post_id) || 
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            !get_option('keseo_auto_generate', '1')) {
            return;
        }

        $supported_types = get_option('keseo_post_types', array('post', 'page', 'product'));
        if (!in_array($post->post_type, $supported_types)) {
            return;
        }

        $api_key = get_option('kelubricants_openai_api_key');
        if (empty($api_key)) return;

        $existing_title = get_post_meta($post_id, '_rank_math_title', true);
        $existing_yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        
        if (!empty($existing_title) || !empty($existing_yoast_title)) {
            return;
        }

        $seo_data = $this->call_openai_api($post_id, $post, $api_key);
        
        if ($seo_data) {
            if (get_option('keseo_enable_google_validation', '0') === '1') {
                $seo_data = $this->validate_with_google($seo_data);
            }
            
            $this->update_seo_meta($post_id, $seo_data);
        }
    }

    private function call_openai_api($post_id, $post, $api_key) {
        $cache_key = 'keseo_seo_' . md5($post_id . $post->post_modified);
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        $title = get_the_title($post_id);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = get_the_excerpt($post_id);
        $focus_keywords = get_option('keseo_focus_keywords', '');
        
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
        
        $seo_content = preg_replace('/```json\s*/', '', $seo_content);
        $seo_content = preg_replace('/```\s*$/', '', $seo_content);
        
        $seo_data = json_decode($seo_content, true);

        if (!$seo_data) {
            error_log('KE SEO Booster: Failed to parse JSON response');
            return false;
        }

        set_transient($cache_key, $seo_data, 24 * HOUR_IN_SECONDS);

        return $seo_data;
    }

    private function get_market_context() {
        $context = array();
        
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
        
        $timezone = get_option('timezone_string');
        if (strpos($timezone, 'America') !== false) {
            $context[] = "North American market - emphasis on automotive and industrial applications";
        }
        
        $focus_keywords = get_option('keseo_focus_keywords', '');
        if (!empty($focus_keywords)) {
            $context[] = "Business focus areas: " . $focus_keywords;
        }
        
        return implode('. ', $context);
    }

    private function validate_with_google($seo_data) {
        global $keseo_google_ads_api;
        
        if (!$keseo_google_ads_api) {
            return $seo_data;
        }
        
        $keywords_to_validate = array();
        
        if (!empty($seo_data['focus_keyword'])) {
            $keywords_to_validate[] = $seo_data['focus_keyword'];
        }
        
        if (!empty($seo_data['seo_tags'])) {
            $tags = array_map('trim', explode(',', $seo_data['seo_tags']));
            $keywords_to_validate = array_merge($keywords_to_validate, array_slice($tags, 0, 5));
        }
        
        if (empty($keywords_to_validate)) {
            return $seo_data;
        }
        
        $google_data = $keseo_google_ads_api->get_keyword_data($keywords_to_validate);
        
        if (!$google_data) {
            return $seo_data;
        }
        
        $best_keyword = $this->select_best_keyword($google_data);
        
        if ($best_keyword) {
            $seo_data['focus_keyword'] = $best_keyword['keyword'];
            
            $seo_data['google_validation'] = array(
                'search_volume' => $best_keyword['search_volume'],
                'competition' => $best_keyword['competition'],
                'avg_cpc' => $best_keyword['avg_cpc'],
                'opportunity_score' => $best_keyword['opportunity_score'],
                'validation_status' => 'validated'
            );
            
            $validated_tags = array();
            foreach ($google_data as $keyword => $data) {
                if ($data['opportunity_score'] > 30) {
                    $validated_tags[] = $keyword;
                }
            }
            
            if (!empty($validated_tags)) {
                $seo_data['seo_tags'] = implode(', ', array_slice($validated_tags, 0, 8));
            }
        }
        
        return $seo_data;
    }
    
    private function select_best_keyword($google_data) {
        if (empty($google_data)) {
            return null;
        }
        
        $best_keyword = null;
        $best_score = 0;
        
        foreach ($google_data as $keyword => $data) {
            $volume_score = min($data['search_volume'] / 100, 50);
            $opportunity_score = $data['opportunity_score'];
            $competition_penalty = $data['competition_score'] * 0.3;
            
            $total_score = $volume_score + $opportunity_score - $competition_penalty;
            
            if ($total_score > $best_score && $data['search_volume'] >= 10) {
                $best_score = $total_score;
                $best_keyword = $data;
            }
        }
        
        return $best_keyword;
    }

    private function update_seo_meta($post_id, $seo_data) {
        if (is_plugin_active('seo-by-rank-math/rank-math.php')) {
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($seo_data['meta_title'] ?? ''));
            update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($seo_data['meta_description'] ?? ''));
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($seo_data['focus_keyword'] ?? ''));
        }

        if (is_plugin_active('wordpress-seo/wp-seo.php')) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($seo_data['meta_title'] ?? ''));
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($seo_data['meta_description'] ?? ''));
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($seo_data['focus_keyword'] ?? ''));
        }

        update_post_meta($post_id, '_keseo_title', sanitize_text_field($seo_data['meta_title'] ?? ''));
        update_post_meta($post_id, '_keseo_description', sanitize_textarea_field($seo_data['meta_description'] ?? ''));
        update_post_meta($post_id, '_keseo_focus_keyword', sanitize_text_field($seo_data['focus_keyword'] ?? ''));
        update_post_meta($post_id, '_keseo_schema_type', sanitize_text_field($seo_data['schema_type'] ?? 'Article'));
        update_post_meta($post_id, '_keseo_og_title', sanitize_text_field($seo_data['og_title'] ?? ''));
        update_post_meta($post_id, '_keseo_og_description', sanitize_textarea_field($seo_data['og_description'] ?? ''));

        if (!empty($seo_data['seo_tags'])) {
            $tags = array_map('trim', explode(',', $seo_data['seo_tags']));
            wp_set_post_terms($post_id, $tags, 'post_tag', false);
        }

        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id && !empty($seo_data['image_alt_text'])) {
            update_post_meta($thumbnail_id, '_wp_attachment_image_alt', sanitize_text_field($seo_data['image_alt_text']));
        }

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

        if ($schema_type === 'Product') {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => get_bloginfo('name')
            );
        }

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
        $entry['lastmod'] = get_the_modified_date('c', $post);
        
        if ($post_type === 'product') {
            $entry['priority'] = '0.8';
        } elseif ($post_type === 'post') {
            $days_old = (time() - strtotime($post->post_date)) / (60 * 60 * 24);
            $entry['priority'] = $days_old < 30 ? '0.8' : '0.6';
        }
        
        return $entry;
    }

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
            
            sleep(1);
        }

        wp_send_json_success("Generated SEO data for {$generated} posts.");
    }
}

// Initialize the Google Ads API integration
$keseo_google_ads_api = new KELubricantsGoogleAdsAPI();

// Initialize the plugin
new KELubricantsSEOBooster();

// AJAX Handlers
add_action('wp_ajax_keseo_test_api', 'keseo_test_api_key');
add_action('wp_ajax_keseo_test_google_api', 'keseo_test_google_api');

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

function keseo_test_google_api() {
    check_ajax_referer('keseo_nonce', 'nonce');
    
    global $keseo_google_ads_api;
    
    if (!$keseo_google_ads_api) {
        wp_send_json_error('Google API integration not initialized');
        return;
    }
    
    $test_result = $keseo_google_ads_api->test_connection();
    
    if ($test_result['success']) {
        wp_send_json_success($test_result['message']);
    } else {
        wp_send_json_error($test_result['message']);
    }
}

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
    add_option('keseo_auto_generate', '1');
    add_option('keseo_enable_schema', '1');
    add_option('keseo_enable_og_tags', '1');
    add_option('keseo_post_types', array('post', 'page', 'product'));
    add_option('keseo_focus_keywords', 'lubricants, automotive oil, engine oil, industrial lubricants');
    add_option('keseo_enable_google_validation', '0');
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'keseo_deactivation');
function keseo_deactivation() {
    wp_clear_scheduled_hook('keseo_daily_analysis');
}
?>