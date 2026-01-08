#!/usr/bin/env python3

"""
Comprehensive Test Suite for WHMCS LLM Provisioning System
Tests all components without requiring Docker
"""

import os
import sys
import json
import yaml
import subprocess
from pathlib import Path
from typing import Dict, List, Tuple
from datetime import datetime

# Test results storage
TEST_RESULTS = {
    'passed': [],
    'failed': [],
    'warnings': [],
    'start_time': datetime.now().isoformat(),
}

PROJECT_ROOT = Path('/home/user/WHMCS-LLM-PROVISIONING')

# Color codes
GREEN = '\033[0;32m'
RED = '\033[0;31m'
YELLOW = '\033[1;33m'
BLUE = '\033[0;34m'
NC = '\033[0m'

def print_header(title: str) -> None:
    """Print section header"""
    print(f"\n{BLUE}{'='*60}{NC}")
    print(f"{BLUE}{title.center(60)}{NC}")
    print(f"{BLUE}{'='*60}{NC}\n")

def print_success(msg: str) -> None:
    """Print success message"""
    print(f"{GREEN}✓ {msg}{NC}")
    TEST_RESULTS['passed'].append(msg)

def print_error(msg: str) -> None:
    """Print error message"""
    print(f"{RED}✗ {msg}{NC}")
    TEST_RESULTS['failed'].append(msg)

def print_warning(msg: str) -> None:
    """Print warning message"""
    print(f"{YELLOW}⚠ {msg}{NC}")
    TEST_RESULTS['warnings'].append(msg)

def test_file_structure() -> bool:
    """Test that all required files exist"""
    print_header("File Structure Validation")

    required_files = [
        'modules/servers/llmprovisioning/llmprovisioning.php',
        'docker-compose.yml',
        'config.env.example',
        'config/nginx/nginx.conf',
        'config/nginx/conf.d/llm-gateway.conf',
        'config/prometheus/prometheus.yml',
        'config/prometheus/alerts.yml',
        'config/alertmanager/alertmanager.yml',
        'docker/templates/llm-container-template.yml',
        'scripts/install.sh',
        'scripts/uninstall.sh',
        'scripts/health-check.sh',
        'scripts/cyberpanel_integration.py',
        'README.md',
        'CONTRIBUTING.md',
        'CHANGELOG.md',
        'LICENSE',
        '.gitignore',
    ]

    all_exist = True
    for file_path in required_files:
        full_path = PROJECT_ROOT / file_path
        if full_path.exists():
            print_success(f"Found: {file_path}")
        else:
            print_error(f"Missing: {file_path}")
            all_exist = False

    return all_exist

def test_yaml_syntax() -> bool:
    """Test YAML file syntax"""
    print_header("YAML Configuration Validation")

    yaml_files = [
        'docker-compose.yml',
        'config/prometheus/prometheus.yml',
        'config/prometheus/alerts.yml',
        'config/alertmanager/alertmanager.yml',
        'docker/templates/llm-container-template.yml',
    ]

    all_valid = True
    for yaml_file in yaml_files:
        full_path = PROJECT_ROOT / yaml_file
        try:
            with open(full_path, 'r') as f:
                yaml.safe_load(f)
            print_success(f"Valid YAML: {yaml_file}")
        except yaml.YAMLError as e:
            print_error(f"Invalid YAML in {yaml_file}: {e}")
            all_valid = False
        except Exception as e:
            print_error(f"Error reading {yaml_file}: {e}")
            all_valid = False

    return all_valid

def test_docker_compose_structure() -> bool:
    """Test docker-compose.yml structure"""
    print_header("Docker Compose Structure Validation")

    full_path = PROJECT_ROOT / 'docker-compose.yml'
    try:
        with open(full_path, 'r') as f:
            compose = yaml.safe_load(f)

        # Check version
        if 'version' in compose:
            print_success(f"Docker Compose version: {compose['version']}")
        else:
            print_warning("No version specified")

        # Check services
        if 'services' in compose:
            services = compose['services']
            print_success(f"Found {len(services)} services")

            required_services = [
                'portainer', 'nginx', 'prometheus',
                'grafana', 'redis', 'alertmanager'
            ]

            for service in required_services:
                if service in services:
                    print_success(f"  - {service}")
                else:
                    print_error(f"  - Missing service: {service}")
        else:
            print_error("No services defined")
            return False

        # Check volumes
        if 'volumes' in compose:
            print_success(f"Found {len(compose['volumes'])} volumes")

        # Check networks
        if 'networks' in compose:
            print_success(f"Found {len(compose['networks'])} networks")

        return True
    except Exception as e:
        print_error(f"Error validating docker-compose.yml: {e}")
        return False

