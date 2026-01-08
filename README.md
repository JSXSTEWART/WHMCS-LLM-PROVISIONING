# WHMCS LLM Provisioning System

> Automated deployment and management of GPU-accelerated Large Language Model (LLM) inference containers through WHMCS billing integration.

## Overview

This platform bridges WHMCS (hosting control panel) with Docker container orchestration (Portainer) to provide a complete SaaS solution for hosting LLM model inference services. It automates container provisioning, monitoring, billing integration, and lifecycle management.

### Key Features

- **Automated Provisioning**: One-click deployment of LLM containers with configurable models and GPU allocation
- **Multiple LLM Models**: Support for Llama 2, Mistral, CodeLlama, Phi, and custom models via HuggingFace
- **GPU Support**: NVIDIA GPU acceleration (T4, A10, A100, RTX, etc.)
- **WHMCS Integration**: Seamless billing, customer management, and service lifecycle
- **Reverse Proxy**: Automatic SSL/TLS setup with CyberPanel integration
- **Monitoring**: Prometheus metrics + Grafana dashboards + Alertmanager notifications
- **Resource Management**: CPU, memory, storage, and GPU allocation per service
- **High Availability**: Container restart policies, health monitoring, and failover
- **Security**: Input validation, rate limiting, SSL verification, audit logging

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      WHMCS                                   │
│            (Billing & Customer Management)                   │
└────────┬──────────────────────────────────────────────────────┘
         │
         │ (Service Lifecycle Events)
         │
┌────────▼──────────────────────────────────────────────────────┐
│           LLM Provisioning Module (PHP)                        │
│  (Create, Suspend, Unsuspend, Terminate, ViewStats)           │
└────────┬──────────────────────────────────────────────────────┘
         │
         ├──────────────────────────┬──────────────────────────┐
         │                          │                          │
┌────────▼────────────┐   ┌────────▼──────────┐   ┌──────────▼─────────┐
│  Portainer CE       │   │ CyberPanel API    │   │  NGINX Gateway     │
│  (Orchestration)    │   │  (Reverse Proxy)  │   │  (SSL Termination) │
└────────┬────────────┘   └────────┬──────────┘   └──────────┬─────────┘
         │                          │                         │
         └──────────────────┬───────┴─────────────────────────┘
                            │
         ┌──────────────────┴─────────────────────┐
         │                                        │
    ┌────▼────────┐            ┌────────────────▼──┐
    │   Docker    │            │   LLM Containers  │
    │  Containers │◄──────────►│  (TGI - HF Models)│
    └─────────────┘            └────────────────┬──┘
         │                                      │
         └──────────────────┬───────────────────┘
                            │
         ┌──────────────────┴──────────────────┐
         │                                     │
    ┌────▼─────────┐              ┌──────────▼───┐
    │ Prometheus   │              │   Grafana    │
    │ (Metrics)    │◄────────────►│ (Dashboards) │
    └─────────────┘              └──────┬───────┘
         │                              │
         │ (Alerts)                     │
         │                              │
    ┌────▼─────────────────────────────▼──┐
    │      AlertManager                     │
    │  (Alert Routing & Notifications)      │
    └───────────────────────────────────────┘
```

## Prerequisites

### System Requirements

- **Docker**: 20.10+
- **Docker Compose**: 1.29+
- **CPU**: 4+ cores (8+ for production)
- **RAM**: 16GB minimum (32GB+ recommended)
- **Disk**: 100GB+ (SSD recommended)
- **Network**: Static IP address, ports 80/443 open
- **NVIDIA GPU** (optional): For GPU acceleration

### Software Requirements

- **WHMCS**: 8.0 or higher
- **PHP**: 7.4+
- **MySQL/MariaDB**: 5.7+
- **Portainer**: CE or Business Edition
- **CyberPanel**: 2.0+ (for reverse proxy)

## Quick Start

### 1. Installation

Clone the repository and run the installation script:

```bash
git clone https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING.git
cd WHMCS-LLM-PROVISIONING

# Copy and configure environment
cp config.env.example config.env
# Edit config.env with your actual values
nano config.env

