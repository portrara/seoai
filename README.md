# KE Lubricants SEO Booster

A WordPress plugin that automatically generates SEO-optimized meta titles, descriptions, tags, and image alt-text using OpenAI's GPT-4 API. Specifically designed for lubricants and automotive product websites.

## Features

- **Auto-generation**: Automatically creates SEO content when posts are saved
- **Multi-SEO Plugin Support**: Compatible with Rank Math and Yoast SEO
- **Customizable**: Choose which post types to optimize
- **Manual Generation**: Bulk generate SEO data for existing posts
- **API Testing**: Built-in API connection testing
- **Error Handling**: Comprehensive error logging and handling
- **Security**: Proper input sanitization and nonce verification

## What It Generates

1. **Meta Title** (50-60 characters) - SEO-optimized titles with primary keywords
2. **Meta Description** (150-155 characters) - Compelling descriptions for search results
3. **SEO Tags** (5-8 tags) - Relevant tags for better categorization
4. **Image Alt-Text** - Descriptive alt-text for featured images

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Go to **Settings > KE SEO Booster** to configure

## Configuration

### Required Setup

1. **OpenAI API Key**: 
   - Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
   - Enter it in the plugin settings
   - Use the "Test API Connection" button to verify

### Optional Settings

- **Auto-generate SEO**: Enable/disable automatic generation on post save
- **Post Types**: Select which post types to optimize (posts, pages, products, etc.)
- **GPT Model**: Choose between GPT-4 Turbo, GPT-4, or GPT-3.5 Turbo
- **Temperature**: Control AI creativity (0 = focused, 1 = creative)

## Usage

### Automatic Generation
Once configured, the plugin automatically generates SEO data when you save posts of the selected types.

### Manual Generation
Use the "Generate SEO for All Posts" button in settings to bulk process existing content.

### SEO Plugin Integration

The plugin works with:
- **Rank Math**: Updates `_rank_math_title` and `_rank_math_description` meta fields
- **Yoast SEO**: Updates `_yoast_wpseo_title` and `_yoast_wpseo_metadesc` meta fields

## File Structure

```
ke-lubricants-seo-booster/
├── ke-lubricants-seo-booster.php          # Original basic version
├── ke-lubricants-seo-booster-enhanced.php # Enhanced version with features
└── README.md                              # This file
```

## API Costs

The plugin uses OpenAI's API which has usage-based pricing:
- **GPT-4 Turbo**: ~$0.01-0.03 per request
- **GPT-4**: ~$0.03-0.06 per request  
- **GPT-3.5 Turbo**: ~$0.002 per request

Costs depend on content length and model choice.

## Security Features

- Input sanitization for all user inputs
- Nonce verification for AJAX requests
- Proper escaping of output data
- API key stored securely in WordPress options

## Error Handling

The plugin includes comprehensive error handling:
- API connection failures
- Invalid JSON responses
- Missing API keys
- WordPress errors

All errors are logged to WordPress error logs for debugging.

## Troubleshooting

### Common Issues

1. **No SEO data generated**:
   - Check if API key is valid
   - Verify post type is enabled in settings
   - Check WordPress error logs

2. **API test fails**:
   - Verify API key is correct
   - Check internet connection
   - Ensure OpenAI API is accessible

3. **SEO data not showing in SEO plugin**:
   - Confirm you're using Rank Math or Yoast SEO
   - Check if meta fields are being updated in post meta

### Debug Mode

Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For issues or questions:
1. Check WordPress error logs
2. Test API connection in plugin settings
3. Verify plugin compatibility with your theme/plugins

## Version History

- **1.1 (Enhanced)**: Added manual generation, API testing, multiple post type support, better error handling
- **1.0 (Original)**: Basic auto-generation functionality

## License

This plugin is provided as-is for the KE Lubricants website. Modify as needed for your specific requirements.