#!/usr/bin/env bash

# Fuerte-WP Version Bump Script
# Usage: ./scripts/bump-version.sh [version]
# Example: ./scripts/bump-version.sh 1.8.0

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Fuerte-WP Version Bump Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Get current version from plugin file
if [ -f "$PLUGIN_DIR/fuerte-wp.php" ]; then
    CURRENT_VERSION=$(grep "Version:" "$PLUGIN_DIR/fuerte-wp.php" | head -1 | sed 's/.*Version: *//' | sed 's/ *$//')
    if [ -z "$CURRENT_VERSION" ]; then
        CURRENT_VERSION="unknown"
    fi
else
    CURRENT_VERSION="unknown"
fi

# Also check composer.json for version
if [ -f "$PLUGIN_DIR/composer.json" ]; then
    COMPOSER_VERSION=$(grep '"version":' "$PLUGIN_DIR/composer.json" | head -1 | sed 's/.*"version": *//' | sed 's/",//' | sed 's/"//')
    if [ -n "$COMPOSER_VERSION" ] && [ "$COMPOSER_VERSION" != "unknown" ]; then
        CURRENT_VERSION="$COMPOSER_VERSION"
    fi
fi

echo -e "${YELLOW}Current version: ${GREEN}${CURRENT_VERSION}${NC}"
echo ""

# Always prompt for version (ignore command line argument for consistency)
read -rp "Enter new version (e.g., 1.8.0 or 1.8.0-beta.1): " VERSION

# Validate version format
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo -e "${RED}Error: Invalid version format. Use semver (e.g., 1.8.0 or 1.8.0-beta.1)${NC}"
    exit 1
fi

# Check if version is the same as current
if [ "$VERSION" = "$CURRENT_VERSION" ]; then
    echo -e "${YELLOW}Warning: Version ${VERSION} is the same as the current version.${NC}"
    read -rp "Continue anyway? (y/N): " CONTINUE
    if [[ ! "$CONTINUE" =~ ^[Yy]$ ]]; then
        echo -e "${RED}Version bump cancelled.${NC}"
        exit 0
    fi
fi

echo -e "${GREEN}Bumping version to ${VERSION}${NC}"
echo ""

# Files to update
FILES=(
    "$PLUGIN_DIR/fuerte-wp.php"
    "$PLUGIN_DIR/composer.json"
    "$PLUGIN_DIR/README.md"
    "$PLUGIN_DIR/README.txt"
    "$PLUGIN_DIR/SECURITY.md"
)

# Backup files
echo -e "${YELLOW}Creating backups...${NC}"
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        cp "$file" "${file}.bak"
        echo -e "${GREEN}✓ Backed up: $(basename "$file")${NC}"
    fi
done

# Count files that will be updated
COUNT=0

# Update main plugin file
echo ""
echo -e "${YELLOW}Updating version in plugin files...${NC}"

# 1. Main plugin file (fuerte-wp.php)
if [ -f "$PLUGIN_DIR/fuerte-wp.php" ]; then
    # Update Version header
    if sed -i.bak "s/Version: .*/Version: ${VERSION}/" "$PLUGIN_DIR/fuerte-wp.php" 2>/dev/null; then
        # Update FUERTEWP_VERSION constant
        sed -i.bak "s/define('FUERTEWP_VERSION', '[^']*')/define('FUERTEWP_VERSION', '${VERSION}')/" "$PLUGIN_DIR/fuerte-wp.php" 2>/dev/null || true
        rm -f "$PLUGIN_DIR/fuerte-wp.php.bak"
        echo -e "${GREEN}✓ Updated fuerte-wp.php${NC}"
        ((COUNT++))
    else
        rm -f "$PLUGIN_DIR/fuerte-wp.php.bak"
        echo -e "${RED}✗ Failed to update fuerte-wp.php${NC}"
    fi
fi

# 2. composer.json
if [ -f "$PLUGIN_DIR/composer.json" ]; then
    if sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$PLUGIN_DIR/composer.json" 2>/dev/null; then
        rm -f "$PLUGIN_DIR/composer.json.bak"
        echo -e "${GREEN}✓ Updated composer.json${NC}"
        ((COUNT++))
    else
        rm -f "$PLUGIN_DIR/composer.json.bak"
        echo -e "${RED}✗ Failed to update composer.json${NC}"
    fi
fi

# 3. README.md (if it contains Stable tag)
if [ -f "$PLUGIN_DIR/README.md" ]; then
    if grep -q "Stable tag:" "$PLUGIN_DIR/README.md"; then
        if sed -i.bak "s/Stable tag: .*/Stable tag: ${VERSION}/" "$PLUGIN_DIR/README.md" 2>/dev/null; then
            rm -f "$PLUGIN_DIR/README.md.bak"
            echo -e "${GREEN}✓ Updated README.md${NC}"
            ((COUNT++))
        else
            rm -f "$PLUGIN_DIR/README.md.bak"
            echo -e "${RED}✗ Failed to update README.md${NC}"
        fi
    fi
