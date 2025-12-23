# Secrets Directory

This directory stores sensitive credentials and API keys.

**⚠️ IMPORTANT**: Never commit actual secrets to version control!

## Required Secrets

### For Docker Compose

Create the following files with your secure passwords:

1. `portainer_password.txt` - Portainer admin password
2. `grafana_password.txt` - Grafana admin password

Generate secure passwords:
```bash
openssl rand -base64 32 > secrets/portainer_password.txt
openssl rand -base64 32 > secrets/grafana_password.txt
```

### For WHMCS Module

Configure in WHMCS:
- Portainer API Key
- CyberPanel API Token (if using)

### For LLM Models

Add to `.env` file:
- `HF_TOKEN` - Hugging Face token for downloading models

## Security Best Practices

1. **Permissions**: Restrict access to this directory
   ```bash
   chmod 700 secrets/
   chmod 600 secrets/*
   ```

2. **Backup**: Store backups securely and encrypted

3. **Rotation**: Regularly rotate passwords and API keys

4. **Environment**: Use different credentials for dev/staging/prod

5. **Access**: Limit who has access to production secrets

## Example Structure

```
secrets/
├── README.md
├── portainer_password.txt
├── grafana_password.txt
└── .gitignore
```

All `.txt` files are ignored by git (see `.gitignore`).
