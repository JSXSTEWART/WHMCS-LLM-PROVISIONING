#!/usr/bin/env python3

"""
CyberPanel Integration Module

Manages reverse proxy setup, DNS configuration, and SSL certificate provisioning
for LLM container deployments through CyberPanel API.

Features:
- Secure API communication with certificate validation
- Domain and reverse proxy management
- SSL/TLS certificate handling
- Error handling with specific exception types
- Type hints for better code clarity
"""

import sys
import json
import logging
import os
from typing import Dict, Optional, Any
from pathlib import Path

try:
    import requests
    from requests.exceptions import RequestException, ConnectionError, Timeout
except ImportError:
    print("Error: requests library not found. Install with: pip install requests")
    sys.exit(1)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler('/var/log/cyberpanel_integration.log')
    ]
)
logger = logging.getLogger(__name__)


class CyberPanelConfigError(Exception):
    """Raised when configuration is invalid"""
    pass


class CyberPanelAPIError(Exception):
    """Raised when API communication fails"""
    pass


class CyberPanelClient:
    """
    Client for communicating with CyberPanel API v2.

    Handles authentication, request signing, and error handling with
    proper SSL certificate verification.
    """

    def __init__(
        self,
        api_url: str,
        api_key: str,
        api_password: str,
        verify_ssl: bool = True,
        ca_bundle: Optional[str] = None,
        timeout: int = 30
    ) -> None:
        """
        Initialize CyberPanel API client.

        Args:
            api_url: Base URL for CyberPanel API (e.g., https://panel.example.com)
            api_key: CyberPanel API authentication key
            api_password: CyberPanel API password
            verify_ssl: Whether to verify SSL certificates (default: True)
            ca_bundle: Path to custom CA bundle for SSL verification
            timeout: Request timeout in seconds (default: 30)

        Raises:
            CyberPanelConfigError: If configuration is invalid
        """
        if not api_url or not api_url.startswith('https://'):
            raise CyberPanelConfigError("API URL must be HTTPS")

        if not api_key or not api_password:
            raise CyberPanelConfigError("API key and password are required")

        self.api_url = api_url.rstrip('/')
        self.api_key = api_key
        self.api_password = api_password
        self.timeout = timeout

        # SSL verification setup
        if verify_ssl and ca_bundle:
            if not Path(ca_bundle).exists():
                raise CyberPanelConfigError(f"CA bundle not found: {ca_bundle}")
            self.verify_ssl = ca_bundle
        else:
            self.verify_ssl = verify_ssl

        logger.info(f"Initialized CyberPanel client for {api_url}")

    def _request(
        self,
        endpoint: str,
        data: Dict[str, Any],
        method: str = 'POST'
    ) -> Dict[str, Any]:
        """
        Make HTTP request to CyberPanel API with proper error handling.

        Args:
            endpoint: API endpoint path (e.g., /api/createWebsite)
            data: Request payload as dictionary
            method: HTTP method (default: POST)

        Returns:
            Parsed JSON response from API

        Raises:
            CyberPanelAPIError: When API request fails
        """
        url = f"{self.api_url}{endpoint}"

        # Prepare headers with authentication
        headers: Dict[str, str] = {
            'X-API-Key': self.api_key,
            'Content-Type': 'application/json',
        }

        try:
            logger.debug(f"Making {method} request to {endpoint}")

            response = requests.request(
                method=method,
                url=url,
                json=data,
                headers=headers,
                verify=self.verify_ssl,
                timeout=self.timeout,
                allow_redirects=False
            )

            # Check for HTTP errors
            if response.status_code >= 400:
                error_body = response.text
                try:
                    error_data = response.json()
                    error_msg = error_data.get('message', 'Unknown error')
                except (ValueError, json.JSONDecodeError):
                    error_msg = error_body or 'Unknown error'

                raise CyberPanelAPIError(
                    f"API error (HTTP {response.status_code}): {error_msg}"
                )

            # Parse response
            try:
                result = response.json()
                logger.debug(f"API response: {result}")
                return result
            except (ValueError, json.JSONDecodeError) as e:
                raise CyberPanelAPIError(f"Invalid JSON response: {e}")

        except ConnectionError as e:
            logger.error(f"Connection error: {e}")
            raise CyberPanelAPIError(f"Failed to connect to API: {e}")
        except Timeout as e:
            logger.error(f"Request timeout: {e}")
            raise CyberPanelAPIError(f"API request timed out: {e}")
        except RequestException as e:
            logger.error(f"Request failed: {e}")
            raise CyberPanelAPIError(f"API request failed: {e}")

    def create_website(
        self,
        domain: str,
        backend_host: str,
        backend_port: int,
        email: str = "admin@example.com",
        ssl_enabled: bool = True
    ) -> Dict[str, Any]:
        """
        Create reverse proxy for backend service.

        Args:
            domain: Domain name to proxy (e.g., api.example.com)
            backend_host: Backend service hostname
            backend_port: Backend service port
            email: Email for SSL certificate (for Let's Encrypt)
            ssl_enabled: Whether to enable SSL/TLS

        Returns:
            API response with creation status

        Raises:
            CyberPanelAPIError: When creation fails
        """
        if not domain or '.' not in domain:
            raise ValueError("Invalid domain name")

        if not (1 <= backend_port <= 65535):
            raise ValueError("Invalid backend port")

        payload = {
            'domain': domain,
            'backendHost': backend_host,
            'backendPort': backend_port,
            'email': email,
            'ssl': 1 if ssl_enabled else 0,
        }

        try:
            logger.info(f"Creating reverse proxy for {domain} -> {backend_host}:{backend_port}")
            result = self._request('/api/createWebsite', payload)
            logger.info(f"Website created successfully: {domain}")
            return result
        except CyberPanelAPIError as e:
            logger.error(f"Failed to create website: {e}")
            raise

    def delete_website(self, domain: str) -> Dict[str, Any]:
        """
        Delete reverse proxy configuration and SSL certificate.

        Args:
            domain: Domain name to delete

        Returns:
            API response with deletion status

        Raises:
            CyberPanelAPIError: When deletion fails
        """
        if not domain or '.' not in domain:
            raise ValueError("Invalid domain name")

        payload = {'domain': domain}

        try:
            logger.info(f"Deleting reverse proxy for {domain}")
            result = self._request('/api/deleteWebsite', payload)
            logger.info(f"Website deleted successfully: {domain}")
            return result
        except CyberPanelAPIError as e:
            logger.error(f"Failed to delete website: {e}")
            raise

    def renew_ssl_certificate(self, domain: str, email: str) -> Dict[str, Any]:
        """
        Renew SSL certificate for domain using Let's Encrypt.

        Args:
            domain: Domain name
            email: Email for Let's Encrypt

        Returns:
            API response with renewal status

        Raises:
            CyberPanelAPIError: When renewal fails
        """
        payload = {
            'domain': domain,
            'email': email,
        }

        try:
            logger.info(f"Renewing SSL certificate for {domain}")
            result = self._request('/api/renewSSL', payload)
            logger.info(f"Certificate renewed: {domain}")
            return result
        except CyberPanelAPIError as e:
            logger.error(f"Failed to renew certificate: {e}")
            raise

    def get_website_status(self, domain: str) -> Dict[str, Any]:
        """
        Get status of website configuration.

        Args:
            domain: Domain name

        Returns:
            Website status information

        Raises:
            CyberPanelAPIError: When status check fails
        """
        payload = {'domain': domain}

        try:
            logger.info(f"Checking status for {domain}")
            result = self._request('/api/getWebsiteStatus', payload)
            return result
        except CyberPanelAPIError as e:
            logger.error(f"Failed to get status: {e}")
            raise


