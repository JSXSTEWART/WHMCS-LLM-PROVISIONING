#!/bin/bash
# Installation script for WHMCS LLM Provisioning System

set -e

echo "======================================"
echo "WHMCS LLM Provisioning System Installer"
echo "======================================"
echo ""

# Check for root privileges
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root or with sudo"
    exit 1
fi

# Check system requirements
echo "Checking system requirements..."

# Check for Docker
if ! command -v docker &> /dev/null; then
    echo "Docker is not installed. Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    systemctl enable docker
    systemctl start docker
else
    echo "✓ Docker is installed"
fi

# Check for Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo "Docker Compose is not installed. Installing..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
else
    echo "✓ Docker Compose is installed"
fi

# Check for NVIDIA Docker runtime (optional)
if command -v nvidia-smi &> /dev/null; then
    echo "✓ NVIDIA GPU detected"
    
    if ! command -v nvidia-docker &> /dev/null; then
        echo "Installing NVIDIA Docker runtime..."
        distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
        curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | apt-key add -
        curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | tee /etc/apt/sources.list.d/nvidia-docker.list
        apt-get update && apt-get install -y nvidia-docker2
        systemctl restart docker
    else
        echo "✓ NVIDIA Docker runtime is installed"
    fi
else
    echo "⚠ NVIDIA GPU not detected. GPU acceleration will not be available."
fi

# Create directories
echo ""
echo "Creating directories..."
mkdir -p /var/lib/llm-containers
mkdir -p /etc/llm-provisioning
mkdir -p secrets

# Generate secrets
echo ""
echo "Generating secrets..."
if [ ! -f secrets/portainer_password.txt ]; then
    openssl rand -base64 32 > secrets/portainer_password.txt
    echo "✓ Portainer admin password generated"
fi

if [ ! -f secrets/grafana_password.txt ]; then
    openssl rand -base64 32 > secrets/grafana_password.txt
    echo "✓ Grafana admin password generated"
fi

# Generate SSL certificates (self-signed for testing)
echo ""
echo "Generating SSL certificates..."
if [ ! -f config/nginx/ssl/cert.pem ]; then
    mkdir -p config/nginx/ssl
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout config/nginx/ssl/key.pem \
        -out config/nginx/ssl/cert.pem \
        -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"
    echo "✓ Self-signed SSL certificate generated"
fi

# Create environment file
echo ""
echo "Creating environment configuration..."
if [ ! -f .env ]; then
    cat > .env << EOF
# WHMCS LLM Provisioning Configuration

# Portainer Configuration
PORTAINER_ADMIN_PASSWORD=$(cat secrets/portainer_password.txt)

# Grafana Configuration  
GRAFANA_ADMIN_PASSWORD=$(cat secrets/grafana_password.txt)

# Hugging Face Token (required for downloading models)
HF_TOKEN=your_huggingface_token_here

# Domain Configuration
DOMAIN=localhost

# Network Configuration
SUBNET=172.20.0.0/16
EOF
    echo "✓ Environment file created (.env)"
fi

# Copy WHMCS module
echo ""
echo "Installing WHMCS module..."
read -p "Enter your WHMCS installation path (e.g., /var/www/whmcs): " WHMCS_PATH

if [ -d "$WHMCS_PATH" ]; then
    mkdir -p "$WHMCS_PATH/modules/servers/llmprovisioning"
    cp -r modules/servers/llmprovisioning/* "$WHMCS_PATH/modules/servers/llmprovisioning/"
    chown -R www-data:www-data "$WHMCS_PATH/modules/servers/llmprovisioning"
    echo "✓ WHMCS module installed"
else
    echo "⚠ WHMCS path not found. Please manually copy the module to:"
    echo "  $WHMCS_PATH/modules/servers/llmprovisioning/"
fi

# Start services
echo ""
echo "Starting services..."
docker-compose up -d

echo ""
echo "======================================"
echo "Installation Complete!"
echo "======================================"
echo ""
echo "Service URLs:"
echo "  Portainer: https://localhost:9443"
echo "  Grafana: http://localhost:3000"
echo "  Prometheus: http://localhost:9090"
echo ""
echo "Credentials:"
echo "  Portainer admin password: $(cat secrets/portainer_password.txt)"
echo "  Grafana admin password: $(cat secrets/grafana_password.txt)"
echo ""
echo "Next steps:"
echo "1. Configure Portainer at https://localhost:9443"
echo "2. Add your Hugging Face token to .env file"
echo "3. Configure the WHMCS module in your WHMCS admin panel"
echo "4. Set up CyberPanel integration (if using)"
echo ""
echo "For more information, see docs/README.md"
