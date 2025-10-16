#!/bin/bash
#
# Test Runner for Pathfinder using PHP 7.4
#
# This script runs PHPUnit tests using the system-specific PHP 7.4 installation
# instead of the default system PHP version.
#
# Usage:
#   ./run-tests.sh                    # Run all tests
#   ./run-tests.sh --filter=testName  # Run specific test
#   ./run-tests.sh --help             # Show PHPUnit help
#

# PHP 7.4 binary path
PHP_BIN="/usr/local/php74/bin/php"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if PHP 7.4 exists
if [ ! -f "$PHP_BIN" ]; then
    echo -e "${RED}Error: PHP 7.4 not found at $PHP_BIN${NC}"
    echo "Please install PHP 7.4 or update the PHP_BIN path in this script."
    exit 1
fi

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Warning: .env file not found${NC}"
    echo "Copy .env.example to .env and configure your ESI credentials:"
    echo "  cp .env.example .env"
    echo ""
    echo "Continuing with tests (some may be skipped)..."
    echo ""
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Warning: vendor directory not found${NC}"
    echo "Running composer install..."
    $PHP_BIN "$(which composer)" install
    if [ $? -ne 0 ]; then
        echo -e "${RED}Error: composer install failed${NC}"
        exit 1
    fi
fi

# Display PHP version
echo -e "${GREEN}Running tests with:${NC}"
$PHP_BIN --version
echo ""

# Run PHPUnit with all arguments passed to this script
$PHP_BIN vendor/bin/phpunit "$@"
EXIT_CODE=$?

# Display result
echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed (exit code: $EXIT_CODE)${NC}"
fi

exit $EXIT_CODE
