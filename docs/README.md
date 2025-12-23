# WHMCS LLM Container Provisioning System

A complete, production-ready platform for provisioning GPU-accelerated LLM inference containers through WHMCS billing, integrated with CyberPanel and Portainer for container orchestration.

## 🚀 Features

- **Automated Container Provisioning**: Deploy LLM inference containers automatically through WHMCS
- **GPU Acceleration**: Support for NVIDIA GPUs (T4, A10, A100) with automatic runtime configuration
- **Multiple LLM Models**: Pre-configured support for:
  - Llama 2 (7B, 13B)
  - Mistral 7B
  - CodeLlama 7B
  - Phi-2 2.7B
  - Custom models
- **Portainer Integration**: Manage containers through Portainer's web interface
- **CyberPanel Integration**: Automatic reverse proxy setup with SSL
- **Monitoring & Alerting**: Prometheus and Grafana for real-time monitoring
- **API Gateway**: NGINX-based gateway with rate limiting and security
- **Billing Integration**: Complete WHMCS module for automated billing

## 📋 Requirements

### System Requirements
- Linux server (Ubuntu 20.04+ or CentOS 8+ recommended)
- Docker 20.10+
- Docker Compose 1.29+
- 16GB+ RAM (32GB+ recommended for GPU workloads)
- 100GB+ storage
- NVIDIA GPU (optional, for GPU acceleration)

### Software Requirements
- WHMCS 8.0+
- PHP 7.4+ with cURL extension
- MySQL/MariaDB (for WHMCS)
- Portainer CE/BE 2.0+
- CyberPanel (optional, for reverse proxy management)

## 🔧 Installation

### Quick Start

1. Clone the repository:
```bash
git clone https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING.git
cd WHMCS-LLM-PROVISIONING
```

2. Run the installation script:
```bash
sudo bash scripts/install.sh
```

3. Configure your Hugging Face token in `.env`:
```bash
nano .env
# Set HF_TOKEN=your_token_here
```

4. Start the services:
```bash
docker-compose up -d
```

### Manual Installation

#### 1. Install Docker and Dependencies

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# For GPU support - Install NVIDIA Docker
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | sudo apt-key add -
curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | sudo tee /etc/apt/sources.list.d/nvidia-docker.list
sudo apt-get update && sudo apt-get install -y nvidia-docker2
sudo systemctl restart docker
```

#### 2. Configure Environment

```bash
# Create directories
sudo mkdir -p /var/lib/llm-containers
sudo mkdir -p /etc/llm-provisioning

# Generate secrets
mkdir -p secrets
openssl rand -base64 32 > secrets/portainer_password.txt
openssl rand -base64 32 > secrets/grafana_password.txt

# Generate SSL certificates
mkdir -p config/nginx/ssl
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout config/nginx/ssl/key.pem \
  -out config/nginx/ssl/cert.pem
```

#### 3. Install WHMCS Module

```bash
# Copy module to WHMCS installation
sudo cp -r modules/servers/llmprovisioning /path/to/whmcs/modules/servers/
sudo chown -R www-data:www-data /path/to/whmcs/modules/servers/llmprovisioning
```

#### 4. Start Services

```bash
docker-compose up -d
```

## ⚙️ Configuration

### WHMCS Module Setup

1. Log in to WHMCS Admin Panel
2. Navigate to **Setup > Products/Services > Servers**
3. Add a new server:
   - **Name**: LLM Provisioning Server
   - **Type**: llmprovisioning
   - **Hostname**: Your Portainer hostname
   - **IP Address**: Your server IP
   - **Username**: admin
   - **Password**: Your Portainer API key
   - **Port**: 9443 (default Portainer HTTPS port)

4. Create a product:
   - Navigate to **Setup > Products/Services > Products/Services**
   - Create a new product group or use existing
   - Add new product:
     - **Product Type**: Other
     - **Product Name**: LLM Container - Llama 2 7B (example)
     - **Module Settings**:
       - Module: llmprovisioning
       - Server Group: Select your LLM server
       - Configure options as needed

### Portainer Configuration

1. Access Portainer at `https://your-server:9443`
2. Create admin account using password from `secrets/portainer_password.txt`
3. Add your Docker endpoint
4. Generate API key:
   - Go to **Account settings**
   - Click **Access tokens**
   - Create new token
   - Copy token to WHMCS server configuration

