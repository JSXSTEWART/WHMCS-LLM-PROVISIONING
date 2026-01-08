#!/bin/bash

##############################################################################
# WHMCS LLM Provisioning System - Health Check Script
#
# Monitors service health, container status, and resource usage
# Generates metrics for integration with monitoring systems
#
# Usage: bash scripts/health-check.sh [--detailed]
##############################################################################

set -euo pipefail

# Color output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DETAILED=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --detailed)
            DETAILED=true
            shift
            ;;
        *)
            shift
            ;;
    esac
done

##############################################################################
# Health Check Functions
##############################################################################

check_service_health() {
    local service=$1
    local endpoint=${2:-}
    local method=${3:-GET}

    if [ -z "$endpoint" ]; then
        # Simple container running check
        if docker ps --format "{{.Names}}" | grep -q "^${service}$"; then
            return 0
        else
            return 1
        fi
    fi

    # HTTP health check
    local response
    response=$(curl -sSf -k -X "$method" "$endpoint" 2>/dev/null || echo "")

    if [ -z "$response" ]; then
        return 1
    else
        return 0
    fi
}

check_container_resources() {
    local container=$1

    docker stats --no-stream "$container" --format "table {{.Container}}\t{{.MemUsage}}\t{{.CPUPerc}}"
}

check_disk_usage() {
    local path=${1:-.}

    df -h "$path" | tail -n 1 | awk '{print $1 "\t" $5}'
}

report_status() {
    local name=$1
    local status=$2
    local details=${3:-}

    if [ "$status" = "OK" ]; then
        echo -e "${GREEN}✓${NC} $name: OK${details:+ ($details)}"
    else
        echo -e "${RED}✗${NC} $name: FAILED${details:+ ($details)}"
    fi
}

##############################################################################
# Main Health Checks
##############################################################################

main() {
    echo "═══════════════════════════════════════════════════════"
    echo "  WHMCS LLM Provisioning System - Health Check"
    echo "═══════════════════════════════════════════════════════"
    echo ""

    local all_healthy=true

    # Check Docker daemon
    echo -e "${YELLOW}Infrastructure:${NC}"
    if docker ps >/dev/null 2>&1; then
        report_status "Docker daemon" "OK"
    else
        report_status "Docker daemon" "FAILED" "Cannot connect to Docker"
        all_healthy=false
    fi

    # Check Docker Compose
    if docker-compose --version >/dev/null 2>&1; then
        report_status "Docker Compose" "OK"
    else
        report_status "Docker Compose" "FAILED"
        all_healthy=false
    fi

    echo ""
    echo -e "${YELLOW}Services:${NC}"

    # Check Portainer
    if check_service_health "portainer" "https://localhost:9000/api/status"; then
        report_status "Portainer" "OK" "Container orchestration"
    else
        report_status "Portainer" "FAILED"
        all_healthy=false
    fi

    # Check Prometheus
    if check_service_health "prometheus" "http://localhost:9090/-/healthy"; then
        report_status "Prometheus" "OK" "Metrics collection"
    else
        report_status "Prometheus" "FAILED"
        all_healthy=false
    fi

    # Check Grafana
    if check_service_health "grafana" "http://localhost:3000/api/health"; then
        report_status "Grafana" "OK" "Visualization dashboard"
    else
        report_status "Grafana" "FAILED"
        all_healthy=false
    fi

    # Check NGINX
    if check_service_health "llm-api-gateway" "https://localhost/health"; then
        report_status "NGINX Gateway" "OK" "API gateway"
    else
        report_status "NGINX Gateway" "FAILED"
        all_healthy=false
    fi

    # Check Redis
    if docker-compose exec -T redis redis-cli ping >/dev/null 2>&1; then
        report_status "Redis Cache" "OK" "Cache layer"
    else
        report_status "Redis Cache" "FAILED"
        all_healthy=false
    fi

    # Check AlertManager
    if check_service_health "alertmanager" "http://localhost:9093/-/healthy"; then
        report_status "AlertManager" "OK" "Alert management"
    else
        report_status "AlertManager" "FAILED"
        all_healthy=false
    fi

    echo ""
    echo -e "${YELLOW}Resource Usage:${NC}"

    # Memory usage
    if docker ps --format "{{.Names}}" | grep -q portainer; then
        local portainer_mem
        portainer_mem=$(docker stats --no-stream portainer --format "{{.MemUsage}}" 2>/dev/null || echo "N/A")
        echo "Portainer memory: $portainer_mem"
    fi

    # Disk usage
    echo "Disk usage: $(check_disk_usage)"

    # Detailed information if requested
    if [ "$DETAILED" = true ]; then
        echo ""
        echo -e "${YELLOW}Container Details:${NC}"

        docker ps --filter "name=portainer|grafana|prometheus|llm-api-gateway" \
            --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

        echo ""
        echo -e "${YELLOW}Volume Status:${NC}"

        docker volume ls --filter "name=portainer|prometheus|grafana|redis|alertmanager" \
            --format "{{.Name}}\t{{.Driver}}"
    fi

    echo ""
    echo "═══════════════════════════════════════════════════════"

    if [ "$all_healthy" = true ]; then
        echo -e "${GREEN}Overall Status: HEALTHY${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}Overall Status: UNHEALTHY${NC}"
        echo "Run with --detailed flag for more information"
        echo ""
        return 1
    fi
}

##############################################################################
# Execute health checks
##############################################################################

main "$@"
