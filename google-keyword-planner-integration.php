<?php
/**
 * Google Keyword Planner API Integration
 * Provides real-time keyword data validation and enhancement
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    }
    
    /**
     * Get real-time keyword data from Google Keyword Planner
     */
    public function get_keyword_data($keywords, $location = 'US', $language = 'en') {
        if (empty($keywords) || !$this->is_configured()) {
            return false;
        }
        
        // Ensure we have a valid access token
        if (!$this->get_access_token()) {
            error_log('KE SEO: Failed to get Google Ads access token');
            return false;
        }
        
        $keyword_data = array();
        
        // Process keywords in batches of 10 (API limit)
        $keyword_batches = array_chunk($keywords, 10);
        
        foreach ($keyword_batches as $batch) {
            $batch_data = $this->fetch_keyword_batch($batch, $location, $language);
            if ($batch_data) {
                $keyword_data = array_merge($keyword_data, $batch_data);
            }
            
            // Add delay to respect rate limits
            sleep(1);
        }
        
        return $keyword_data;
    }
    
    /**
     * Fetch keyword data for a batch of keywords
     */
    private function fetch_keyword_batch($keywords, $location, $language) {
        $url = "https://googleads.googleapis.com/v15/customers/{$this->customer_id}/keywordPlanIdeas:generateKeywordIdeas";
        
        // Prepare keyword seeds
        $keyword_seeds = array();
        foreach ($keywords as $keyword) {
            $keyword_seeds[] = array('text' => trim($keyword));
        }
        
        // Request body
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
            'Authorization: Bearer ' . $this->access_token,
            'Developer-Token: ' . $this->developer_token,
            'Content-Type: application/json'
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
    
    /**
     * Parse Google Ads API response
     */
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
                
                // Convert micros to dollars
                $low_cpc = $low_top_bid / 1000000;
                $high_cpc = $high_top_bid / 1000000;
                $avg_cpc = ($low_cpc + $high_cpc) / 2;
                
                // Map competition levels
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
    
    /**
     * Get fresh access token using refresh token
     */
    private function get_access_token() {
        // Check if we have a cached valid token
        $cached_token = get_transient('keseo_google_access_token');
        if ($cached_token) {
            $this->access_token = $cached_token;
            return true;
        }
        
        // Get new access token
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
            
            // Cache the token for slightly less than its expiry time
            set_transient('keseo_google_access_token', $this->access_token, $expires_in - 300);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if API is properly configured
     */
    private function is_configured() {
        return !empty($this->customer_id) && 
               !empty($this->developer_token) && 
               !empty($this->client_id) && 
               !empty($this->client_secret) && 
               !empty($this->refresh_token);
    }
    
    /**
     * Get location ID for geo-targeting
     */
    private function get_location_id($location) {
        $locations = array(
            'US' => '2840',
            'CA' => '2124',
            'UK' => '2826',
            'AU' => '2036',
            'DE' => '2276',
            'FR' => '2250',
            'IT' => '2380',
            'ES' => '2724',
            'BR' => '2076',
            'IN' => '2356',
            'JP' => '2392'
        );
        
        return $locations[$location] ?? '2840'; // Default to US
    }
    
    /**
     * Get language ID
     */
    private function get_language_id($language) {
        $languages = array(
            'en' => '1000',
            'es' => '1003',
            'fr' => '1002',
            'de' => '1001',
            'it' => '1004',
            'pt' => '1014',
            'ja' => '1005',
            'zh' => '1017'
        );
        
        return $languages[$language] ?? '1000'; // Default to English
    }
    
    /**
     * Map competition level to numeric score
     */
    private function map_competition_level($competition) {
        switch ($competition) {
            case 'LOW':
                return 25;
            case 'MEDIUM':
                return 50;
            case 'HIGH':
                return 75;
            default:
                return 50;
        }
    }
    
    /**
     * Calculate commercial intent score
     */
    private function calculate_commercial_intent($avg_cpc, $competition_score) {
        // Higher CPC and competition usually indicate commercial intent
        $cpc_score = min($avg_cpc * 10, 50); // Cap at 50
        $intent_score = ($cpc_score + $competition_score) / 2;
        
        return min(round($intent_score), 100);
    }
    
    /**
     * Calculate opportunity score
     */
    private function calculate_opportunity_score($search_volume, $competition_score, $avg_cpc) {
        // Higher volume is good, lower competition is good, moderate CPC is good
        $volume_score = min($search_volume / 1000, 50); // Normalize volume
        $competition_penalty = $competition_score * 0.5; // Lower is better
        $cpc_score = min($avg_cpc * 5, 25); // Sweet spot around $2-5
        
        $opportunity = $volume_score - $competition_penalty + $cpc_score;
        
        return max(0, min(round($opportunity), 100));
    }
    
    /**
     * Validate keywords with real Google data
     */
    public function validate_ai_keywords($ai_keywords, $location = 'US') {
        if (empty($ai_keywords)) {
            return array();
        }
        
        // Extract just the keyword strings if array of objects
        $keyword_strings = array();
        foreach ($ai_keywords as $keyword) {
            if (is_string($keyword)) {
                $keyword_strings[] = $keyword;
            } elseif (is_array($keyword) && isset($keyword['keyword'])) {
                $keyword_strings[] = $keyword['keyword'];
            }
        }
        
        $google_data = $this->get_keyword_data($keyword_strings, $location);
        
        if (!$google_data) {
            return $ai_keywords; // Return original if validation fails
        }
        
        $validated_keywords = array();
        
        foreach ($ai_keywords as $index => $ai_keyword) {
            $keyword_text = is_string($ai_keyword) ? $ai_keyword : $ai_keyword['keyword'];
            
            if (isset($google_data[$keyword_text])) {
                $google_info = $google_data[$keyword_text];
                
                $validated_keywords[] = array(
                    'keyword' => $keyword_text,
                    'ai_suggested' => true,
                    'search_volume' => $google_info['search_volume'],
                    'competition' => $google_info['competition'],
                    'competition_score' => $google_info['competition_score'],
                    'avg_cpc' => $google_info['avg_cpc'],
                    'commercial_intent' => $google_info['commercial_intent'],
                    'opportunity_score' => $google_info['opportunity_score'],
                    'validation_status' => $this->get_validation_status($google_info),
                    'recommendation' => $this->get_keyword_recommendation($google_info)
                );
            } else {
                // Keep AI keyword but mark as unvalidated
                $validated_keywords[] = array(
                    'keyword' => $keyword_text,
                    'ai_suggested' => true,
                    'validation_status' => 'unvalidated',
                    'recommendation' => 'Manual review recommended - no Google data available'
                );
            }
        }
        
        return $validated_keywords;
    }
    
    /**
     * Get validation status based on Google data
     */
    private function get_validation_status($google_info) {
        $volume = $google_info['search_volume'];
        $opportunity = $google_info['opportunity_score'];
        
        if ($volume < 10) {
            return 'low_volume';
        } elseif ($opportunity > 60) {
            return 'high_opportunity';
        } elseif ($opportunity > 30) {
            return 'good_opportunity';
        } else {
            return 'challenging';
        }
    }
    
    /**
     * Get keyword recommendation
     */
    private function get_keyword_recommendation($google_info) {
        $volume = $google_info['search_volume'];
        $competition = $google_info['competition_score'];
        $opportunity = $google_info['opportunity_score'];
        
        if ($volume < 10) {
            return 'Consider for long-tail strategy - very low search volume';
        } elseif ($opportunity > 70) {
            return 'High priority - excellent opportunity';
        } elseif ($opportunity > 50) {
            return 'Good target - moderate opportunity';
        } elseif ($competition > 70) {
            return 'High competition - requires strong content strategy';
        } else {
            return 'Research further - mixed signals';
        }
    }
    
    /**
     * Get suggested keywords from Google (beyond AI suggestions)
     */
    public function get_suggested_keywords($seed_keywords, $limit = 50, $location = 'US') {
        if (!$this->is_configured()) {
            return array();
        }
        
        // Get broader keyword suggestions from Google
        $suggestions = $this->fetch_keyword_suggestions($seed_keywords, $limit, $location);
        
        // Filter and score suggestions
        $filtered_suggestions = array();
        foreach ($suggestions as $suggestion) {
            if ($suggestion['search_volume'] >= 10 && $suggestion['opportunity_score'] > 20) {
                $filtered_suggestions[] = $suggestion;
            }
        }
        
        // Sort by opportunity score
        usort($filtered_suggestions, function($a, $b) {
            return $b['opportunity_score'] - $a['opportunity_score'];
        });
        
        return array_slice($filtered_suggestions, 0, $limit);
    }
    
    /**
     * Fetch keyword suggestions from Google Ads API
     */
    private function fetch_keyword_suggestions($seed_keywords, $limit, $location) {
        // This would use the same API but with broader parameters
        // to get more keyword ideas beyond the AI suggestions
        return $this->get_keyword_data($seed_keywords, $location);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'API credentials not configured'
            );
        }
        
        if (!$this->get_access_token()) {
            return array(
                'success' => false,
                'message' => 'Failed to get access token'
            );
        }
        
        // Test with a simple keyword
        $test_data = $this->get_keyword_data(array('lubricants'));
        
        if ($test_data) {
            return array(
                'success' => true,
                'message' => 'Google Keyword Planner API connected successfully',
                'test_data' => $test_data
            );
        } else {
            return array(
                'success' => false,
                'message' => 'API connection failed'
            );
        }
    }
}

// Initialize the Google Ads API integration
$keseo_google_ads_api = new KELubricantsGoogleAdsAPI();