#!/bin/bash

# Ad Generation Testing Script
# This script helps you test the ad generation feature automatically

set -e

cd "$(dirname "$0")"

echo "==================================================================="
echo "Ad Generation Browser Testing"
echo "==================================================================="
echo ""

# Check if test image exists
TEST_IMAGE="../no_laravel/migration_source/edit_ad_layout_example.png"
if [ ! -f "$TEST_IMAGE" ]; then
    echo "❌ Test image not found: $TEST_IMAGE"
    echo "   Please ensure the image exists for testing."
    echo ""
    exit 1
fi

echo "✅ Test image found"
echo ""

# Check if npm dev server is running
if ! curl -s http://localhost:5173 > /dev/null 2>&1; then
    echo "⚠️  Vite dev server not running on localhost:5173"
    echo "   Run 'npm run dev' in another terminal before testing."
    echo ""
fi

# Check if Laravel server is running
if ! curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo "⚠️  Laravel server not running on localhost:8000"
    echo "   Run 'php artisan serve' in another terminal before testing."
    echo ""
fi

echo "==================================================================="
echo "Choose test to run:"
echo "==================================================================="
echo "1. Run all ad generation tests"
echo "2. Test: Generate button disabled without images"
echo "3. Test: Upload image and check button enabled"
echo "4. Test: Full create → upload → generate flow"
echo "5. Test: Edit page generate flow"
echo "6. Debug: Check browser console logs"
echo "7. Run all tests with verbose output"
echo "8. Quick manual test (open browser)"
echo ""

read -p "Enter choice (1-8): " choice

case $choice in
    1)
        echo ""
        echo "Running all ad generation tests..."
        php artisan test tests/Browser/AdGenerationTest.php
        ;;
    2)
        echo ""
        echo "Testing generate button disabled state..."
        php artisan test --filter="generate button is disabled without images"
        ;;
    3)
        echo ""
        echo "Testing image upload and button state..."
        php artisan test --filter="generate button becomes enabled after uploading image"
        ;;
    4)
        echo ""
        echo "Testing full create flow..."
        php artisan test --filter="full flow: create ad with image and click generate"
        ;;
    5)
        echo ""
        echo "Testing edit page flow..."
        php artisan test --filter="can upload image on edit page"
        ;;
    6)
        echo ""
        echo "Running debug test with console logs..."
        php artisan test --filter="debug: browser logs for generate flow"
        ;;
    7)
        echo ""
        echo "Running all tests with verbose output..."
        php artisan test tests/Browser/AdGenerationTest.php --verbose
        ;;
    8)
        echo ""
        echo "Opening browser for manual testing..."
        echo ""
        echo "Steps to test manually:"
        echo "1. Login with test@example.com / password"
        echo "2. Go to Ads → Create Ad"
        echo "3. Upload test image"
        echo "4. Click 'Generate with AI'"
        echo "5. Check browser console (F12) for errors"
        echo ""

        if command -v xdg-open > /dev/null; then
            xdg-open http://localhost:8000/login
        elif command -v open > /dev/null; then
            open http://localhost:8000/login
        else
            echo "Please open: http://localhost:8000/login"
        fi
        ;;
    *)
        echo "Invalid choice"
        exit 1
        ;;
esac

echo ""
echo "==================================================================="
echo "Test completed!"
echo "==================================================================="
echo ""
echo "If tests fail, check:"
echo "  • Browser dev console (F12)"
echo "  • Network tab for failed requests"
echo "  • storage/logs/laravel.log for server errors"
echo ""
echo "For more details, see TESTING_GUIDE.md"
