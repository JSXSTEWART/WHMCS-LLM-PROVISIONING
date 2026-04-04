#!/bin/bash
# Uninstall script for WHMCS LLM Provisioning System

set -e

echo "======================================"
echo "WHMCS LLM Provisioning System Uninstaller"
echo "======================================"
echo ""

# Confirm uninstallation
read -p "This will remove all containers, volumes, and data. Are you sure? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Uninstallation cancelled."
    exit 0
fi

echo ""
echo "Stopping services..."
docker-compose down -v

echo ""
echo "Removing LLM containers..."
docker ps -a | grep "llm-" | awk '{print $1}' | xargs -r docker rm -f

echo ""
echo "Removing LLM volumes..."
docker volume ls | grep "llm-" | awk '{print $2}' | xargs -r docker volume rm

echo ""
echo "Removing networks..."
docker network ls | grep "llm_network" | awk '{print $1}' | xargs -r docker network rm || true

echo ""
echo "Cleaning up directories..."
read -p "Remove data directory /var/lib/llm-containers? (yes/no): " remove_data
if [ "$remove_data" = "yes" ]; then
    sudo rm -rf /var/lib/llm-containers
    echo "Data directory removed"
fi

read -p "Remove configuration directory /etc/llm-provisioning? (yes/no): " remove_config
if [ "$remove_config" = "yes" ]; then
    sudo rm -rf /etc/llm-provisioning
    echo "Configuration directory removed"
fi

echo ""
echo "Removing WHMCS module..."
read -p "Enter WHMCS installation path (leave empty to skip): " whmcs_path
if [ -n "$whmcs_path" ] && [ -d "$whmcs_path/modules/servers/llmprovisioning" ]; then
    sudo rm -rf "$whmcs_path/modules/servers/llmprovisioning"
    echo "WHMCS module removed"
fi

echo ""
echo "======================================"
echo "Uninstallation completed"
echo "======================================"
echo ""
echo "The following items were retained:"
echo "  - Docker and Docker Compose"
echo "  - NVIDIA Docker runtime"
echo "  - System packages"
echo ""
echo "To completely remove Docker:"
echo "  sudo apt remove docker-ce docker-ce-cli containerd.io"
echo ""
