#!/usr/bin/env python3
"""
WordPress Plugin ZIP Creator
Creates properly formatted zip files for WordPress plugins.

Rules:
1. Inner folder must be plugin name only (no version)
2. Paths use forward slashes /
3. Create zip without version first, then rename
4. No "v" prefix on version numbers
"""

import zipfile
import os
import shutil
import sys

# Configuration
PLUGIN_NAME = "ml-wc-practitioners"
PLUGIN_FILE = "ml-wc-practitioners.php"
RELEASES_DIR = "releases"

def get_version_from_php(php_file):
    """Extract version from PHP file header"""
    with open(php_file, 'r', encoding='utf-8') as f:
        for line in f:
            if '* Version:' in line:
                version = line.split('Version:')[1].strip()
                return version
    return None

def create_plugin_zip():
    # Get current directory
    script_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(script_dir)
    
    # Check plugin file exists
    if not os.path.exists(PLUGIN_FILE):
        print(f"ERROR: {PLUGIN_FILE} not found!")
        sys.exit(1)
    
    # Get version from PHP file
    version = get_version_from_php(PLUGIN_FILE)
    if not version:
        print("ERROR: Could not find version in PHP file!")
        sys.exit(1)
    
    print(f"Plugin: {PLUGIN_NAME}")
    print(f"Version: {version}")
    
    # Create releases directory if needed
    if not os.path.exists(RELEASES_DIR):
        os.makedirs(RELEASES_DIR)
    
    # Step 1: Create zip WITHOUT version number
    base_zip = f"{PLUGIN_NAME}.zip"
    base_zip_path = os.path.join(RELEASES_DIR, base_zip)
    
    # Remove old zip if exists
    if os.path.exists(base_zip_path):
        os.remove(base_zip_path)
    
    print(f"\nCreating {base_zip}...")
    
    # Create zip with correct structure using forward slashes
    with zipfile.ZipFile(base_zip_path, 'w', zipfile.ZIP_DEFLATED) as zf:
        # Add the plugin file with correct path (forward slashes!)
        arcname = f"{PLUGIN_NAME}/{PLUGIN_FILE}"  # Forward slash!
        zf.write(PLUGIN_FILE, arcname)
        print(f"  Added: {arcname}")
    
    print(f"Created: {base_zip_path}")
    
    # Verify the zip structure
    print("\nVerifying zip structure:")
    with zipfile.ZipFile(base_zip_path, 'r') as zf:
        for name in zf.namelist():
            print(f"  {name}")
            # Check for backslashes
            if '\\' in name:
                print("  WARNING: Backslash found!")
    
    # Step 2: Copy and RENAME to versioned name (no "v" prefix!)
    versioned_zip = f"{PLUGIN_NAME}-{version}.zip"
    versioned_zip_path = os.path.join(RELEASES_DIR, versioned_zip)
    
    shutil.copy(base_zip_path, versioned_zip_path)
    print(f"\nCopied to: {versioned_zip_path}")
    
    # Final verification
    print("\n" + "="*50)
    print("SUCCESS!")
    print(f"  Main zip: {RELEASES_DIR}/{base_zip}")
    print(f"  Versioned: {RELEASES_DIR}/{versioned_zip}")
    print("="*50)

if __name__ == "__main__":
    create_plugin_zip()