### CyberPanel Integration (Optional)

1. Install CyberPanel if not already installed
2. Generate API token:
   ```bash
   cyberpanel createToken
   ```
3. Configure in WHMCS custom fields:
   - `cyberpanel_url`: https://your-cyberpanel:8090
   - `cyberpanel_token`: Your API token
   - `cyberpanel_domain`: Target domain for LLM service

## 📊 Monitoring

### Prometheus Metrics

Access Prometheus at `http://your-server:9090`

Available metrics:
- Container CPU and memory usage
- GPU utilization and memory
- Request rates and latency
- Container health status

### Grafana Dashboards

Access Grafana at `http://your-server:3000`

Default credentials:
- Username: admin
- Password: (from `secrets/grafana_password.txt`)

Pre-configured dashboards for:
- LLM container performance
- GPU utilization
- Request metrics
- System resources

## 🔒 Security

### Best Practices

1. **Use SSL/TLS**: Replace self-signed certificates with proper SSL certificates
2. **API Key Rotation**: Regularly rotate Portainer API keys
3. **Network Isolation**: Use Docker networks to isolate containers
4. **Rate Limiting**: NGINX provides rate limiting (10 req/s default)
5. **Firewall Rules**: Restrict access to management ports
6. **Regular Updates**: Keep Docker, Portainer, and base images updated

### Security Features

- API key authentication for Portainer
- Rate limiting on API endpoints
- SSL/TLS encryption for all connections
- Container isolation with Docker networks
- Security headers (HSTS, XSS Protection, etc.)
- Resource limits per container

## 🐛 Troubleshooting

### Container Won't Start

```bash
# Check container logs
docker logs llm-<service-id>

# Check Portainer logs
docker logs portainer

# Verify GPU availability (if using GPU)
nvidia-smi
```

### WHMCS Module Errors

```bash
# Check WHMCS module logs
tail -f /path/to/whmcs/modules/servers/llmprovisioning/logs/module.log

# Check WHMCS system logs
tail -f /path/to/whmcs/logs/module.log
```

### Port Conflicts

```bash
# Find what's using a port
sudo netstat -tulpn | grep <port>

# Kill process if needed
sudo kill <pid>
```

### GPU Not Detected

```bash
# Verify NVIDIA driver
nvidia-smi

# Check Docker GPU support
docker run --rm --gpus all nvidia/cuda:11.8.0-base-ubuntu22.04 nvidia-smi

# Restart Docker
sudo systemctl restart docker
```

## 📖 API Reference

### Container Management

The WHMCS module provides these functions:

- `CreateAccount`: Provision new LLM container
- `SuspendAccount`: Stop container
- `UnsuspendAccount`: Start container
- `TerminateAccount`: Remove container and volumes
- `RestartContainer`: Restart container

### Portainer API

Direct API calls are handled by the `PortainerClient` class:

```php
$client = new PortainerClient($hostname, $apiKey, $port);
$container = $client->createContainer($config);
$client->startContainer($containerId);
```

### CyberPanel API

Integration handled by `CyberPanelClient` class:

```php
$client = new CyberPanelClient($url, $token);
$client->createReverseProxy($config);
```

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📄 License

MIT License - see LICENSE file for details

## 🙏 Acknowledgments

- [Hugging Face](https://huggingface.co/) for LLM models
- [Portainer](https://www.portainer.io/) for container management
- [CyberPanel](https://cyberpanel.net/) for web hosting control
- [WHMCS](https://www.whmcs.com/) for billing integration

## 📞 Support

For issues and questions:
- GitHub Issues: [Report a bug](https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING/issues)
- Documentation: See `/docs` folder for detailed guides

## 🗺️ Roadmap

- [ ] Multi-node support for distributed deployments
- [ ] Auto-scaling based on load
- [ ] Support for more LLM models
- [ ] Web UI for direct container management
- [ ] Backup and restore functionality
- [ ] Cost optimization recommendations
- [ ] Integration with more control panels

---

Made with ❤️ for the AI and hosting communities