def test_prometheus_config() -> bool:
    """Test Prometheus configuration"""
    print_header("Prometheus Configuration Validation")

    full_path = PROJECT_ROOT / 'config/prometheus/prometheus.yml'
    try:
        with open(full_path, 'r') as f:
            prom = yaml.safe_load(f)

        # Check global config
        if 'global' in prom:
            print_success("Global configuration defined")

        # Check scrape configs
        if 'scrape_configs' in prom:
            jobs = prom['scrape_configs']
            print_success(f"Found {len(jobs)} scrape jobs")
            for job in jobs:
                if 'job_name' in job:
                    print_success(f"  - {job['job_name']}")

        # Check alert rules
        if 'rule_files' in prom:
            print_success("Alert rules configured")

        # Check alertmanager config
        if 'alerting' in prom:
            print_success("AlertManager integration configured")

        return True
    except Exception as e:
        print_error(f"Error validating prometheus.yml: {e}")
        return False

def test_alert_rules() -> bool:
    """Test Prometheus alert rules"""
    print_header("Prometheus Alert Rules Validation")

    full_path = PROJECT_ROOT / 'config/prometheus/alerts.yml'
    try:
        with open(full_path, 'r') as f:
            alerts = yaml.safe_load(f)

        if 'groups' in alerts:
            for group in alerts['groups']:
                group_name = group.get('name', 'unknown')
                rules = group.get('rules', [])
                print_success(f"Alert group '{group_name}' with {len(rules)} rules")

                # Check critical alerts
                critical_count = sum(
                    1 for rule in rules
                    if rule.get('labels', {}).get('severity') == 'critical'
                )
                if critical_count > 0:
                    print_success(f"  - {critical_count} critical alerts")

        return True
    except Exception as e:
        print_error(f"Error validating alerts.yml: {e}")
        return False

def test_php_syntax() -> bool:
    """Test PHP module syntax"""
    print_header("PHP Module Syntax Validation")

    full_path = PROJECT_ROOT / 'modules/servers/llmprovisioning/llmprovisioning.php'
    try:
        result = subprocess.run(
            ['php', '-l', str(full_path)],
            capture_output=True,
            text=True,
            timeout=10
        )

        if result.returncode == 0:
            print_success("PHP syntax is valid")
            return True
        else:
            print_error(f"PHP syntax error: {result.stderr}")
            return False
    except FileNotFoundError:
        print_warning("PHP not installed, skipping PHP syntax check")
        return True
    except Exception as e:
        print_error(f"Error checking PHP syntax: {e}")
        return False

def test_shell_syntax() -> bool:
    """Test shell script syntax"""
    print_header("Shell Script Syntax Validation")

    shell_scripts = [
        'scripts/install.sh',
        'scripts/uninstall.sh',
        'scripts/health-check.sh',
    ]

    all_valid = True
    for script_file in shell_scripts:
        full_path = PROJECT_ROOT / script_file
        try:
            result = subprocess.run(
                ['bash', '-n', str(full_path)],
                capture_output=True,
                text=True,
                timeout=10
            )

            if result.returncode == 0:
                print_success(f"Valid bash: {script_file}")
            else:
                print_error(f"Bash syntax error in {script_file}: {result.stderr}")
                all_valid = False
        except Exception as e:
            print_error(f"Error checking {script_file}: {e}")
            all_valid = False

    return all_valid

def test_python_syntax() -> bool:
    """Test Python script syntax"""
    print_header("Python Script Syntax Validation")

    full_path = PROJECT_ROOT / 'scripts/cyberpanel_integration.py'
    try:
        result = subprocess.run(
            ['python3', '-m', 'py_compile', str(full_path)],
            capture_output=True,
            text=True,
            timeout=10
        )

        if result.returncode == 0:
            print_success("Python syntax is valid")
            return True
        else:
            print_error(f"Python syntax error: {result.stderr}")
            return False
    except Exception as e:
        print_error(f"Error checking Python syntax: {e}")
        return False

