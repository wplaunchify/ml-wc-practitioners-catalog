# ML WooCommerce Practitioners - Installation Guide

## 🎯 What This Plugin Does

This plugin gives you a **complete Professional Nutritionals supplement store** in just a few clicks:

1. **Auto-installs WooCommerce** (if needed) - NO setup wizard!
2. **Imports 196 products** from GitHub with pricing, descriptions, and images
3. **Downloads all product images** to your WordPress media library
4. **Creates WooCommerce products** with all metadata and categories
5. **Ready to sell** - fully configured supplement store

## 📦 Installation Steps

### Step 1: Upload Plugin

1. Download `ml-wc-practitioners.zip` from this repository
2. Go to your WordPress admin: **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Step 2: Install WooCommerce (If Needed)

If WooCommerce isn't installed, you'll see a yellow notice with a button:

**"Install WooCommerce Now"**

Click it! The plugin will:
- Download and install WooCommerce
- Activate it automatically
- **Bypass the entire setup wizard** (no annoying questions!)
- Set sensible defaults for a supplement store
- Reload the page when done

### Step 3: Import Product Catalog

1. Go to **PN Catalog** in your WordPress admin menu
2. The GitHub token is already configured (you can change it if needed)
3. Click **"Import Catalog from GitHub"**
4. Watch the progress bar as it:
   - Downloads product database from GitHub
   - Creates 196 WooCommerce products
   - Downloads and attaches all product images
   - Sets up categories and metadata

### Step 4: Done!

Your store is ready! Go to **Products** to see all 196 Professional Nutritionals supplements.

## ⚙️ Configuration

### GitHub Token (Optional)

The plugin comes with a default GitHub token that works. But if you want to use your own:

1. Go to **PN Catalog → Settings**
2. Enter your GitHub Personal Access Token
3. Click **Save Settings**
4. Click **Test Connection** to verify

### Product Customization

After import, all products are stored locally in your WordPress site. You can:
- Edit product images
- Modify descriptions
- Adjust pricing
- Add custom fields
- Change categories

The plugin only imports once - after that, your site is completely independent.

## 🔧 What Gets Bypassed

This plugin automatically disables all the annoying WooCommerce stuff:

- ✅ Setup wizard - SKIPPED
- ✅ Onboarding tasks - HIDDEN
- ✅ Task list - DISABLED
- ✅ Marketplace suggestions - OFF
- ✅ Store setup prompts - GONE

You get a clean, ready-to-use WooCommerce install with no hassle.

## 📊 What Gets Imported

**196 Professional Nutritionals Products** including:

- Product names and SKUs
- Wholesale and retail pricing
- Profit margins
- Full descriptions
- Ingredients lists
- Benefits and directions
- Warnings and disclaimers
- Product categories
- 500x500 product images (matted and centered)

## 🆘 Troubleshooting

### "Could not connect to GitHub"

- Check your internet connection
- Verify the GitHub token is valid
- Make sure the repository is accessible

### "WooCommerce installation failed"

- Check you have permission to install plugins
- Try installing WooCommerce manually from Plugins → Add New
- Then activate this plugin

### "Import timed out"

- The import might still be running in the background
- Refresh the page and check your Products list
- If needed, click Import again (it won't duplicate products)

## 📁 Files Included

- `ml-wc-practitioners.php` - Main plugin file (single-file architecture)
- `ml-wc-practitioners.zip` - Ready-to-upload WordPress plugin
- `INSTALLATION-GUIDE.md` - This file

## 🔗 GitHub Repository

All product data and images are stored in:
- **Repository:** `wplaunchify/ml-stuarthoover`
- **Branch:** `main`
- **Catalog:** `professional-nutritionals/catalog/`
- **Images:** `professional-nutritionals/images/`

## 🎉 That's It!

You now have a complete Professional Nutritionals supplement store ready to go. No inventory, no hassle, just products ready to sell.

---

**Version:** 1.1.0  
**Author:** Spencer Forman  
**Support:** https://minutelaunch.ai

