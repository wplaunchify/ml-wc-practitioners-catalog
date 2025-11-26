# Changelog - ML WooCommerce Practitioners

## Version 2.0.3 - November 26, 2025

### 🔧 Bug Fix

**Fixed Permission Error**
- Changed required capability from `manage_woocommerce` to `manage_options`
- Fixes "you are not allowed to access this page" error
- Plugin now accessible to all WordPress Administrators
- Works even before WooCommerce is installed

---

## Version 2.0.2 - November 25, 2025

### 🎉 Major Improvements

**No Token Required - Public Repository**
- Clarified that GitHub Personal Access Token is **optional** since repository is public
- Updated UI to show "No token needed!" message
- Changed placeholder text to indicate token is not required
- Plugin works immediately without any authentication setup

**Repository Configuration**
- Confirmed correct GitHub repository: `wplaunchify/ml-wc-practitioners-catalogue`
- Updated default branch from `master` to `main`
- Plugin correctly fetches catalog data and images from public repository

### Technical Details
- Updated `ML_WC_PRACTITIONERS_GITHUB_BRANCH` constant from 'master' to 'main'
- Modified admin UI to clarify token is optional for public repositories
- Updated documentation comments to reflect public repository access

---

## Version 1.1.0 - November 10, 2025

### 🎉 Major Features Added

**WooCommerce Auto-Install & Wizard Bypass**
- One-click WooCommerce installation from within the plugin
- Completely bypasses the annoying WooCommerce setup wizard
- No more onboarding tasks, marketplace suggestions, or setup prompts
- Sets sensible defaults for supplement stores automatically
- Seamless AJAX installation with progress feedback

### What This Means

You can now go from a **fresh WordPress install** to a **fully-stocked supplement store** in under 5 minutes:

1. Upload and activate this plugin
2. Click "Install WooCommerce Now" (if needed)
3. Click "Import Catalog from GitHub"
4. Done! 196 products ready to sell

### Technical Details

- Added `ajax_install_woocommerce()` method for plugin installation
- Added `bypass_woocommerce_wizard()` to disable all WC onboarding
- Added `show_woocommerce_install_notice()` with install button UI
- Updated `check_and_setup_woocommerce()` to handle both scenarios
- All WooCommerce setup options automatically configured

### Settings Disabled

The plugin automatically disables these WooCommerce annoyances:
- Onboarding opt-in
- Task list
- Setup wizard redirect
- Marketplace suggestions
- Admin install prompts

---

## Version 1.0.0 - November 10, 2025

### Initial Release

**Core Features**
- Import 196 Professional Nutritionals products from private GitHub repo
- Download and attach product images to WordPress media library
- Create WooCommerce products with full metadata
- Support for product categories and custom fields
- GitHub authentication with personal access token
- Progress tracking and status updates
- Admin interface with settings management

**Product Data Imported**
- Product names and SKUs
- Wholesale and retail pricing
- Profit margins
- Descriptions and ingredients
- Benefits and directions
- Warnings and disclaimers
- Product categories
- 500x500 product images

**Technical Architecture**
- Single-file WordPress plugin
- Inline CSS and JavaScript
- Extensive commenting for LLM-friendly editing
- Table of contents for easy navigation
- Singleton pattern for main class
- AJAX handlers for all operations
- Error handling and logging

**GitHub Integration**
- Connects to private `wplaunchify/ml-stuarthoover` repository
- Fetches catalog from `professional-nutritionals/catalog/`
- Downloads images from `professional-nutritionals/images/`
- Uses Personal Access Token for authentication
- Configurable token via admin interface

---

## Roadmap

### Future Enhancements

**Version 1.2.0 (Planned)**
- Automatic product updates from GitHub
- Bulk product editing interface
- Custom pricing rules per practitioner
- Integration with Professional Nutritionals API
- Automated inventory sync

**Version 1.3.0 (Planned)**
- Multi-supplier support
- Custom product bundles
- Subscription product support
- Advanced category management
- Product import scheduling

**Version 2.0.0 (Planned)**
- Complete practitioner site setup automation
- FluentCRM integration
- Marketing automation templates
- Patient portal integration
- Full zero-inventory fulfillment system

---

**Plugin URI:** https://github.com/wplaunchify/ml-stuarthoover  
**Author:** Spencer Forman  
**License:** GPL v2 or later