# Run installation
bash scripts/install.sh
```

### 2. Access the Services

After installation, services are available at:

- **Portainer**: https://localhost:9000 (default: admin/changeme)
- **Grafana**: https://localhost:3000 (default: admin/changeme)
- **Prometheus**: https://localhost:9090
- **API Gateway**: https://localhost

### 3. Configure WHMCS Module

1. Copy PHP module to WHMCS:
   ```bash
   cp modules/servers/llmprovisioning/llmprovisioning.php \
      /path/to/whmcs/modules/servers/llmprovisioning.php
   ```

2. In WHMCS Admin:
   - Go to Setup → Products/Services → Servers
   - Add new server with module: "LLM Provisioning"
   - Enter Portainer URL and API key

3. Create a product with LLM service type
4. Configure service options (model, memory, CPU, GPU type, storage)

### 4. Create Test Service

In WHMCS Admin or via API:
- Create service instance on your LLM provisioning server
- Monitor container creation in Portainer
- Access inference API once running

## Configuration

### Environment Variables

Key configuration files:

- `config.env` - Main configuration (start with `config.env.example`)
- `docker-compose.yml` - Service definitions
- `config/prometheus/prometheus.yml` - Metrics collection
- `config/alertmanager/alertmanager.yml` - Alert routing

### Customizing Models

Edit approved models in `modules/servers/llmprovisioning/llmprovisioning.php`:

```php
const APPROVED_MODELS = [
    'llama2-7b',
    'llama2-13b',
    'mistral-7b',
    // Add your models here
];
```

### Resource Limits

Adjust in the same file:

```php
const MIN_MEMORY_GB = 4;
const MAX_MEMORY_GB = 256;
const MIN_CPU_CORES = 1;
const MAX_CPU_CORES = 128;
```

## Management

### Health Check

```bash
bash scripts/health-check.sh          # Basic health check
bash scripts/health-check.sh --detailed  # Detailed diagnostics
```

### Monitoring Services

```bash
# View running containers
docker-compose ps

# View service logs
docker-compose logs -f <service-name>

# Access Grafana dashboards
# http://localhost:3000
```

### Container Logs

```bash
# View LLM container logs
docker logs <container-id>

# Follow logs in real-time
docker logs -f <container-id>
```

### Restart Services

```bash
# Restart all services
docker-compose restart

# Restart specific service
docker-compose restart <service-name>

# Restart with data preservation
docker-compose down && docker-compose up -d
```

## API Usage

### Inference API

Once a container is provisioned, inference is available at:

```
https://api.example.com/llm/{service-id}/api/generate
```

### Example Request

```bash
curl -X POST https://api.example.com/llm/service-123/api/generate \
  -H "Content-Type: application/json" \
  -d '{
    "inputs": "What is machine learning?",
    "parameters": {
      "max_new_tokens": 100,
      "temperature": 0.7
    }
  }'
```

See [API.md](docs/API.md) for complete API documentation.

## Production Deployment

### Before Going Live

1. **Security**:
   - Change all default passwords
   - Enable SSL with Let's Encrypt certificates
   - Configure firewall rules (only 80/443 public)
   - Enable API key rotation
   - Set up audit logging

2. **Performance**:
   - Configure Redis caching
   - Tune Prometheus retention
   - Set appropriate resource limits
   - Enable Grafana dashboards

3. **Monitoring**:
   - Configure AlertManager notifications
   - Set up Slack/email integration
   - Create custom alerts
   - Test alert routing

4. **Backup**:
   - Configure volume backups
   - Test restore procedures
   - Document recovery steps
   - Monitor backup success

See [DEPLOYMENT.md](docs/DEPLOYMENT.md) for complete production setup guide.

## Troubleshooting

### Container Won't Start

```bash
# Check container logs
docker logs <container-id>

# Check resource availability
docker stats

# Verify GPU access
nvidia-smi
```

### API Timeouts

- Increase proxy timeout in NGINX config
- Check container resource limits
- Monitor inference queue depth
- Review model size vs. GPU memory

### Service Down

```bash
# Run full health check
bash scripts/health-check.sh --detailed

# Check Docker daemon
docker ps

# Review service logs
docker-compose logs
```

See [TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) for more help.

## Support

- **Issues**: https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING/issues
- **Discussions**: https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING/discussions
- **Documentation**: See `docs/` directory

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Security Considerations

- Always use HTTPS in production
- Enable SSL certificate verification
- Regularly update Docker images
- Monitor for security advisories
- Keep WHMCS and dependencies updated
- Use strong API keys and passwords
- Enable audit logging

## License

MIT License - see [LICENSE](LICENSE) for details.

## Version

- **Latest**: 2.0.0
- **Release Date**: January 2026
- **Changelog**: See [CHANGELOG.md](CHANGELOG.md)

---

**Built with ❤️ for the hosting community**
