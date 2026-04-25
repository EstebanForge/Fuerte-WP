#!/usr/bin/env bash

# Fuerte-WP Version Bump Script (Portable Perl Edition)
# Usage: ./scripts/bump-version.sh [version]
# Example: ./scripts/bump-version.sh 1.8.1

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Fuerte-WP Version Bump Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Get current version
if [ -f "$PLUGIN_DIR/fuerte-wp.php" ]; then
    CURRENT_VERSION=$(grep "Version:" "$PLUGIN_DIR/fuerte-wp.php" | head -1 | sed 's/.*Version: *//' | sed 's/ *$//')
else
    CURRENT_VERSION="unknown"
fi

echo -e "${YELLOW}Current version: ${GREEN}${CURRENT_VERSION}${NC}"
echo ""

# Prompt for version
read -rp "Enter new version (e.g., 1.8.1): " VERSION

# Validate version format
if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$ ]]; then
    echo -e "${RED}Error: Invalid version format.${NC}"
    exit 1
fi

echo -e "${GREEN}Bumping version to ${VERSION}${NC}"
echo ""

# Use perl for portable search and replace (returns 0 if file exists and updated/non-critical)
update_file() {
    local pattern="$1"
    local file="$2"
    local label="$3"
    local critical="${4:-false}"  # Default: non-critical

    if [ -f "$file" ]; then
        if perl -i -pe "$pattern" "$file" 2>/dev/null; then
            echo -e "${GREEN}✓ Updated $label${NC}"
            return 0
        else
            if [ "$critical" = "true" ]; then
                echo -e "${RED}✗ Failed to update $label${NC}"
                return 1
            else
                echo -e "${YELLOW}⊘ No match found or optional update skipped: $label${NC}"
                return 0  # Don't fail for non-critical updates
            fi
        fi
    fi
    return 0
}

COUNT=0

# 1. fuerte-wp.php (Version header) - preserve alignment
update_file "s/(Version:\s+)\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?/\${1}${VERSION}/" "$PLUGIN_DIR/fuerte-wp.php" "fuerte-wp.php (header)" "true" && ((COUNT++)) || true

# 2. composer.json
update_file "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$PLUGIN_DIR/composer.json" "composer.json" "true" && ((COUNT++)) || true

# 4. README.txt
if [ -f "$PLUGIN_DIR/README.txt" ]; then
    if perl -i -pe "s/Stable tag: .*/Stable tag: ${VERSION}/" "$PLUGIN_DIR/README.txt" 2>/dev/null && \
       perl -i -pe "s/^Version: .*/Version: ${VERSION}/" "$PLUGIN_DIR/README.txt" 2>/dev/null; then
        echo -e "${GREEN}✓ Updated README.txt${NC}"
        ((COUNT++))
    else
        echo -e "${YELLOW}⊘ No version tags found in README.txt${NC}"
    fi
fi

# 5. README.md
if [ -f "$PLUGIN_DIR/README.md" ]; then
    if grep -q "Stable tag:" "$PLUGIN_DIR/README.md" 2>/dev/null; then
        perl -i -pe "s/Stable tag: .*/Stable tag: ${VERSION}/" "$PLUGIN_DIR/README.md" 2>/dev/null
        echo -e "${GREEN}✓ Updated README.md${NC}"
        ((COUNT++))
    else
        echo -e "${YELLOW}⊘ No 'Stable tag' found in README.md (GitHub format)${NC}"
    fi
fi

# 6. SECURITY.md (Table update)
if [ -f "$PLUGIN_DIR/SECURITY.md" ]; then
    # Update the supported version line and the comparison line
    if perl -i -pe "s/\| [0-9]+\.[0-9]+\.[0-9]+[ ]+\| :white_check_mark: \|/| ${VERSION}   | :white_check_mark: |/" "$PLUGIN_DIR/SECURITY.md" 2>/dev/null && \
       perl -i -pe "s/\| <[0-9]+\.[0-9]+\.[0-9]+[ ]+\| :x:                \|/| <${VERSION}  | :x:                |/" "$PLUGIN_DIR/SECURITY.md" 2>/dev/null; then
        echo -e "${GREEN}✓ Updated SECURITY.md${NC}"
        ((COUNT++))
    else
        echo -e "${YELLOW}⊘ No version table found in SECURITY.md${NC}"
    fi
fi

# 7. tests/bootstrap.php (FUERTEWP_VERSION is now dynamic - skip updating)
if [ -f "$PLUGIN_DIR/tests/bootstrap.php" ]; then
    echo -e "${YELLOW}⊘ FUERTEWP_VERSION is now defined dynamically - skipping tests/bootstrap.php${NC}"
fi

# 8. Library composer.json (HyperFields)
LIB_COMPOSER="$PLUGIN_DIR/vendor/estebanforge/hyperfields/composer.json"
if [ -f "$LIB_COMPOSER" ]; then
    if perl -i -pe "s/\"version\": \"[^\"]*\"/\"version\": \"${VERSION}\"/" "$LIB_COMPOSER" 2>/dev/null; then
        echo -e "${GREEN}✓ Updated library composer.json (HyperFields)${NC}"
        ((COUNT++))
    else
        echo -e "${YELLOW}⊘ No version field found in HyperFields composer.json${NC}"
    fi
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Version Bump Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Version:    ${VERSION}"
echo ""

# Show changed files
echo -e "${YELLOW}Next steps:${NC}"
echo -e "1. Review the changes: ${BLUE}git status${NC}"
echo -e "2. Run tests: ${GREEN}composer test${NC}"
echo -e "3. Commit and tag:${NC}"
echo -e "   ${BLUE}git add .${NC}"
echo -e "   ${BLUE}git commit -m \"chore: bump version to ${VERSION}\"${NC}"
echo -e "   ${BLUE}git tag v${VERSION}${NC}"

exit 0
