#!/usr/bin/env python3
"""
CyberPanel Integration Script
Handles reverse proxy setup for LLM containers
"""

import os
import sys
import json
import requests
from typing import Dict, Optional

class CyberPanelAPI:
    """CyberPanel API client"""
    
    def __init__(self, base_url: str, token: str):
        self.base_url = base_url.rstrip('/')
        self.token = token
        self.session = requests.Session()
        self.session.verify = False  # Disable SSL verification for self-signed certs
        
    def _request(self, endpoint: str, data: Dict) -> Dict:
        """Make API request to CyberPanel"""
        url = f"{self.base_url}{endpoint}"
        data['token'] = self.token
        
        try:
            response = self.session.post(url, json=data, timeout=30)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            print(f"Error making request to CyberPanel: {e}", file=sys.stderr)
            raise
    
    def create_website(self, domain: str, email: str, package: str = "Default") -> Dict:
        """Create a new website in CyberPanel"""
        return self._request('/api/createWebsite', {
            'domainName': domain,
            'ownerEmail': email,
            'packageName': package,
            'websiteOwner': 'admin',
            'ownerPassword': os.urandom(16).hex(),
        })
    
    def create_proxy(self, domain: str, target_url: str, ssl: bool = True) -> Dict:
        """Create reverse proxy configuration"""
        result = self._request('/api/createProxyRule', {
            'domain': domain,
            'url': target_url,
            'ssl': 1 if ssl else 0,
        })
        
        if ssl:
            # Request SSL certificate
            self.issue_ssl(domain)
        
        return result
    
    def issue_ssl(self, domain: str) -> Dict:
        """Issue SSL certificate for domain"""
        return self._request('/api/issueSSL', {
            'domainName': domain,
        })
    
    def delete_proxy(self, domain: str) -> Dict:
        """Delete reverse proxy configuration"""
        return self._request('/api/deleteProxyRule', {
            'domain': domain,
        })
    
    def delete_website(self, domain: str) -> Dict:
        """Delete website from CyberPanel"""
        return self._request('/api/deleteWebsite', {
            'domainName': domain,
        })


def setup_llm_proxy(service_id: int, domain: str, port: int, 
                    cyberpanel_url: str, cyberpanel_token: str,
                    client_email: str, enable_ssl: bool = True):
    """Set up CyberPanel reverse proxy for LLM container"""
    
    print(f"Setting up proxy for service {service_id}")
    print(f"  Domain: {domain}")
    print(f"  Target Port: {port}")
    
    client = CyberPanelAPI(cyberpanel_url, cyberpanel_token)
    
    try:
        # Create website if it doesn't exist
        print("Creating website...")
        client.create_website(domain, client_email)
        
        # Create reverse proxy
        print("Creating reverse proxy...")
        target_url = f"http://localhost:{port}"
        result = client.create_proxy(domain, target_url, enable_ssl)
        
        if result.get('status') == 1:
            print(f"✓ Successfully configured proxy for {domain}")
            return True
        else:
            print(f"✗ Failed to configure proxy: {result.get('errorMessage', 'Unknown error')}")
            return False
            
    except Exception as e:
        print(f"✗ Error setting up proxy: {e}", file=sys.stderr)
        return False


def cleanup_llm_proxy(domain: str, cyberpanel_url: str, cyberpanel_token: str):
    """Remove CyberPanel configuration for LLM container"""
    
    print(f"Cleaning up proxy for {domain}")
    
    client = CyberPanelAPI(cyberpanel_url, cyberpanel_token)
    
    try:
        # Delete proxy
        print("Deleting reverse proxy...")
        client.delete_proxy(domain)
        
        # Delete website
        print("Deleting website...")
        client.delete_website(domain)
        
        print(f"✓ Successfully cleaned up {domain}")
        return True
        
    except Exception as e:
        print(f"✗ Error cleaning up: {e}", file=sys.stderr)
        return False


if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='CyberPanel integration for LLM provisioning')
    subparsers = parser.add_subparsers(dest='command', help='Command to execute')
    
    # Setup command
    setup_parser = subparsers.add_parser('setup', help='Set up reverse proxy')
    setup_parser.add_argument('--service-id', type=int, required=True, help='WHMCS service ID')
    setup_parser.add_argument('--domain', required=True, help='Domain name')
    setup_parser.add_argument('--port', type=int, required=True, help='Container port')
    setup_parser.add_argument('--email', required=True, help='Client email')
    setup_parser.add_argument('--cyberpanel-url', required=True, help='CyberPanel URL')
    setup_parser.add_argument('--cyberpanel-token', required=True, help='CyberPanel API token')
    setup_parser.add_argument('--no-ssl', action='store_true', help='Disable SSL')
    
    # Cleanup command
    cleanup_parser = subparsers.add_parser('cleanup', help='Remove reverse proxy')
    cleanup_parser.add_argument('--domain', required=True, help='Domain name')
    cleanup_parser.add_argument('--cyberpanel-url', required=True, help='CyberPanel URL')
    cleanup_parser.add_argument('--cyberpanel-token', required=True, help='CyberPanel API token')
    
    args = parser.parse_args()
    
    if args.command == 'setup':
        success = setup_llm_proxy(
            args.service_id,
            args.domain,
            args.port,
            args.cyberpanel_url,
            args.cyberpanel_token,
            args.email,
            not args.no_ssl
        )
        sys.exit(0 if success else 1)
        
    elif args.command == 'cleanup':
        success = cleanup_llm_proxy(
            args.domain,
            args.cyberpanel_url,
            args.cyberpanel_token
        )
        sys.exit(0 if success else 1)
        
    else:
        parser.print_help()
        sys.exit(1)
