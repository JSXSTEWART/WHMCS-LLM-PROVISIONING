#!/bin/bash
# Health check script for LLM provisioning system

set -e

SERVICES=(
    "portainer:9443"
    "prometheus:9090"
    "grafana:3000"
)

echo "========================================"
echo "LLM Provisioning System Health Check"
echo "========================================"
echo ""

check_service() {
    local name=$1
    local endpoint=$2
    
    if curl -sSf -k "https://localhost:${endpoint}/health" > /dev/null 2>&1 || \
       curl -sSf "http://localhost:${endpoint}/health" > /dev/null 2>&1 || \
       curl -sSf -k "https://localhost:${endpoint}/-/healthy" > /dev/null 2>&1 || \
       curl -sSf "http://localhost:${endpoint}/-/healthy" > /dev/null 2>&1 || \
       curl -sSf "http://localhost:${endpoint}/api/health" > /dev/null 2>&1; then
        echo "✓ ${name} is healthy"
        return 0
    else
        echo "✗ ${name} is not responding"
        return 1
    fi
}

check_docker_service() {
    local service=$1
    
    if docker ps | grep -q "${service}"; then
        echo "✓ ${service} container is running"
        return 0
    else
        echo "✗ ${service} container is not running"
        return 1
    fi
}

# Check Docker is running
echo "Checking Docker..."
if systemctl is-active --quiet docker; then
    echo "✓ Docker service is running"
else
    echo "✗ Docker service is not running"
    exit 1
fi

echo ""
echo "Checking containers..."
for service in portainer prometheus grafana redis llm-gateway; do
    check_docker_service "$service"
done

echo ""
echo "Checking service endpoints..."
for service_info in "${SERVICES[@]}"; do
    IFS=':' read -r name port <<< "$service_info"
    check_service "$name" "$port" || true
done

echo ""
echo "Checking GPU availability..."
if command -v nvidia-smi &> /dev/null; then
    if nvidia-smi &> /dev/null; then
        echo "✓ NVIDIA GPU is available"
        nvidia-smi --query-gpu=name,memory.total,memory.used --format=csv,noheader
    else
        echo "⚠ NVIDIA GPU drivers not working properly"
    fi
else
    echo "⚠ NVIDIA GPU not detected (nvidia-smi not found)"
fi

echo ""
echo "Checking disk space..."
df -h / | tail -1 | awk '{print "Disk usage: " $5 " (" $3 " used of " $2 ")"}'

echo ""
echo "Checking memory..."
free -h | grep "Mem:" | awk '{print "Memory usage: " $3 " used of " $2}'

echo ""
echo "Checking LLM containers..."
llm_count=$(docker ps | grep -c "llm-" || true)
echo "Active LLM containers: ${llm_count}"

echo ""
echo "========================================"
echo "Health check completed"
echo "========================================"
