# ML WooCommerce Practitioners Catalog

WordPress plugin for importing Professional Nutritionals product catalog to WooCommerce.

## What This Plugin Does

- **One-click import** of 196+ Professional Nutritionals products
- **Automatic image handling** - downloads and attaches product images
- **Complete product data** - descriptions, pricing, categories, SKUs
- **WooCommerce auto-setup** - installs and configures WooCommerce if needed
- **Zero-inventory model** - perfect for practitioner drop-shipping

## Installation

1. Download the plugin ZIP from releases
2. Upload to WordPress via Plugins → Add New → Upload
3. Activate the plugin
4. Go to ML Practitioners → Import Catalog
5. Click "Import Products" button
6. Wait for import to complete (196 products)

## What Gets Imported

- **196 Products** from Professional Nutritionals
- **Product Images** (square format, white background)
- **Pricing** (wholesale + retail)
- **Categories** (Supplements, Vitamins, Minerals, etc.)
- **Descriptions** (professional grade supplement info)
- **SKUs** (Professional Nutritionals stock codes)

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 5.0+ (auto-installed if needed)

## Repository Structure

```
ml-wc-practitioners-catalog/
├── plugin/                          # WordPress plugin files
│   ├── ml-wc-practitioners.php     # Main plugin file
│   ├── ml-wc-practitioners.zip     # Installable ZIP
│   └── README.md                    # Plugin documentation
├── catalog/                         # Product catalog data
│   ├── PRACTITIONER-CATALOG-FINAL.csv  # 196 products with data
│   └── images/                      # 196 product images (PNG)
└── README.md                        # This file
```

## For Developers

### Plugin Details
- **Version:** 1.6.0
- **GitHub Repo:** wplaunchify/ml-wc-practitioners-catalogue
- **Data Source:** catalog/PRACTITIONER-CATALOG-FINAL.csv
- **Images:** catalog/images/ (196 PNG files)

### Key Features
- Batch import with progress tracking
- Automatic WooCommerce installation
- Image download and media library integration
- Category creation and assignment
- Product metadata handling
- Error handling and logging

## Support

For issues or questions, open an issue on GitHub.

## License

GPL v2 or later

## Author

Spencer Forman - MinuteLaunch.ai
