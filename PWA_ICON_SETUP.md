# PWA Icon Setup Instructions

## Overview
The PWA manifest has been updated to use WAPOS branded icons. You need to create icon files with your WAPOS logo.

## Required Icon Files

Create these PNG files in `assets/images/system/`:

### Standard Icons (with transparent or white background)
- `wapos-icon-72.png` - 72x72px
- `wapos-icon-96.png` - 96x96px
- `wapos-icon-128.png` - 128x128px
- `wapos-icon-144.png` - 144x144px
- `wapos-icon-152.png` - 152x152px
- `wapos-icon-192.png` - 192x192px
- `wapos-icon-384.png` - 384x384px
- `wapos-icon-512.png` - 512x512px

### Maskable Icons (with safe zone padding)
- `wapos-icon-maskable-192.png` - 192x192px
- `wapos-icon-maskable-512.png` - 512x512px

## Design Guidelines

### Standard Icons
1. Use your WAPOS logo
2. Center the logo on a transparent or white background
3. Add 10-15% padding around the logo
4. Save as PNG with transparency

### Maskable Icons
1. Use the same WAPOS logo
2. Add 20% safe zone padding (logo should be 80% of canvas)
3. Can use colored background (purple gradient recommended: #667eea to #764ba2)
4. Ensure logo is centered
5. Save as PNG

## Quick Generation Methods

### Method 1: Online Tool (Easiest)
1. Go to https://www.pwabuilder.com/imageGenerator
2. Upload your WAPOS logo (512x512px recommended)
3. Select all required sizes
4. Download the generated icons
5. Rename files to match the naming convention above
6. Upload to `assets/images/system/`

### Method 2: Using Photoshop/GIMP
1. Open your WAPOS logo
2. For each size:
   - Create new canvas (e.g., 512x512px)
   - Paste logo centered
   - Add appropriate padding
   - Export as PNG
3. Save with correct filenames

### Method 3: Using ImageMagick (Command Line)
```bash
# Install ImageMagick first
# Then run these commands:

# Standard icons
convert wapos-logo.png -resize 72x72 -background transparent -gravity center -extent 72x72 wapos-icon-72.png
convert wapos-logo.png -resize 96x96 -background transparent -gravity center -extent 96x96 wapos-icon-96.png
convert wapos-logo.png -resize 128x128 -background transparent -gravity center -extent 128x128 wapos-icon-128.png
convert wapos-logo.png -resize 144x144 -background transparent -gravity center -extent 144x144 wapos-icon-144.png
convert wapos-logo.png -resize 152x152 -background transparent -gravity center -extent 152x152 wapos-icon-152.png
convert wapos-logo.png -resize 192x192 -background transparent -gravity center -extent 192x192 wapos-icon-192.png
convert wapos-logo.png -resize 384x384 -background transparent -gravity center -extent 384x384 wapos-icon-384.png
convert wapos-logo.png -resize 512x512 -background transparent -gravity center -extent 512x512 wapos-icon-512.png

# Maskable icons (with purple background)
convert wapos-logo.png -resize 154x154 -background "#667eea" -gravity center -extent 192x192 wapos-icon-maskable-192.png
convert wapos-logo.png -resize 410x410 -background "#667eea" -gravity center -extent 512x512 wapos-icon-maskable-512.png
```

## Testing

### Test PWA Installation
1. Deploy updated `manifest.json` to production
2. Upload all icon files to `assets/images/system/`
3. Open your site in Chrome/Edge
4. Click install icon in address bar
5. Verify WAPOS logo appears in install dialog
6. Install and check home screen icon

### Test on Different Devices
- **Android**: Check home screen icon and splash screen
- **iOS**: Check home screen icon (add to home screen)
- **Desktop**: Check taskbar/dock icon
- **Windows**: Check Start Menu tile

## Fallback
If icons are not found, the browser will use:
1. Favicon
2. Default browser icon (generic)

Make sure to also have a proper favicon.ico in the root directory.

## Current Status
✅ manifest.json updated with new icon paths
✅ Theme color changed to purple (#667eea)
✅ Background color changed to white (#ffffff)
⏳ Icon files need to be created and uploaded

## Files Updated
- `manifest.json` - PWA configuration with WAPOS branding
