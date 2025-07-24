jQuery(document).ready(function($) {
    'use strict';

    // Tab switching functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').hide();
        $($(this).attr('href')).show();
    });

    // API key testing
    $('#test-api-key').on('click', function() {
        var $button = $(this);
        var $result = $('#api-test-result');
        var apiKey = $('input[name="kelubricants_openai_api_key"]').val().trim();
        
        if (!apiKey) {
            $result.html('<span class="error">Please enter an API key first</span>');
            return;
        }
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('<span class="testing">Testing API connection...</span>');
        
        $.ajax({
            url: keseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'keseo_test_api',
                api_key: apiKey,
                nonce: keseo_ajax.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    $result.html('<span class="success">✓ API key is valid and working</span>');
                } else {
                    $result.html('<span class="error">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span class="error">✗ Connection failed: ' + error + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test API Key');
            }
        });
    });

    // Google API testing
    $('#test-google-api').on('click', function() {
        var $button = $(this);
        var $result = $('#google-api-test-result');
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('<span class="testing">Testing Google API connection...</span>');
        
        $.ajax({
            url: keseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'keseo_test_google_api',
                nonce: keseo_ajax.nonce
            },
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    $result.html('<span class="success">✓ ' + response.data + '</span>');
                } else {
                    $result.html('<span class="error">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span class="error">✗ Connection failed: ' + error + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Google API Connection');
            }
        });
    });

    // Bulk SEO generation
    $('#bulk-generate-seo').on('click', function() {
        var $button = $(this);
        var $progress = $('#bulk-progress');
        
        if (!confirm('This will generate SEO data for all posts that don\'t have it yet. This may take several minutes. Continue?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Generating...');
        $progress.show().html('<div class="progress-bar"><div class="progress-fill"></div></div><p>Starting bulk generation...</p>');
        
        $.ajax({
            url: keseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'keseo_bulk_generate',
                nonce: keseo_ajax.nonce
            },
            timeout: 300000, // 5 minutes
            success: function(response) {
                if (response.success) {
                    $progress.html('<div class="success-message">✓ ' + response.data + '</div>');
                } else {
                    $progress.html('<div class="error-message">✗ ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $progress.html('<div class="error-message">✗ Generation failed: ' + error + '</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Generate SEO for All Posts');
            }
        });
    });

    // Real-time SEO preview (for individual posts)
    function generateSEOPreview(postId) {
        var $preview = $('#keseo-preview');
        if (!$preview.length) return;
        
        $preview.html('<p>Generating SEO preview...</p>');
        
        $.ajax({
            url: keseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'keseo_generate_preview',
                post_id: postId,
                nonce: keseo_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var seo = response.data;
                    var html = '<div class="seo-preview-container">';
                    html += '<h4>AI-Generated SEO Preview</h4>';
                    html += '<div class="seo-item"><strong>Title:</strong> ' + escapeHtml(seo.meta_title) + '</div>';
                    html += '<div class="seo-item"><strong>Description:</strong> ' + escapeHtml(seo.meta_description) + '</div>';
                    html += '<div class="seo-item"><strong>Focus Keyword:</strong> ' + escapeHtml(seo.focus_keyword) + '</div>';
                    html += '<div class="seo-item"><strong>Tags:</strong> ' + escapeHtml(seo.seo_tags) + '</div>';
                    html += '<div class="seo-item"><strong>Schema Type:</strong> ' + escapeHtml(seo.schema_type) + '</div>';
                    html += '</div>';
                    $preview.html(html);
                } else {
                    $preview.html('<p class="error">Failed to generate SEO preview</p>');
                }
            },
            error: function() {
                $preview.html('<p class="error">Error generating preview</p>');
            }
        });
    }

    // Add SEO preview button to post edit screens
    if ($('#post').length) {
        var postId = $('#post_ID').val();
        if (postId) {
            $('#postdivrich').after('<div id="keseo-preview-wrap"><button type="button" id="generate-seo-preview" class="button">Generate SEO Preview</button><div id="keseo-preview"></div></div>');
            
            $('#generate-seo-preview').on('click', function() {
                generateSEOPreview(postId);
            });
        }
    }

    // Character count for meta fields
    function updateCharCount(input, counter, limit, warningAt) {
        var length = input.val().length;
        var remaining = limit - length;
        var color = length > warningAt ? (length > limit ? 'red' : 'orange') : 'green';
        
        counter.text(remaining + ' characters remaining')
               .css('color', color);
    }

    // Add character counters to meta fields if they exist
    $('input[name*="title"], textarea[name*="description"]').each(function() {
        var $input = $(this);
        var isTitle = $input.attr('name').indexOf('title') !== -1;
        var limit = isTitle ? 60 : 155;
        var warningAt = isTitle ? 50 : 140;
        var $counter = $('<div class="char-counter"></div>');
        
        $input.after($counter);
        updateCharCount($input, $counter, limit, warningAt);
        
        $input.on('input keyup', function() {
            updateCharCount($input, $counter, limit, warningAt);
        });
    });

    // SEO Score Calculator
    function calculateSEOScore(title, description, content, keyword) {
        var score = 0;
        var suggestions = [];

        // Title checks
        if (title.length >= 30 && title.length <= 60) {
            score += 20;
        } else {
            suggestions.push('Title should be 30-60 characters long');
        }

        // Description checks
        if (description.length >= 120 && description.length <= 155) {
            score += 20;
        } else {
            suggestions.push('Meta description should be 120-155 characters long');
        }

        // Keyword in title
        if (keyword && title.toLowerCase().includes(keyword.toLowerCase())) {
            score += 15;
        } else if (keyword) {
            suggestions.push('Include focus keyword in title');
        }

        // Keyword in description
        if (keyword && description.toLowerCase().includes(keyword.toLowerCase())) {
            score += 15;
        } else if (keyword) {
            suggestions.push('Include focus keyword in meta description');
        }

        // Content length
        if (content.length >= 300) {
            score += 10;
        } else {
            suggestions.push('Content should be at least 300 words');
        }

        // Keyword density in content
        if (keyword && content.toLowerCase().includes(keyword.toLowerCase())) {
            var density = (content.toLowerCase().split(keyword.toLowerCase()).length - 1) / content.split(' ').length * 100;
            if (density >= 0.5 && density <= 2.5) {
                score += 10;
            } else if (density > 2.5) {
                suggestions.push('Keyword density too high (should be 0.5-2.5%)');
            } else {
                suggestions.push('Include focus keyword in content');
            }
        }

        // Readability
        var avgWordsPerSentence = content.split(' ').length / content.split('.').length;
        if (avgWordsPerSentence <= 20) {
            score += 10;
        } else {
            suggestions.push('Use shorter sentences for better readability');
        }

        return { score: score, suggestions: suggestions };
    }

    // Display SEO score if elements exist
    function displaySEOScore() {
        var title = $('#title, input[name*="title"]').val() || '';
        var description = $('textarea[name*="description"]').val() || '';
        var content = $('#content').val() || '';
        var keyword = $('input[name*="keyword"]').val() || '';

        if (title || description || content) {
            var result = calculateSEOScore(title, description, content, keyword);
            var $scoreDisplay = $('#seo-score-display');
            
            if (!$scoreDisplay.length) {
                $scoreDisplay = $('<div id="seo-score-display" class="seo-score-widget"></div>');
                $('#postdivrich, .edit-post-header').after($scoreDisplay);
            }

            var scoreColor = result.score >= 80 ? 'green' : result.score >= 60 ? 'orange' : 'red';
            var html = '<h4>SEO Score: <span style="color:' + scoreColor + '">' + result.score + '/100</span></h4>';
            
            if (result.suggestions.length > 0) {
                html += '<h5>Suggestions:</h5><ul>';
                result.suggestions.forEach(function(suggestion) {
                    html += '<li>' + suggestion + '</li>';
                });
                html += '</ul>';
            }
            
            $scoreDisplay.html(html);
        }
    }

    // Update SEO score on content changes
    $('#title, #content, input[name*="title"], textarea[name*="description"], input[name*="keyword"]').on('input keyup', debounce(displaySEOScore, 1000));

    // Utility function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

    // Debounce function
    function debounce(func, wait) {
        var timeout;
        return function executedFunction(...args) {
            var later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Enhanced form validation
    $('form').on('submit', function(e) {
        var apiKey = $('input[name="kelubricants_openai_api_key"]').val();
        var autoGenerate = $('input[name="keseo_auto_generate"]').is(':checked');
        
        if (autoGenerate && !apiKey) {
            e.preventDefault();
            alert('Please provide an OpenAI API key if auto-generation is enabled.');
            $('input[name="kelubricants_openai_api_key"]').focus();
            return false;
        }
    });

    // Initialize on page load
    displaySEOScore();
});