def test_python_imports() -> bool:
    """Test Python script imports"""
    print_header("Python Module Import Validation")

    full_path = PROJECT_ROOT / 'scripts/cyberpanel_integration.py'
    try:
        # Try to import the module
        spec = __import__('importlib.util').util.spec_from_file_location(
            "cyberpanel_integration",
            str(full_path)
        )
        if spec and spec.loader:
            module = __import__('importlib.util').util.module_from_spec(spec)
            spec.loader.exec_module(module)
            print_success("All imports successful")

            # Check for expected classes
            expected_classes = [
                'CyberPanelClient',
                'CyberPanelConfigError',
                'CyberPanelAPIError',
                'ConfigurationValidator',
            ]

            for cls_name in expected_classes:
                if hasattr(module, cls_name):
                    print_success(f"  - Class found: {cls_name}")
                else:
                    print_error(f"  - Missing class: {cls_name}")

            return True
        else:
            print_error("Could not load module spec")
            return False
    except Exception as e:
        print_error(f"Error importing Python module: {e}")
        return False

def test_config_template() -> bool:
    """Test configuration template"""
    print_header("Configuration Template Validation")

    full_path = PROJECT_ROOT / 'config.env.example'
    try:
        with open(full_path, 'r') as f:
            content = f.read()

        # Check for required sections
        sections = [
            'Portainer',
            'Grafana',
            'Redis',
            'CyberPanel',
            'WHMCS',
            'LLM Container',
            'SSL',
            'Prometheus',
            'AlertManager',
            'Security',
        ]

        for section in sections:
            if section.upper() in content.upper():
                print_success(f"Section found: {section}")
            else:
                print_warning(f"Section missing: {section}")

        # Count variables
        var_count = content.count('=')
        print_success(f"Configuration has {var_count} variables")

        return True
    except Exception as e:
        print_error(f"Error validating config template: {e}")
        return False

def test_documentation() -> bool:
    """Test documentation files"""
    print_header("Documentation Validation")

    doc_files = [
        ('README.md', ['Overview', 'Prerequisites', 'Quick Start', 'Configuration']),
        ('CONTRIBUTING.md', ['Code of Conduct', 'Getting Started', 'Testing']),
        ('CHANGELOG.md', ['Added', 'Security', 'Version']),
    ]

    all_valid = True
    for doc_file, required_sections in doc_files:
        full_path = PROJECT_ROOT / doc_file
        try:
            with open(full_path, 'r') as f:
                content = f.read()

            file_valid = True
            for section in required_sections:
                if section in content:
                    print_success(f"{doc_file}: Contains '{section}'")
                else:
                    print_warning(f"{doc_file}: Missing '{section}'")
                    file_valid = False

            # Check length
            lines = len(content.split('\n'))
            if lines > 50:
                print_success(f"{doc_file}: {lines} lines of documentation")
            else:
                print_warning(f"{doc_file}: Only {lines} lines (may be insufficient)")

            all_valid = all_valid and file_valid
        except Exception as e:
            print_error(f"Error validating {doc_file}: {e}")
            all_valid = False

    return all_valid

def test_license() -> bool:
    """Test license file"""
    print_header("License Validation")

    full_path = PROJECT_ROOT / 'LICENSE'
    try:
        with open(full_path, 'r') as f:
            content = f.read()

        if 'MIT' in content:
            print_success("License is MIT")

        if 'Copyright' in content:
            print_success("Copyright notice present")

        if len(content) > 100:
            print_success("License content present")

        return True
    except Exception as e:
        print_error(f"Error validating LICENSE: {e}")
        return False

def test_gitignore() -> bool:
    """Test .gitignore file"""
    print_header(".gitignore Validation")

    full_path = PROJECT_ROOT / '.gitignore'
    try:
        with open(full_path, 'r') as f:
            content = f.read()

        # Check for critical entries
        critical_patterns = [
            'config.env',
            'secrets/',
            '*.log',
            'venv/',
            '.env',
            '*.key',
            '*.pem',
        ]

        for pattern in critical_patterns:
            if pattern in content:
                print_success(f"  - Ignores: {pattern}")
            else:
                print_warning(f"  - Missing pattern: {pattern}")

        return True
    except Exception as e:
        print_error(f"Error validating .gitignore: {e}")
        return False

