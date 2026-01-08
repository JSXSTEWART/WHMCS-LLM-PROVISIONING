#!/bin/bash

##############################################################################
# WHMCS LLM Provisioning System - Uninstallation Script
#
# Safely removes all services and cleans up resources
#
# Usage: bash scripts/uninstall.sh [--keep-data]
##############################################################################

set -euo pipefail

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="${PROJECT_ROOT}/uninstall.log"
KEEP_DATA=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --keep-data)
            KEEP_DATA=true
            shift
            ;;
        *)
            shift
            ;;
    esac
done

log_info() {
    echo -e "${BLUE}[INFO]${NC} $*" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $*" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $*" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $*" | tee -a "$LOG_FILE"
}

confirm() {
    local prompt="$1"
    local response

    echo -ne "${YELLOW}${prompt} (yes/no): ${NC}"
    read -r response

    [[ "$response" == "yes" ]]
}

main() {
    echo -e "${BLUE}╔════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║  WHMCS LLM Provisioning System - Uninstallation   ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════╝${NC}"
    echo ""

    log_warn "This will remove all running services"
    if [ "$KEEP_DATA" = false ]; then
        log_warn "All data will be deleted (use --keep-data to preserve volumes)"
    else
        log_info "Data will be preserved"
    fi
    echo ""

    if ! confirm "Continue with uninstallation?"; then
        log_info "Uninstallation cancelled"
        exit 0
    fi

    echo ""
    cd "$PROJECT_ROOT" || exit 1

    # Stop services
    log_info "Stopping services..."
    docker-compose down || log_warn "Some services may not have stopped cleanly"

    log_success "Services stopped"

    # Remove volumes if not keeping data
    if [ "$KEEP_DATA" = false ]; then
        log_info "Removing volumes..."
        docker-compose down -v || log_warn "Some volumes may not have been removed"
        log_success "Volumes removed"
    fi

    # Remove configuration backup
    log_info "Removing backup configurations..."
    rm -f "${PROJECT_ROOT}/config.env.backup"*

    log_success "Uninstallation completed"

    echo ""
    if [ "$KEEP_DATA" = false ]; then
        log_info "All services and data have been removed"
    else
        log_info "Services removed, data preserved"
    fi
}

main "$@"
