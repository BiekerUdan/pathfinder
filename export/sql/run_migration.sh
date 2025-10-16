#!/bin/bash
# ============================================================================
# SDE to Universe Database Migration Runner
# ============================================================================
# This script provides a convenient wrapper for running the migration with
# configurable database names and connection parameters.
#
# Usage:
#   ./run_migration.sh [options]
#
# Options:
#   -h, --host HOST          Database host (default: localhost)
#   -P, --port PORT          Database port (default: 3306)
#   -u, --user USER          Database user (default: root)
#   -p, --password PASS      Database password (will prompt if not provided)
#   -s, --sde-db NAME        SDE database name (default: eve_sde)
#   -U, --universe-db NAME   Universe database name (default: eve_universe)
#   -b, --backup             Create backup before migration (recommended)
#   -t, --test               Test mode: show commands without executing
#   --help                   Show this help message
#
# Environment Variables:
#   DB_HOST                  Database host
#   DB_PORT                  Database port
#   DB_USER                  Database user
#   DB_PASS                  Database password
#   SDE_DB                   SDE database name
#   UNIVERSE_DB              Universe database name
#
# Examples:
#   # Basic usage (will prompt for password)
#   ./run_migration.sh -u root
#
#   # With custom database names
#   ./run_migration.sh -s my_sde_db -U my_universe_db -u dbuser
#
#   # Using environment variables
#   export DB_USER=root
#   export SDE_DB=eve_sde
#   export UNIVERSE_DB=eve_universe
#   ./run_migration.sh
#
#   # Create backup first (recommended for production)
#   ./run_migration.sh -b -u root
# ============================================================================

set -e  # Exit on error

# Default values (can be overridden by environment variables)
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
SDE_DB="${SDE_DB:-eve_sde}"
UNIVERSE_DB="${UNIVERSE_DB:-universe}"
CREATE_BACKUP=false
TEST_MODE=false

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_help() {
    grep '^#' "$0" | grep -v '#!/bin/bash' | sed 's/^# \?//'
    exit 0
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--host)
            DB_HOST="$2"
            shift 2
            ;;
        -P|--port)
            DB_PORT="$2"
            shift 2
            ;;
        -u|--user)
            DB_USER="$2"
            shift 2
            ;;
        -p|--password)
            DB_PASS="$2"
            shift 2
            ;;
        -s|--sde-db)
            SDE_DB="$2"
            shift 2
            ;;
        -U|--universe-db)
            UNIVERSE_DB="$2"
            shift 2
            ;;
        -b|--backup)
            CREATE_BACKUP=true
            shift
            ;;
        -t|--test)
            TEST_MODE=true
            shift
            ;;
        --help)
            show_help
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MIGRATION_SCRIPT="$SCRIPT_DIR/migrate_sde_to_universe.sql"

# Check if migration script exists
if [ ! -f "$MIGRATION_SCRIPT" ]; then
    print_error "Migration script not found: $MIGRATION_SCRIPT"
    exit 1
fi

# Build MySQL command arguments
MYSQL_ARGS="-h${DB_HOST} -P${DB_PORT} -u${DB_USER}"
if [ -n "$DB_PASS" ]; then
    MYSQL_ARGS="$MYSQL_ARGS -p${DB_PASS}"
else
    MYSQL_ARGS="$MYSQL_ARGS -p"
fi

# Show configuration
echo ""
echo "============================================================================"
echo "SDE to Universe Database Migration"
echo "============================================================================"
echo "Configuration:"
echo "  Host:         $DB_HOST:$DB_PORT"
echo "  User:         $DB_USER"
echo "  SDE DB:       $SDE_DB"
echo "  Universe DB:  $UNIVERSE_DB"
echo "  Backup:       $CREATE_BACKUP"
echo "  Test Mode:    $TEST_MODE"
echo "============================================================================"
echo ""

# Test mode - just show what would be done
if [ "$TEST_MODE" = true ]; then
    print_info "TEST MODE - No changes will be made"
    echo ""
    print_info "Would create temporary migration script with:"
    echo "  - SDE database: $SDE_DB"
    echo "  - Universe database: $UNIVERSE_DB"
    echo ""
    print_info "Would execute: mysql $MYSQL_ARGS < [temp_script]"
    if [ "$CREATE_BACKUP" = true ]; then
        echo ""
        print_info "Would create backup: backup_${UNIVERSE_DB}_\$(date +%Y%m%d_%H%M%S).sql"
    fi
    exit 0
fi

# Verify databases exist
print_info "Verifying database access..."

if ! mysql $MYSQL_ARGS -e "USE $SDE_DB;" 2>/dev/null; then
    print_error "Cannot access SDE database: $SDE_DB"
    print_error "Please verify database exists and credentials are correct"
    exit 1
fi

if ! mysql $MYSQL_ARGS -e "USE $UNIVERSE_DB;" 2>/dev/null; then
    print_error "Cannot access Universe database: $UNIVERSE_DB"
    print_error "Please verify database exists and credentials are correct"
    exit 1
fi

print_success "Database access verified"

# Check if Universe database has existing data
SYSTEM_COUNT=$(mysql $MYSQL_ARGS -N -e "SELECT COUNT(*) FROM $UNIVERSE_DB.system;" 2>/dev/null || echo "0")
if [ "$SYSTEM_COUNT" -gt 0 ]; then
    print_warning "Universe database already contains $SYSTEM_COUNT systems"
    echo ""
    read -p "Do you want to continue? This will add/update data (y/N): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Migration cancelled"
        exit 0
    fi
fi

# Create backup if requested
if [ "$CREATE_BACKUP" = true ]; then
    BACKUP_FILE="backup_${UNIVERSE_DB}_$(date +%Y%m%d_%H%M%S).sql"
    print_info "Creating backup: $BACKUP_FILE"

    if mysqldump $MYSQL_ARGS --single-transaction --quick $UNIVERSE_DB > "$SCRIPT_DIR/$BACKUP_FILE"; then
        print_success "Backup created: $BACKUP_FILE"
    else
        print_error "Backup failed!"
        exit 1
    fi
fi

# Create temporary migration script with substituted database names
TEMP_SCRIPT=$(mktemp)
trap "rm -f $TEMP_SCRIPT" EXIT

print_info "Preparing migration script..."
sed -e "s/eve_sde/$SDE_DB/g" -e "s/eve_universe/$UNIVERSE_DB/g" "$MIGRATION_SCRIPT" > "$TEMP_SCRIPT"

# Run migration
print_info "Starting migration (this will take several minutes)..."
print_info "Progress updates will appear as the migration runs..."
echo ""

if mysql $MYSQL_ARGS < "$TEMP_SCRIPT"; then
    echo ""
    print_success "Migration completed successfully!"
    echo ""
    echo "============================================================================"
    echo "NEXT STEPS"
    echo "============================================================================"
    echo "1. Visit your Pathfinder setup page: https://your-pathfinder.com/setup"
    echo "2. Click 'Build Systems Index' to create search indexes"
    echo "3. Click 'Build System Neighbour' to generate routing cache"
    echo "4. Click 'Build Wormholes' to populate wormhole static connections"
    echo ""
    echo "System search should now work in the map UI!"
    echo "============================================================================"
else
    print_error "Migration failed!"
    if [ "$CREATE_BACKUP" = true ]; then
        echo ""
        print_info "To restore from backup, run:"
        echo "  mysql $MYSQL_ARGS $UNIVERSE_DB < $SCRIPT_DIR/$BACKUP_FILE"
    fi
    exit 1
fi
