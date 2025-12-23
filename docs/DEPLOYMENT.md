# Deployment Guide

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Production Deployment](#production-deployment)
3. [High Availability Setup](#high-availability-setup)
4. [Backup and Recovery](#backup-and-recovery)
5. [Performance Tuning](#performance-tuning)
6. [Monitoring Setup](#monitoring-setup)
7. [Security Hardening](#security-hardening)

## Pre-Deployment Checklist

Before deploying to production, ensure you have:

- [ ] Server with adequate resources (see requirements)
- [ ] Domain names configured with DNS
- [ ] SSL certificates (Let's Encrypt or commercial)
- [ ] WHMCS installation configured
- [ ] Portainer instance set up
- [ ] Database backups configured
- [ ] Monitoring tools ready
- [ ] Firewall rules configured
- [ ] SMTP server for notifications

## Production Deployment

### 1. Server Preparation

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y git curl wget vim htop iotop

# Configure firewall
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw allow 9443/tcp  # Portainer
sudo ufw enable

# Optimize kernel for containers
cat >> /etc/sysctl.conf << EOF
net.ipv4.ip_forward=1
net.bridge.bridge-nf-call-iptables=1
net.bridge.bridge-nf-call-ip6tables=1
vm.max_map_count=262144
fs.file-max=2097152
EOF

sudo sysctl -p
```

### 2. SSL Certificate Setup

#### Using Let's Encrypt

```bash
# Install certbot
sudo apt install -y certbot

# Generate certificates
sudo certbot certonly --standalone -d portainer.yourdomain.com
sudo certbot certonly --standalone -d llm-api.yourdomain.com
sudo certbot certonly --standalone -d grafana.yourdomain.com

# Copy certificates to config directory
sudo cp /etc/letsencrypt/live/portainer.yourdomain.com/fullchain.pem config/nginx/ssl/cert.pem
sudo cp /etc/letsencrypt/live/portainer.yourdomain.com/privkey.pem config/nginx/ssl/key.pem

# Set up auto-renewal
sudo crontab -e
# Add: 0 3 * * * certbot renew --quiet && docker-compose restart llm-gateway
```

### 3. Environment Configuration

```bash
# Copy example config
cp config.env.example .env

# Generate secure passwords
PORTAINER_PASS=$(openssl rand -base64 32)
GRAFANA_PASS=$(openssl rand -base64 32)

# Update .env file
nano .env

# Set all production values:
# - Domain names
# - API keys
# - Passwords
# - Resource limits
# - Email configuration
```

### 4. Deploy Services

```bash
# Pull latest images
docker-compose pull

# Start services
docker-compose up -d

# Verify all containers are running
docker-compose ps

# Check logs
docker-compose logs -f
```

### 5. Configure Portainer

```bash
# Access Portainer UI
# https://portainer.yourdomain.com:9443

# 1. Create admin account
# 2. Connect to local Docker endpoint
# 3. Create API access token:
#    - User settings → Access tokens → Add token
#    - Copy token to WHMCS server configuration

# 4. Configure environment:
#    - Set resource limits
#    - Configure registries (if using private registry)
#    - Set up teams and access control
```

### 6. Configure WHMCS

```bash
# Install module
sudo cp -r modules/servers/llmprovisioning /path/to/whmcs/modules/servers/
sudo chown -R www-data:www-data /path/to/whmcs/modules/servers/llmprovisioning

# In WHMCS Admin:
# 1. System Settings → Servers → Add New Server
#    - Name: LLM Production
#    - Type: llmprovisioning
#    - Hostname: portainer.yourdomain.com
#    - Password: [Portainer API Key]
#    - Port: 9443

# 2. Create Products:
#    - Setup → Products/Services → Create Product Group
#    - Add products for each LLM model/tier
#    - Assign llmprovisioning module
#    - Configure pricing
```

## High Availability Setup

### Multi-Node Configuration

```yaml
# docker-compose.ha.yml
version: '3.8'

services:
  portainer-primary:
    image: portainer/portainer-ce:latest
    command: -H tcp://tasks.agent:9001 --tlsskipverify
    # ... other config
    
  portainer-agent:
    image: portainer/agent:latest
    deploy:
      mode: global
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /var/lib/docker/volumes:/var/lib/docker/volumes
```

### Load Balancer Configuration

```nginx
# /etc/nginx/nginx.conf - on separate load balancer

upstream llm_backend {
    least_conn;
    server node1.yourdomain.com:443 max_fails=3 fail_timeout=30s;
    server node2.yourdomain.com:443 max_fails=3 fail_timeout=30s;
    server node3.yourdomain.com:443 max_fails=3 fail_timeout=30s;
}

server {
    listen 443 ssl http2;
    server_name llm-api.yourdomain.com;
    
    ssl_certificate /etc/ssl/certs/cert.pem;
    ssl_certificate_key /etc/ssl/private/key.pem;
    
    location / {
        proxy_pass https://llm_backend;
        proxy_next_upstream error timeout http_502 http_503 http_504;
    }
}
```

## Backup and Recovery

### Automated Backup Script

```bash
#!/bin/bash
# /usr/local/bin/backup-llm-provisioning.sh

BACKUP_DIR="/var/backups/llm-provisioning"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup Portainer data
docker run --rm -v portainer_data:/data -v $BACKUP_DIR:/backup \
    alpine tar czf /backup/portainer_$DATE.tar.gz -C /data .

# Backup Grafana data
docker run --rm -v grafana_data:/data -v $BACKUP_DIR:/backup \
    alpine tar czf /backup/grafana_$DATE.tar.gz -C /data .

# Backup Prometheus data
docker run --rm -v prometheus_data:/data -v $BACKUP_DIR:/backup \
    alpine tar czf /backup/prometheus_$DATE.tar.gz -C /data .

# Backup configuration
tar czf $BACKUP_DIR/config_$DATE.tar.gz config/ .env docker-compose.yml

# Backup WHMCS database
mysqldump -u whmcs_user -p'password' whmcs_db | \
    gzip > $BACKUP_DIR/whmcs_db_$DATE.sql.gz

# Remove old backups
find $BACKUP_DIR -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
```

### Schedule Backups

```bash
# Add to crontab
sudo crontab -e

# Daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-llm-provisioning.sh >> /var/log/llm-backup.log 2>&1
```

### Recovery Procedure

```bash
# Stop services
docker-compose down

# Restore Portainer data
docker run --rm -v portainer_data:/data -v /var/backups/llm-provisioning:/backup \
    alpine tar xzf /backup/portainer_YYYYMMDD_HHMMSS.tar.gz -C /data

# Restore other volumes similarly...

# Restore configuration
tar xzf /var/backups/llm-provisioning/config_YYYYMMDD_HHMMSS.tar.gz

# Restore database
gunzip < /var/backups/llm-provisioning/whmcs_db_YYYYMMDD_HHMMSS.sql.gz | \
    mysql -u whmcs_user -p'password' whmcs_db

# Restart services
docker-compose up -d
```

## Performance Tuning

### Docker Daemon Configuration

```json
// /etc/docker/daemon.json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  },
  "storage-driver": "overlay2",
  "default-runtime": "nvidia",
  "runtimes": {
    "nvidia": {
      "path": "nvidia-container-runtime",
      "runtimeArgs": []
    }
  },
  "default-ulimits": {
    "nofile": {
      "Name": "nofile",
      "Hard": 64000,
      "Soft": 64000
    }
  },
  "max-concurrent-downloads": 10,
  "max-concurrent-uploads": 10
}
```

### System Optimization

```bash
# Increase file descriptors
echo "* soft nofile 65536" >> /etc/security/limits.conf
echo "* hard nofile 65536" >> /etc/security/limits.conf

# Optimize swap
echo "vm.swappiness=10" >> /etc/sysctl.conf
echo "vm.vfs_cache_pressure=50" >> /etc/sysctl.conf

# Apply changes
sudo sysctl -p
```

### Container Resource Tuning

```yaml
# Optimize for high-performance inference
deploy:
  resources:
    limits:
      cpus: '8'
      memory: 16G
    reservations:
      cpus: '4'
      memory: 8G
      devices:
        - driver: nvidia
          count: 1
          capabilities: [gpu]
```

## Monitoring Setup

### Prometheus Alerts

```yaml
# config/prometheus/alerts.yml - Additional production alerts
- alert: HighErrorRate
  expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.05
  for: 5m
  labels:
    severity: critical
  annotations:
    summary: "High error rate detected"
    
- alert: DiskSpaceLow
  expr: (node_filesystem_avail_bytes / node_filesystem_size_bytes) < 0.1
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "Disk space below 10%"
```

### Grafana Dashboard Import

```bash
# Import pre-built dashboards
curl -X POST http://admin:${GRAFANA_PASS}@localhost:3000/api/dashboards/import \
  -H "Content-Type: application/json" \
  -d @config/grafana/dashboards/llm-overview.json
```

## Security Hardening

### 1. Firewall Configuration

```bash
# Restrict management ports
sudo ufw deny 9090  # Prometheus
sudo ufw deny 3000  # Grafana
sudo ufw allow from trusted.ip.address to any port 9090
sudo ufw allow from trusted.ip.address to any port 3000
```

### 2. Enable AppArmor/SELinux

```bash
# AppArmor (Ubuntu/Debian)
sudo apt install apparmor apparmor-utils
sudo systemctl enable apparmor

# SELinux (CentOS/RHEL)
sudo setenforce 1
sudo sed -i 's/SELINUX=.*/SELINUX=enforcing/' /etc/selinux/config
```

### 3. Container Security

```yaml
# Add security options to docker-compose.yml
security_opt:
  - no-new-privileges:true
  - seccomp:unconfined
read_only: true
tmpfs:
  - /tmp
  - /var/run
```

### 4. Network Segmentation

```yaml
# Separate networks for different components
networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
    internal: true
  monitoring:
    driver: bridge
    internal: true
```

### 5. Secrets Management

```bash
# Use Docker secrets instead of environment variables
docker secret create portainer_api_key ./secrets/portainer_key.txt
docker secret create db_password ./secrets/db_pass.txt

# Reference in compose file
secrets:
  portainer_api_key:
    external: true
```

### 6. Regular Security Updates

```bash
#!/bin/bash
# /usr/local/bin/security-updates.sh

# Update system packages
apt update && apt upgrade -y

# Update Docker images
docker-compose pull
docker-compose up -d

# Clean up old images
docker image prune -af --filter "until=168h"

# Restart services if needed
docker-compose restart
```

Schedule monthly:
```bash
0 3 1 * * /usr/local/bin/security-updates.sh >> /var/log/security-updates.log 2>&1
```

## Post-Deployment Verification

```bash
# Check all services are running
docker-compose ps

# Verify Portainer API
curl -k https://portainer.yourdomain.com:9443/api/status

# Test container creation
# (Use WHMCS to create a test service)

# Check monitoring
curl http://localhost:9090/-/healthy  # Prometheus
curl http://localhost:3000/api/health  # Grafana

# View logs
docker-compose logs --tail=100 -f

# Check resource usage
docker stats

# Verify GPU access (if applicable)
docker run --rm --gpus all nvidia/cuda:11.8.0-base-ubuntu22.04 nvidia-smi
```

## Troubleshooting Common Issues

### Issue: Container fails to start

```bash
# Check logs
docker logs llm-<service-id>

# Check GPU availability
nvidia-smi

# Verify image exists
docker images | grep text-generation-inference

# Check resource limits
docker inspect llm-<service-id> | grep -A 10 Resources
```

### Issue: High memory usage

```bash
# Check container memory
docker stats --no-stream

# Reduce batch size in container env
MODEL_MAX_BATCH_SIZE=16

# Enable memory monitoring
watch -n 1 'free -h && docker stats --no-stream'
```

### Issue: Slow inference

```bash
# Check GPU utilization
nvidia-smi -l 1

# Verify GPU is being used
docker logs llm-<service-id> | grep -i cuda

# Check network latency
ping llm-api.yourdomain.com

# Monitor request queue
curl http://localhost:<port>/metrics | grep queue
```

## Maintenance Schedule

| Task | Frequency | Command |
|------|-----------|---------|
| Backup | Daily | `/usr/local/bin/backup-llm-provisioning.sh` |
| Security Updates | Weekly | `apt update && apt upgrade` |
| Log Rotation | Weekly | `docker-compose logs --tail=1000 > archive.log` |
| Image Updates | Monthly | `docker-compose pull && docker-compose up -d` |
| Certificate Renewal | Quarterly | `certbot renew` |
| Performance Review | Monthly | Review Grafana dashboards |
| Capacity Planning | Quarterly | Analyze growth trends |

---

For additional support, see the main [README](../README.md) or open an issue on GitHub.
