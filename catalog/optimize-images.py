#!/usr/bin/env python3
"""
Optimize product images for WordPress import.
- Keeps 500x500 PNG format
- Compresses using PIL optimization
- Creates images.zip for single-download deployment
"""

import os
import zipfile
from PIL import Image
from pathlib import Path

# Configuration
SOURCE_DIR = "images"
OPTIMIZED_DIR = "images-optimized"
ZIP_FILE = "images.zip"

def get_file_size_kb(path):
    return os.path.getsize(path) / 1024

def optimize_image(src_path, dst_path):
    """Optimize a single PNG image."""
    img = Image.open(src_path)
    
    # Convert to RGB if necessary (removes alpha if not needed)
    if img.mode == 'RGBA':
        # Check if alpha channel is actually used
        alpha = img.split()[3]
        if alpha.getextrema() == (255, 255):
            # Alpha is fully opaque, convert to RGB
            img = img.convert('RGB')
    elif img.mode != 'RGB':
        img = img.convert('RGB')
    
    # Save optimized PNG
    img.save(dst_path, 'PNG', optimize=True)
    
    return get_file_size_kb(src_path), get_file_size_kb(dst_path)

def main():
    # Create optimized directory
    os.makedirs(OPTIMIZED_DIR, exist_ok=True)
    
    # Get all PNG files
    images = list(Path(SOURCE_DIR).glob("*.png"))
    print(f"Found {len(images)} images to optimize\n")
    
    total_original = 0
    total_optimized = 0
    
    for i, img_path in enumerate(images, 1):
        dst_path = Path(OPTIMIZED_DIR) / img_path.name
        
        orig_size, opt_size = optimize_image(str(img_path), str(dst_path))
        total_original += orig_size
        total_optimized += opt_size
        
        savings = ((orig_size - opt_size) / orig_size) * 100 if orig_size > 0 else 0
        
        if i % 20 == 0 or i == len(images):
            print(f"Processed {i}/{len(images)} images...")
    
    print(f"\n{'='*50}")
    print(f"Original total:  {total_original/1024:.2f} MB")
    print(f"Optimized total: {total_optimized/1024:.2f} MB")
    print(f"Savings:         {(total_original-total_optimized)/1024:.2f} MB ({((total_original-total_optimized)/total_original)*100:.1f}%)")
    
    # Create ZIP file
    print(f"\nCreating {ZIP_FILE}...")
    
    with zipfile.ZipFile(ZIP_FILE, 'w', zipfile.ZIP_DEFLATED) as zf:
        for img_path in Path(OPTIMIZED_DIR).glob("*.png"):
            # Add with just filename (no folder prefix)
            zf.write(img_path, img_path.name)
    
    zip_size = get_file_size_kb(ZIP_FILE) / 1024
    print(f"ZIP created: {zip_size:.2f} MB")
    
    # Verify ZIP contents
    with zipfile.ZipFile(ZIP_FILE, 'r') as zf:
        print(f"ZIP contains {len(zf.namelist())} files")
        # Show first few entries
        for name in zf.namelist()[:3]:
            print(f"  - {name}")
        print("  ...")
    
    print(f"\n{'='*50}")
    print("SUCCESS!")
    print(f"  Optimized images: {OPTIMIZED_DIR}/")
    print(f"  ZIP file: {ZIP_FILE}")
    print("="*50)

if __name__ == "__main__":
    main()

