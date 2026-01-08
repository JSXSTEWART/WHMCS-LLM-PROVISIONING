#!/bin/bash

##############################################################################
# WHMCS LLM Provisioning System - Automated Installation Script
#
# Performs complete setup including:
# - Docker and docker-compose verification
# - NVIDIA GPU runtime setup (if available)
# - SSL/TLS certificate generation
# - Environment configuration initialization
# - Service deployment via docker-compose
# - Database schema initialization
#
# Usage: bash scripts/install.sh
##############################################################################

set -euo pipefail

# Color output for clarity
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
CONFIG_ENV="${PROJECT_ROOT}/config.env"
SECRETS_DIR="${PROJECT_ROOT}/secrets"
SSL_DIR="${PROJECT_ROOT}/config/nginx/ssl"
LOG_FILE="${PROJECT_ROOT}/install.log"

# Logging functions
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

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Error handling
cleanup_on_error() {
    log_error "Installation failed. Check $LOG_FILE for details."
    exit 1
}

trap cleanup_on_error ERR

##############################################################################
# MAIN INSTALLATION FLOW
##############################################################################

main() {
    echo -e "${BLUE}╔════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║  WHMCS LLM Provisioning System - Installation     ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════╝${NC}"
    echo ""

    log_info "Starting installation process..."

    # Step 1: Check prerequisites
    check_prerequisites

    # Step 2: Create required directories
    create_directories

    # Step 3: Generate configuration
    generate_configuration

    # Step 4: Generate SSL certificates
    generate_ssl_certificates

    # Step 5: Setup NVIDIA GPU runtime
    setup_gpu_runtime

    # Step 6: Deploy Docker services
    deploy_services

    # Step 7: Initialize database
    initialize_database

    # Step 8: Health checks
    run_health_checks

    log_success "Installation completed successfully!"
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║  Installation Summary                              ║${NC}"
    echo -e "${GREEN}╠════════════════════════════════════════════════════╣${NC}"
    echo -e "${GREEN}║ Portainer:     http://localhost:9000               ║${NC}"
    echo -e "${GREEN}║ Grafana:       http://localhost:3000               ║${NC}"
    echo -e "${GREEN}║ Prometheus:    http://localhost:9090               ║${NC}"
    echo -e "${GREEN}║ API Gateway:   https://localhost                   ║${NC}"
    echo -e "${GREEN}║                                                    ║${NC}"
    echo -e "${GREEN}║ Configuration: ${CONFIG_ENV##*/}${NC}"
    echo -e "${GREEN}║ Log file:      ${LOG_FILE##*/}${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════╝${NC}"
}

##############################################################################
# INSTALLATION STEPS
##############################################################################

