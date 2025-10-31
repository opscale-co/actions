#!/bin/bash

# Script to automate package publishing to Packagist
# Creating releases and tags on GitHub

# Colors for messages
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions to display messages
function show_message() {
    echo -e "${GREEN}==>${NC} $1"
}

function show_error() {
    echo -e "${RED}ERROR:${NC} $1"
    exit 1
}

function show_warning() {
    echo -e "${YELLOW}WARNING:${NC} $1"
}

function show_info() {
    echo -e "${BLUE}INFO:${NC} $1"
}

# Check if git is installed
if ! command -v git &> /dev/null; then
    show_error "Git is not installed. Please install it to continue."
fi

# Check if we're in a git repository
if [ ! -d .git ]; then
    show_error "You are not in a Git repository. Run this script from your project's root."
fi

# Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    show_warning "You have uncommitted changes. Consider committing before continuing."
    read -p "Do you want to continue anyway? (y/n): " continue_choice
    if [[ $continue_choice != "y" && $continue_choice != "Y" ]]; then
        exit 0
    fi
fi

# Get information for the new version
show_message "Packagist publishing process started"

# Check composer.json
if [ ! -f composer.json ]; then
    show_error "composer.json file not found in the current directory."
fi

# Read current version from composer.json if it exists
CURRENT_VERSION=$(grep -o '"version": *"[^"]*"' composer.json 2>/dev/null | cut -d'"' -f4)
if [ -n "$CURRENT_VERSION" ]; then
    show_info "Current version in composer.json: $CURRENT_VERSION"
else
    show_info "No version found in composer.json"
    CURRENT_VERSION="0.0.0"
fi

# Ask for the new version
echo ""
echo "Choose the type of version to publish:"
echo "1) Major (important change that breaks compatibility)"
echo "2) Minor (new features backward compatible)"
echo "3) Patch (bug fixes backward compatible)"
echo "4) Specify version manually"
read -p "Option [1-4]: " version_option

# Split current version into components
IFS='.' read -r -a version_parts <<< "$CURRENT_VERSION"
MAJOR=${version_parts[0]:-0}
MINOR=${version_parts[1]:-0}
PATCH=${version_parts[2]:-0}

case $version_option in
    1)
        NEW_MAJOR=$((MAJOR + 1))
        NEW_VERSION="$NEW_MAJOR.0.0"
        ;;
    2)
        NEW_MINOR=$((MINOR + 1))
        NEW_VERSION="$MAJOR.$NEW_MINOR.0"
        ;;
    3)
        NEW_PATCH=$((PATCH + 1))
        NEW_VERSION="$MAJOR.$MINOR.$NEW_PATCH"
        ;;
    4)
        read -p "Enter the new version (format x.y.z): " NEW_VERSION
        # Validate version format
        if ! [[ $NEW_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            show_error "Invalid version format. It must be x.y.z (numbers separated by dots)"
        fi
        ;;
    *)
        show_error "Invalid option"
        ;;
esac

show_info "New version selected: $NEW_VERSION"

# Update composer.json if it contains version
if grep -q '"version":' composer.json; then
    sed -i "s/\"version\": *\"[^\"]*\"/\"version\": \"$NEW_VERSION\"/" composer.json
    show_message "composer.json file updated with new version"
else
    show_warning "No 'version' entry found in composer.json. Not updated."
fi

# Request release notes
echo ""
show_message "Enter the release notes (end with a line containing only 'EOF'):"
RELEASE_NOTES=""
while IFS= read -r line; do
    [[ "$line" == "EOF" ]] && break
    RELEASE_NOTES+="$line"$'\n'
done

# Confirm before proceeding
echo ""
show_message "Publication summary:"
echo "- Version: v$NEW_VERSION"
echo "- Release notes:"
echo "$RELEASE_NOTES"
echo ""
read -p "Confirm and proceed with publication? (y/n): " confirm
if [[ $confirm != "y" && $confirm != "Y" ]]; then
    show_info "Operation cancelled by user."
    exit 0
fi

# Update CHANGELOG.md if it exists
if [ -f CHANGELOG.md ]; then
    show_message "Updating CHANGELOG.md..."
    # Create temporary entry
    TEMP_FILE=$(mktemp)
    {
        echo "# Changelog"
        echo ""
        echo "## v$NEW_VERSION - $(date +%Y-%m-%d)"
        echo ""
        echo "$RELEASE_NOTES"
        echo ""
        if [ -s CHANGELOG.md ]; then
            # Skip the "# Changelog" header if it already exists
            tail -n +2 CHANGELOG.md
        fi
    } > "$TEMP_FILE"
    mv "$TEMP_FILE" CHANGELOG.md
    show_info "CHANGELOG.md updated"
fi

# Commit changes
show_message "Committing changes..."
git add composer.json
if [ -f CHANGELOG.md ]; then
    git add CHANGELOG.md
fi
git commit -m "Preparing release v$NEW_VERSION"

# Create and publish tag
show_message "Creating tag v$NEW_VERSION..."
git tag -a "v$NEW_VERSION" -m "Version $NEW_VERSION"

# Ask if push is desired
read -p "Do you want to push changes and tag now? (y/n): " push_choice
if [[ $push_choice == "y" || $push_choice == "Y" ]]; then
    show_message "Pushing changes and tag..."
    git push origin main
    git push origin "v$NEW_VERSION"
    
    show_info "GitHub Actions should trigger automatically to:"
    show_info "1. Update CHANGELOG.md based on the release"
    show_info "2. Publish the package to Packagist"
else
    show_info "Push not performed. To complete the process, manually run:"
    echo "git push origin main"
    echo "git push origin v$NEW_VERSION"
fi

# Ask if manual GitHub release creation is desired
echo ""
read -p "Do you want to manually create a GitHub release now? (y/n): " release_choice
if [[ $release_choice == "y" || $release_choice == "Y" ]]; then
    # Get GitHub remote origin
    GITHUB_ORIGIN=$(git remote get-url origin | sed 's/\.git$//' | sed 's/git@github.com:/https:\/\/github.com\//')
    
    if [[ -n "$GITHUB_ORIGIN" ]]; then
        RELEASE_URL="$GITHUB_ORIGIN/releases/new?tag=v$NEW_VERSION&title=v$NEW_VERSION"
        show_info "Opening URL to create release:"
        echo "$RELEASE_URL"
        
        # Try to open browser (compatible with different platforms)
        if command -v xdg-open &> /dev/null; then
            xdg-open "$RELEASE_URL"
        elif command -v open &> /dev/null; then
            open "$RELEASE_URL"
        elif command -v start &> /dev/null; then
            start "$RELEASE_URL"
        else
            show_info "Could not open browser automatically. Copy and paste the URL into your browser."
        fi
    else
        show_warning "Could not determine GitHub URL."
    fi
fi

show_message "Process completed successfully!"