class ConfigurationValidator:
    """Validates configuration from environment variables"""

    @staticmethod
    def get_config() -> Dict[str, str]:
        """
        Load and validate configuration from environment.

        Returns:
            Validated configuration dictionary

        Raises:
            CyberPanelConfigError: If required variables are missing
        """
        required_vars = [
            'CYBERPANEL_API_URL',
            'CYBERPANEL_API_KEY',
            'CYBERPANEL_API_PASSWORD',
        ]

        config = {}
        missing_vars = []

        for var in required_vars:
            value = os.getenv(var)
            if not value:
                missing_vars.append(var)
            else:
                config[var] = value

        if missing_vars:
            raise CyberPanelConfigError(
                f"Missing required environment variables: {', '.join(missing_vars)}"
            )

        return config


def main() -> int:
    """
    Example usage and testing.

    Returns:
        Exit code (0 for success, 1 for failure)
    """
    try:
        # Load configuration
        config = ConfigurationValidator.get_config()

        # Initialize client with proper SSL verification
        client = CyberPanelClient(
            api_url=config['CYBERPANEL_API_URL'],
            api_key=config['CYBERPANEL_API_KEY'],
            api_password=config['CYBERPANEL_API_PASSWORD'],
            verify_ssl=True,  # Always verify in production
            ca_bundle=os.getenv('CYBERPANEL_CA_BUNDLE'),  # Optional custom CA
            timeout=30
        )

        # Test connection
        logger.info("Testing CyberPanel API connection...")

        # Create reverse proxy example
        result = client.create_website(
            domain='llm-test.example.com',
            backend_host='localhost',
            backend_port=8080,
            email='admin@example.com',
            ssl_enabled=True
        )

        print(json.dumps(result, indent=2))
        return 0

    except CyberPanelConfigError as e:
        logger.error(f"Configuration error: {e}")
        return 1
    except CyberPanelAPIError as e:
        logger.error(f"API error: {e}")
        return 1
    except Exception as e:
        logger.error(f"Unexpected error: {e}")
        return 1


if __name__ == '__main__':
    sys.exit(main())
