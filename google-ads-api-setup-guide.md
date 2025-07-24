# Google Ads API Integration Setup Guide

This guide will help you set up Google Ads API integration for real-time keyword validation in the KE Lubricants SEO Booster plugin.

## Why Use Google Ads API Integration?

✅ **Real-time keyword validation** with actual search volumes
✅ **Accurate competition data** from Google's own systems  
✅ **Commercial intent insights** through CPC and bidding data
✅ **Opportunity scoring** to prioritize the best keywords
✅ **Enhanced AI accuracy** by validating AI suggestions with real data

## Prerequisites

- Google Ads account (can be inactive, just needs to exist)
- Google Cloud Platform account
- Basic understanding of API credentials

## Step-by-Step Setup

### Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the **Google Ads API** in the API Library
4. Go to **APIs & Services > Library**
5. Search for "Google Ads API" and enable it

### Step 2: Set Up OAuth 2.0 Credentials

1. In Google Cloud Console, go to **APIs & Services > Credentials**
2. Click **+ CREATE CREDENTIALS** > **OAuth 2.0 Client IDs**
3. Configure consent screen if prompted:
   - User Type: **External**
   - App name: **KE SEO Booster**
   - User support email: Your email
   - Scopes: Add `https://www.googleapis.com/auth/adwords`
4. Create OAuth 2.0 Client ID:
   - Application type: **Web application**
   - Name: **KE SEO Plugin**
   - Authorized redirect URIs: `https://developers.google.com/oauthplayground`

**Save your Client ID and Client Secret** - you'll need these later.

### Step 3: Apply for Google Ads API Access

1. Go to [Google Ads API Center](https://ads.google.com/nav/selectaccount?authuser=0&dst=%2Fintl%2Fen_us%2Fhome%2Ftools%2Fmanager-accounts%2F)
2. Apply for API access (this can take 24-48 hours)
3. Once approved, get your **Developer Token** from the API Center

### Step 4: Generate Refresh Token

1. Go to [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)
2. Click the **settings gear** icon (top right)
3. Check **"Use your own OAuth credentials"**
4. Enter your **Client ID** and **Client Secret**
5. In the left panel, find **Google Ads API v15**
6. Select scope: `https://www.googleapis.com/auth/adwords`
7. Click **"Authorize APIs"**
8. Sign in and authorize access
9. Click **"Exchange authorization code for tokens"**
10. **Copy the Refresh Token** - you'll need this

### Step 5: Get Your Customer ID

1. Log in to your [Google Ads account](https://ads.google.com/)
2. Look at the top right corner for your Customer ID (format: 123-456-7890)
3. **Remove the dashes** - use format: 1234567890

### Step 6: Configure Plugin Settings

1. In WordPress admin, go to **Settings > KE SEO Booster > Google API**
2. Enter your credentials:
   - **Customer ID**: 1234567890 (no dashes)
   - **Developer Token**: From Google Ads API Center
   - **Client ID**: From Google Cloud Console
   - **Client Secret**: From Google Cloud Console  
   - **Refresh Token**: From OAuth Playground
3. Check **"Enable Google Validation"**
4. Click **"Test Google API Connection"**
5. Save settings

## Troubleshooting

### Common Issues

**Error: "Customer not found"**
- Double-check your Customer ID format (no dashes)
- Ensure the Google account has access to the Ads account

**Error: "Developer token not approved"**
- Wait for Google to approve your API access (24-48 hours)
- Check your application status in Google Ads API Center

**Error: "Invalid refresh token"**
- Regenerate the refresh token using OAuth Playground
- Ensure you selected the correct Google Ads API scope

**Error: "Insufficient permissions"**
- Make sure your Google account has access to the Ads account
- Check that API access is approved

### API Limits

- **Rate Limits**: 15,000 operations per hour
- **Daily Limits**: Varies by account history
- The plugin automatically handles rate limiting with delays

### Cost Considerations

- Google Ads API is **free** for keyword research
- You don't need to run active ads
- No charges for API calls

## Testing Your Setup

Use the **"Test Google API Connection"** button to verify:
- ✅ API credentials are valid
- ✅ Connection is working
- ✅ Sample keyword data is retrieved

## How It Works in the Plugin

### Automatic Validation
When enabled, the plugin will:
1. Generate keywords using AI (OpenAI)
2. Validate keywords with Google Ads API
3. Select the best keyword based on:
   - Search volume
   - Competition level
   - Commercial intent (CPC)
   - Opportunity score
4. Update SEO data with validated keywords

### Manual Testing
- Use the **SEO Preview** feature to see validated keywords
- Check the **SEO Analysis** page for coverage statistics
- Review keyword performance in WordPress admin

## Security Best Practices

- Store credentials securely (plugin handles this automatically)
- Use environment variables for production sites
- Rotate tokens periodically
- Monitor API usage in Google Cloud Console

## Support Resources

- [Google Ads API Documentation](https://developers.google.com/google-ads/api/docs)
- [OAuth 2.0 Setup Guide](https://developers.google.com/google-ads/api/docs/oauth/overview)
- [API Limits and Quotas](https://developers.google.com/google-ads/api/docs/best-practices/quotas)
- [Error Code Reference](https://developers.google.com/google-ads/api/reference/rpc)

## Benefits After Setup

✨ **Higher Accuracy**: Real search volumes instead of estimates
✨ **Better Keywords**: AI suggestions validated with real data
✨ **Commercial Intent**: Identify keywords with purchasing intent
✨ **Competitive Edge**: Know exactly what Google sees for search volume
✨ **Time Savings**: Automated validation eliminates manual checking

---

## Example API Response

Once configured, you'll see enhanced keyword data like:

```json
{
  "keyword": "synthetic motor oil",
  "search_volume": 2900,
  "competition": "MEDIUM",
  "avg_cpc": 2.45,
  "opportunity_score": 85,
  "validation_status": "high_opportunity",
  "recommendation": "High priority - excellent opportunity"
}
```

This real-time data ensures your SEO strategy targets keywords with actual search demand and commercial viability.