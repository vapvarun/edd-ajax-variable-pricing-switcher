# EDD AJAX Variable Pricing Switcher

A powerful WordPress plugin that enhances Easy Digital Downloads with AJAX-powered variable pricing, automatic Annual/Lifetime grouping, and seamless checkout experience.

## 🚀 Features

### ✅ **AJAX Price Updates**
- **No page reloads** - All price changes happen instantly via AJAX
- **Real-time updates** on both product pages and checkout
- **Smooth user experience** with loading indicators and error handling

### ✅ **Automatic Grouping**
- **Smart categorization** - Automatically groups pricing options into "Annual" and "Lifetime" sections
- **Visual distinction** - Color-coded headers for easy identification
- **Based on EDD data** - Uses existing `is_lifetime` field in variable pricing meta

### ✅ **Checkout Enhancement**
- **Change license types** in cart without page reload
- **Collapsible panels** for clean checkout experience
- **Real-time cart totals** update automatically
- **Success/error notifications** for user feedback

### ✅ **Default Selection**
- **Auto-selects** first pricing option (prioritizes Annual over Lifetime)
- **Prevents confusion** - users always have a selection
- **Form compatibility** - works seamlessly with EDD purchase forms

### ✅ **Universal Compatibility**
- **Enabled by default** for all products with variable pricing
- **No configuration required** - works out of the box
- **Mobile responsive** design
- **EDD core compatible** - maintains all existing functionality

## 📋 Requirements

- **WordPress** 5.0 or higher
- **Easy Digital Downloads** 2.5 or higher
- **jQuery** (included with WordPress)

## 🔧 Installation

### Method 1: Manual Installation
1. **Download** the plugin files
2. **Upload** the `edd-ajax-variable-pricing-switcher` folder to `/wp-content/plugins/`
3. **Activate** the plugin through the WordPress 'Plugins' menu
4. **Done!** The plugin works automatically

### Method 2: WordPress Admin
1. **Navigate** to Plugins > Add New
2. **Upload** the plugin ZIP file
3. **Install** and **Activate**
4. **Ready to use!**

## 📁 File Structure

```
edd-ajax-variable-pricing-switcher/
├── edd-ajax-variable-pricing-switcher.php  (Main plugin file)
├── edd-ajax-pricing-switcher.js           (JavaScript functionality)
└── README.md                              (This file)
```

## 🎯 How It Works

### **Product Pages**
1. **Detects** products with variable pricing
2. **Groups** options by Annual/Lifetime automatically
3. **Displays** in clean, organized sections
4. **Updates** price via AJAX when selection changes
5. **Maintains** form compatibility for purchase

### **Checkout Pages**
1. **Adds** "Change License Type" buttons to cart items
2. **Shows** grouped options in expandable panels
3. **Updates** cart totals in real-time via AJAX
4. **Preserves** cart integrity during updates
5. **Provides** immediate feedback to users

### **Data Detection**
The plugin automatically reads your EDD variable pricing data:
```php
// Example pricing structure
array(
    'name' => 'Lifetime Single Site License',
    'amount' => '249.00',
    'is_lifetime' => '1'  // ← This triggers Lifetime grouping
)
```

## 🎨 Styling

The plugin includes responsive CSS that:
- **Matches** most WordPress themes
- **Color-codes** Annual (blue) and Lifetime (green) sections
- **Provides** hover effects and smooth transitions
- **Adapts** to mobile devices automatically

### **Customization**
Add custom CSS to your theme to modify appearance:
```css
/* Customize Annual section */
.annual-group .pricing-group-title {
    background: #your-color !important;
}

/* Customize Lifetime section */
.lifetime-group .pricing-group-title {
    background: #your-color !important;
}
```

## 🔌 Hooks & Filters

### **JavaScript Events**
```javascript
// Triggered when price is updated
$('body').on('edd_price_updated', function(event, data, priceId) {
    // Your custom code here
});

// Triggered when cart item price is updated
$('body').on('edd_cart_item_price_updated', function(event, data) {
    // Your custom code here
});
```

### **Integration API**
```javascript
// Access plugin functions globally
window.eddAjaxPricing.updateCartTotals(data);
window.eddAjaxPricing.initializeDefaults();
```

## 🐛 Troubleshooting

### **Common Issues**

**Q: Pricing switcher not appearing**
- **Check** that your product has variable pricing enabled
- **Verify** that multiple pricing options exist
- **Ensure** plugin is activated

**Q: AJAX not working**
- **Check** browser console for JavaScript errors
- **Verify** WordPress AJAX is functioning
- **Test** with default theme to rule out conflicts

**Q: Grouping not working**
- **Ensure** your pricing data includes `is_lifetime` field
- **Check** that the field value is exactly `'1'` (string)
- **Verify** pricing data structure in database

**Q: Styling issues**
- **Check** for theme CSS conflicts
- **Add** custom CSS to override as needed
- **Test** on different devices/browsers

### **Debug Mode**
Enable WordPress debug mode to see detailed error messages:
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 🤝 Support

### **Getting Help**
1. **Check** this README for common solutions
2. **Review** browser console for JavaScript errors
3. **Test** with default WordPress theme
4. **Disable** other plugins to check for conflicts

### **Bug Reports**
When reporting issues, please include:
- WordPress version
- EDD version
- Active theme
- Other active plugins
- Browser console errors
- Steps to reproduce

## 🔄 Changelog

See [RELEASE_NOTES.md](RELEASE_NOTES.md) for detailed version history.

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 👥 Credits

Developed by **Wbcom Designs**  
Built for **Easy Digital Downloads**  
Compatible with **WordPress 5.0+**

---

## 🎯 Quick Start Guide

1. **Install & Activate** the plugin
2. **Create/Edit** an EDD product
3. **Add variable pricing** with some options marked as `is_lifetime = '1'`
4. **View** the product page - see automatic grouping
5. **Add to cart** and visit checkout
6. **Click** "Change License Type" to test cart updates
7. **Enjoy** the enhanced user experience!

**That's it!** No configuration needed - the plugin works automatically for all your variable pricing products.
