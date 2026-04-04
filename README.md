# WHMCS LLM Container Provisioning System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docker](https://img.shields.io/badge/Docker-20.10%2B-blue.svg)](https://www.docker.com/)
[![WHMCS](https://img.shields.io/badge/WHMCS-8.0%2B-green.svg)](https://www.whmcs.com/)

A complete, production-ready platform for provisioning GPU-accelerated LLM inference containers through WHMCS billing, integrated with CyberPanel and Portainer for container orchestration.

## 🚀 Quick Start

```bash
# Clone the repository
git clone https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING.git
cd WHMCS-LLM-PROVISIONING

# Run installation script
sudo bash scripts/install.sh

# Configure your environment
nano .env

# Start services
docker-compose up -d
```

## ✨ Features

- **🔄 Automated Provisioning**: Deploy LLM containers automatically through WHMCS
- **🎮 GPU Acceleration**: Full support for NVIDIA GPUs (T4, A10, A100)
- **🤖 Multiple Models**: Pre-configured support for Llama 2, Mistral, CodeLlama, Phi-2, and custom models
- **🐳 Portainer Integration**: Manage containers through intuitive web interface
- **🌐 CyberPanel Integration**: Automatic reverse proxy with SSL certificates
- **📊 Monitoring**: Real-time metrics with Prometheus and Grafana
- **🔒 Security**: SSL/TLS, API authentication, rate limiting, and security headers
- **💰 Billing**: Complete WHMCS module for automated billing and lifecycle management

## 📋 System Requirements

### Minimum Requirements
- **OS**: Linux (Ubuntu 20.04+ or CentOS 8+)
- **CPU**: 4+ cores
- **RAM**: 16GB (32GB+ recommended for GPU workloads)
- **Storage**: 100GB+ SSD
- **Docker**: 20.10+
- **Docker Compose**: 1.29+

### Optional
- **GPU**: NVIDIA GPU with 16GB+ VRAM
- **WHMCS**: Version 8.0+
- **CyberPanel**: Latest version

## 🔧 Installation

### Quick Install

```bash
sudo bash scripts/install.sh
```

The installer will:
- ✅ Install Docker and Docker Compose
- ✅ Install NVIDIA Docker runtime (if GPU detected)
- ✅ Generate secure passwords
- ✅ Create SSL certificates
- ✅ Set up directory structure
- ✅ Deploy all services

### Manual Installation

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for detailed deployment instructions.

## 📖 Documentation

- **[Installation Guide](docs/README.md)**: Complete setup instructions
- **[API Documentation](docs/API.md)**: API reference and examples
- **[Deployment Guide](docs/DEPLOYMENT.md)**: Production deployment best practices
- **[Contributing](CONTRIBUTING.md)**: How to contribute to the project
- **[Changelog](CHANGELOG.md)**: Version history and updates

## 🏗️ Architecture

```
┌─────────────────┐
│     WHMCS       │  Billing & Client Management
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  LLM Module     │  Container Lifecycle Management
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│   Portainer     │  Container Orchestration
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────┐
│  Docker Engine  │────▶│ LLM Containers│
└─────────────────┘     └──────────────┘
         │
         ▼
┌─────────────────┐
│   CyberPanel    │  Reverse Proxy & SSL
└─────────────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────┐
│  Prometheus     │────▶│   Grafana    │  Monitoring
└─────────────────┘     └──────────────┘
```

## 🎯 Use Cases

- **AI-as-a-Service**: Offer LLM inference as a hosted service
- **Enterprise Solutions**: Private LLM deployments for businesses
- **Development Platforms**: Provide AI tools for developers
- **Research**: Scalable infrastructure for AI research
- **Education**: Learning platforms with AI capabilities

## 🛠️ Configuration

### WHMCS Module Setup

1. Copy module to WHMCS:
   ```bash
   cp -r modules/servers/llmprovisioning /path/to/whmcs/modules/servers/
   ```

2. Configure in WHMCS Admin:
   - Setup → Servers → Add New Server
   - Select "llmprovisioning" module
   - Enter Portainer credentials

3. Create products for different LLM tiers

### Supported Models

- **Llama 2**: 7B, 13B variants
- **Mistral**: 7B model
- **CodeLlama**: 7B for code generation
- **Phi-2**: Compact 2.7B model
- **Custom**: Support for custom models via Hugging Face

### GPU Options

- NVIDIA T4 (16GB)
- NVIDIA A10 (24GB)
- NVIDIA A100 (40GB/80GB)
- CPU-only mode

## 📊 Monitoring

Access monitoring dashboards:

- **Portainer**: `https://your-server:9443`
- **Prometheus**: `http://your-server:9090`
- **Grafana**: `http://your-server:3000`

Default credentials are in `secrets/` directory.

## 🔒 Security

- SSL/TLS encryption for all connections
- API key authentication
- Rate limiting (10 req/s default)
- Container isolation
- Security headers (HSTS, XSS protection)
- Regular security updates

See [Security Best Practices](docs/DEPLOYMENT.md#security-hardening) for more details.

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Hugging Face](https://huggingface.co/) - LLM models and inference
- [Portainer](https://www.portainer.io/) - Container management
- [CyberPanel](https://cyberpanel.net/) - Web hosting control
- [WHMCS](https://www.whmcs.com/) - Billing platform

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING/issues)
- **Documentation**: [docs/](docs/)
- **Discussions**: [GitHub Discussions](https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING/discussions)

## 🗺️ Roadmap

- [ ] Multi-node distributed deployment
- [ ] Auto-scaling based on load
- [ ] Additional LLM model support
- [ ] Web UI for management
- [ ] Kubernetes support
- [ ] Cloud provider integrations

See [CHANGELOG.md](CHANGELOG.md) for detailed roadmap.

---

**Made with ❤️ for the AI and hosting communities**