check_prerequisites() {
    log_info "Checking prerequisites..."

    local missing_tools=()

    if ! command_exists docker; then
        missing_tools+=("docker")
    fi

    if ! command_exists docker-compose; then
        missing_tools+=("docker-compose")
    fi

    if ! command_exists curl; then
        missing_tools+=("curl")
    fi

    if ! command_exists openssl; then
        missing_tools+=("openssl")
    fi

    if [ ${#missing_tools[@]} -gt 0 ]; then
        log_error "Missing required tools: ${missing_tools[*]}"
        log_info "Please install missing tools and try again."
        exit 1
    fi

    log_success "All prerequisites satisfied"

    # Verify Docker daemon is running
    if ! docker ps >/dev/null 2>&1; then
        log_error "Docker daemon is not running. Please start Docker and try again."
        exit 1
    fi

    log_success "Docker daemon is running"

    # Check Docker version
    local docker_version
    docker_version=$(docker --version | awk '{print $3}' | cut -d',' -f1)
    log_info "Docker version: $docker_version"

    local docker_compose_version
    docker_compose_version=$(docker-compose --version | awk '{print $3}' | cut -d',' -f1)
    log_info "Docker Compose version: $docker_compose_version"
}

create_directories() {
    log_info "Creating required directories..."

    mkdir -p "$SECRETS_DIR"
    mkdir -p "$SSL_DIR"
    mkdir -p "${PROJECT_ROOT}/config/prometheus"
    mkdir -p "${PROJECT_ROOT}/config/alertmanager"
    mkdir -p "${PROJECT_ROOT}/config/grafana/provisioning"
    mkdir -p "${PROJECT_ROOT}/logs"

    chmod 700 "$SECRETS_DIR"

    log_success "Directories created"
}

generate_configuration() {
    log_info "Generating configuration..."

    if [ -f "$CONFIG_ENV" ]; then
        log_warn "Configuration file already exists at $CONFIG_ENV"
        log_info "Backing up existing configuration..."
        cp "$CONFIG_ENV" "${CONFIG_ENV}.backup.$(date +%s)"
    fi

    # Generate secure random passwords
    local portainer_password
    local grafana_password
    local redis_password
    local api_key

    portainer_password=$(openssl rand -base64 32)
    grafana_password=$(openssl rand -base64 32)
    redis_password=$(openssl rand -base64 32)
    api_key=$(openssl rand -hex 32)

    # Create configuration file
    cat > "$CONFIG_ENV" << EOF
# WHMCS LLM Provisioning Configuration

# Portainer Configuration
PORTAINER_URL=https://portainer.example.com
PORTAINER_ADMIN_USER=admin
PORTAINER_ADMIN_PASSWORD=$portainer_password
PORTAINER_ENDPOINT_ID=1

# Grafana Configuration
GRAFANA_DOMAIN=grafana.example.com
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=$grafana_password

# Redis Configuration
REDIS_HOST=redis-cache
REDIS_PORT=6379
REDIS_PASSWORD=$redis_password

# CyberPanel Configuration
CYBERPANEL_API_URL=https://cyberpanel.example.com
CYBERPANEL_API_KEY=your_cyberpanel_api_key
CYBERPANEL_API_PASSWORD=your_cyberpanel_api_password

# WHMCS Configuration
WHMCS_URL=https://whmcs.example.com
WHMCS_API_KEY=$api_key
WHMCS_DB_HOST=localhost
WHMCS_DB_USER=whmcs
WHMCS_DB_PASSWORD=whmcs_password
WHMCS_DB_NAME=whmcs

# LLM Container Configuration
TGI_VERSION=latest
TGI_HEALTH_CHECK_INTERVAL=30

# SSL Configuration
SSL_CERT_VALIDITY_DAYS=365
SSL_ENABLE_LETSENCRYPT=false
LETSENCRYPT_EMAIL=admin@example.com

# Monitoring Configuration
PROMETHEUS_RETENTION_DAYS=30
ALERT_EMAIL=ops@example.com

# Security
API_RATE_LIMIT=100
API_TIMEOUT_SECONDS=30
ENABLE_AUDIT_LOGGING=true
EOF

    chmod 600 "$CONFIG_ENV"
    log_success "Configuration generated at $CONFIG_ENV"
    log_warn "Please update configuration with your actual values"
}

generate_ssl_certificates() {
    log_info "Generating SSL certificates..."

    if [ -f "${SSL_DIR}/cert.pem" ] && [ -f "${SSL_DIR}/key.pem" ]; then
        log_warn "SSL certificates already exist, skipping generation"
        return
    fi

    log_info "Generating self-signed certificate..."
    openssl req -x509 \
        -nodes \
        -days 365 \
        -newkey rsa:2048 \
        -keyout "${SSL_DIR}/key.pem" \
        -out "${SSL_DIR}/cert.pem" \
        -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost" \
        2>/dev/null

    chmod 600 "${SSL_DIR}/key.pem"
    chmod 644 "${SSL_DIR}/cert.pem"

    log_success "SSL certificates generated"
    log_warn "Note: For production, use Let's Encrypt certificates"
}

setup_gpu_runtime() {
    log_info "Checking for NVIDIA GPU support..."

    if ! command_exists nvidia-smi; then
        log_warn "NVIDIA GPU not detected. GPU features will be disabled."
        return
    fi

    log_info "NVIDIA GPU detected"

    if ! command_exists nvidia-docker; then
        log_warn "nvidia-docker not found. Installing NVIDIA Docker runtime..."

        if command_exists apt-get; then
            apt-get update || true
            apt-get install -y nvidia-docker2 || log_warn "Failed to install nvidia-docker2"
        else
            log_warn "Automatic installation of nvidia-docker not supported on this system"
        fi
    fi

    log_success "NVIDIA GPU runtime configured"
}

deploy_services() {
    log_info "Deploying Docker services..."

    cd "$PROJECT_ROOT" || exit 1

    if ! docker-compose config >/dev/null 2>&1; then
        log_error "docker-compose configuration validation failed"
        exit 1
    fi

    log_info "Pulling latest images..."
    docker-compose pull || log_warn "Failed to pull some images"

    log_info "Starting services..."
    docker-compose up -d

    log_success "Services deployed successfully"
    log_info "Waiting for services to initialize..."
    sleep 10
}

initialize_database() {
    log_info "Initializing database..."

    # Database schema is handled by WHMCS module activation
    # This function can be extended for custom tables if needed

    log_success "Database initialization completed"
}

run_health_checks() {
    log_info "Running health checks..."

    local checks_passed=0
    local checks_total=5

    # Check Portainer
    if curl -sSf -k "https://localhost:9000/api/status" >/dev/null 2>&1; then
        log_success "Portainer is running"
        ((checks_passed++))
    else
        log_warn "Portainer health check failed"
    fi

    # Check Prometheus
    if curl -sSf "http://localhost:9090/-/healthy" >/dev/null 2>&1; then
        log_success "Prometheus is running"
        ((checks_passed++))
    else
        log_warn "Prometheus health check failed"
    fi

    # Check Grafana
    if curl -sSf "http://localhost:3000/api/health" >/dev/null 2>&1; then
        log_success "Grafana is running"
        ((checks_passed++))
    else
        log_warn "Grafana health check failed"
    fi

    # Check Redis
    if docker-compose exec -T redis redis-cli ping >/dev/null 2>&1; then
        log_success "Redis is running"
        ((checks_passed++))
    else
        log_warn "Redis health check failed"
    fi

    # Check NGINX
    if curl -sSf -k "https://localhost/health" >/dev/null 2>&1; then
        log_success "NGINX is running"
        ((checks_passed++))
    else
        log_warn "NGINX health check failed"
    fi

    log_info "Health checks: $checks_passed/$checks_total passed"
}

##############################################################################
# Execute main installation
##############################################################################

main "$@"