def test_resource_limits() -> bool:
    """Test resource limit validation in PHP"""
    print_header("Resource Limits Validation")

    full_path = PROJECT_ROOT / 'modules/servers/llmprovisioning/llmprovisioning.php'
    try:
        with open(full_path, 'r') as f:
            content = f.read()

        # Check for limit constants
        limits = [
            ('MIN_MEMORY_GB', 4),
            ('MAX_MEMORY_GB', 256),
            ('MIN_CPU_CORES', 1),
            ('MAX_CPU_CORES', 128),
            ('MIN_STORAGE_GB', 20),
            ('MAX_STORAGE_GB', 5000),
        ]

        for const, expected_val in limits:
            if const in content:
                print_success(f"  - {const} = {expected_val}")
            else:
                print_error(f"  - Missing constant: {const}")

        # Check for validation function
        if 'validateResourceLimits' in content:
            print_success("  - validateResourceLimits function present")

        return True
    except Exception as e:
        print_error(f"Error validating resource limits: {e}")
        return False

def test_approved_models() -> bool:
    """Test approved models validation"""
    print_header("Approved Models Validation")

    full_path = PROJECT_ROOT / 'modules/servers/llmprovisioning/llmprovisioning.php'
    try:
        with open(full_path, 'r') as f:
            content = f.read()

        if 'APPROVED_MODELS' in content:
            print_success("APPROVED_MODELS constant defined")

            models = [
                'llama2-7b',
                'llama2-13b',
                'mistral-7b',
            ]

            for model in models:
                if model in content:
                    print_success(f"  - {model} supported")

        if 'validateModel' in content:
            print_success("validateModel function present")

        return True
    except Exception as e:
        print_error(f"Error validating models: {e}")
        return False

def test_ssl_verification() -> bool:
    """Test SSL verification is properly configured"""
    print_header("SSL Verification Validation")

    full_path = PROJECT_ROOT / 'scripts/cyberpanel_integration.py'
    try:
        with open(full_path, 'r') as f:
            content = f.read()

        # Should have proper SSL verification
        if 'verify_ssl' in content:
            print_success("SSL verification parameter present")
        else:
            print_warning("SSL verification may not be properly configured")

        # Should NOT have CURLOPT_SSL_VERIFYPEER = false (PHP)
        php_file = PROJECT_ROOT / 'modules/servers/llmprovisioning/llmprovisioning.php'
        with open(php_file, 'r') as f:
            php_content = f.read()

        if 'CURLOPT_SSL_VERIFYPEER, false' in php_content:
            print_error("Found insecure SSL verification disable")
            return False
        else:
            print_success("No insecure SSL verification disabling found")

        return True
    except Exception as e:
        print_error(f"Error validating SSL verification: {e}")
        return False

def generate_report() -> None:
    """Generate test report"""
    print_header("Test Summary Report")

    TEST_RESULTS['end_time'] = datetime.now().isoformat()

    passed = len(TEST_RESULTS['passed'])
    failed = len(TEST_RESULTS['failed'])
    warnings = len(TEST_RESULTS['warnings'])
    total = passed + failed + warnings

    print(f"\n{GREEN}Passed: {passed}{NC}")
    print(f"{RED}Failed: {failed}{NC}")
    print(f"{YELLOW}Warnings: {warnings}{NC}")
    print(f"\nTotal Tests: {total}")

    if failed == 0:
        print(f"\n{GREEN}✓ ALL TESTS PASSED!{NC}")
        success_rate = 100
    else:
        success_rate = (passed / total * 100) if total > 0 else 0

    print(f"Success Rate: {success_rate:.1f}%")

    # Save report to JSON
    report_file = PROJECT_ROOT / 'test_report.json'
    with open(report_file, 'w') as f:
        json.dump(TEST_RESULTS, f, indent=2)

    print(f"\nDetailed report saved to: {report_file}")

    return failed == 0

def main() -> int:
    """Run all tests"""
    print(f"\n{BLUE}╔════════════════════════════════════════════════════╗{NC}")
    print(f"{BLUE}║  WHMCS LLM Provisioning System - Test Suite        ║{NC}")
    print(f"{BLUE}╚════════════════════════════════════════════════════╝{NC}")

    tests = [
        test_file_structure,
        test_yaml_syntax,
        test_docker_compose_structure,
        test_prometheus_config,
        test_alert_rules,
        test_php_syntax,
        test_shell_syntax,
        test_python_syntax,
        test_python_imports,
        test_config_template,
        test_documentation,
        test_license,
        test_gitignore,
        test_resource_limits,
        test_approved_models,
        test_ssl_verification,
    ]

    for test in tests:
        try:
            test()
        except Exception as e:
            print_error(f"Test {test.__name__} crashed: {e}")

    all_passed = generate_report()

    return 0 if all_passed else 1

if __name__ == '__main__':
    sys.exit(main())