fi

# 4. README.txt (WordPress readme format)
if [ -f "$PLUGIN_DIR/README.txt" ]; then
    # Update Stable tag
    if sed -i.bak "s/Stable tag: .*/Stable tag: ${VERSION}/" "$PLUGIN_DIR/README.txt" 2>/dev/null; then
        # Update version in header if present
        if grep -q "^=== Fuerte-WP ===" "$PLUGIN_DIR/README.txt"; then
            # Check if there's a version line and update it (ignore if not found)
            sed -i.bak "s/^Version: .*/Version: ${VERSION}/" "$PLUGIN_DIR/README.txt" 2>/dev/null || true
        fi
        rm -f "$PLUGIN_DIR/README.txt.bak"
        echo -e "${GREEN}✓ Updated README.txt${NC}"
        ((COUNT++))
    else
        rm -f "$PLUGIN_DIR/README.txt.bak"
        echo -e "${YELLOW}⚠ Failed to update README.txt (file may not have Stable tag)${NC}"
    fi
fi

# 5. SECURITY.md
if [ -f "$PLUGIN_DIR/SECURITY.md" ]; then
    # Check if it has a version section and update it
    if grep -q "## Version" "$PLUGIN_DIR/SECURITY.md" 2>/dev/null; then
        if sed -i.bak "s/## Version.*/## Version ${VERSION}/" "$PLUGIN_DIR/SECURITY.md" 2>/dev/null; then
            rm -f "$PLUGIN_DIR/SECURITY.md.bak"
            echo -e "${GREEN}✓ Updated SECURITY.md${NC}"
            ((COUNT++))
        else
            rm -f "$PLUGIN_DIR/SECURITY.md.bak"
            echo -e "${RED}✗ Failed to update SECURITY.md${NC}"
        fi
    fi
fi

# 6. package.json (if it exists - for any JS assets)
if [ -f "$PLUGIN_DIR/package.json" ]; then
    if sed -i.bak "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$PLUGIN_DIR/package.json" 2>/dev/null; then
        rm -f "$PLUGIN_DIR/package.json.bak"
        echo -e "${GREEN}✓ Updated package.json${NC}"
        ((COUNT++))
    else
        rm -f "$PLUGIN_DIR/package.json.bak"
        echo -e "${RED}✗ Failed to update package.json${NC}"
    fi
fi

# 7. fuerte-wpasset.php (if it exists - constants file)
if [ -f "$PLUGIN_DIR/includes/fuerte-wpasset.php" ]; then
    if sed -i.bak "s/define( 'FUERTEWP_VERSION', '[^']*' );/define( 'FUERTEWP_VERSION', '${VERSION}' );/" "$PLUGIN_DIR/includes/fuerte-wpasset.php" 2>/dev/null; then
        rm -f "$PLUGIN_DIR/includes/fuerte-wpasset.php.bak"
        echo -e "${GREEN}✓ Updated fuertewpasset.php${NC}"
        ((COUNT++))
    else
        rm -f "$PLUGIN_DIR/includes/fuerte-wpasset.php.bak"
        echo -e "${RED}✗ Failed to update fuertewpasset.php${NC}"
    fi
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Version Bump Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Version:    ${VERSION}"
echo -e "Files updated: ${COUNT}"
echo ""

# Show changed files
echo -e "${YELLOW}Changed files:${NC}"
git status --short "$PLUGIN_DIR" 2>/dev/null || echo "  (Not a git repository or git not available)"

echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo -e "1. Review the changes above"
echo -e "2. Run tests: ${GREEN}composer test${NC}"
echo -e "3. Fix code style: ${GREEN}composer cs:fix${NC}"
echo -e "4. Commit changes:"
echo -e "   ${BLUE}git add .${NC}"
echo -e "   ${BLUE}git commit -m \"chore: bump version to ${VERSION}\"${NC}"
echo -e "   ${BLUE}git tag v${VERSION}${NC}"
echo -e "   ${BLUE}git push origin main --tags${NC}"
echo ""

# Ask if user wants to see a diff
read -rp "Show diff of changes? (y/N): " SHOW_DIFF
if [[ "$SHOW_DIFF" =~ ^[Yy]$ ]]; then
    echo ""
    echo -e "${YELLOW}========================================${NC}"
    echo -e "${YELLOW}File Differences${NC}"
    echo -e "${YELLOW}========================================${NC}"
    git diff "$PLUGIN_DIR" 2>/dev/null || echo "Git diff not available"
fi

echo ""
echo -e "${GREEN}Done!${NC}"

# Exit successfully
exit 0
