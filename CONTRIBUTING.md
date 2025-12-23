# Contributing to WHMCS LLM Provisioning

Thank you for your interest in contributing! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and constructive in all interactions. We aim to maintain a welcoming and inclusive community.

## How to Contribute

### Reporting Bugs

1. Check if the bug has already been reported in [Issues](https://github.com/JSXSTEWART/WHMCS-LLM-PROVISIONING/issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce
   - Expected vs actual behavior
   - System information (OS, Docker version, etc.)
   - Relevant logs or screenshots

### Suggesting Features

1. Check existing feature requests
2. Create a new issue with:
   - Clear description of the feature
   - Use cases and benefits
   - Possible implementation approach

### Pull Requests

1. Fork the repository
2. Create a feature branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Make your changes following the coding standards
4. Test thoroughly
5. Commit with clear messages:
   ```bash
   git commit -m "Add feature: description"
   ```
6. Push to your fork
7. Create a Pull Request with:
   - Clear description of changes
   - Reference to related issues
   - Screenshots for UI changes

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/WHMCS-LLM-PROVISIONING.git
cd WHMCS-LLM-PROVISIONING

# Create environment file
cp config.env.example .env

# Start development environment
docker-compose up -d
```

## Coding Standards

### PHP (WHMCS Module)

- Follow PSR-12 coding standard
- Use meaningful variable and function names
- Add PHPDoc comments for functions
- Handle errors gracefully
- Log important operations

Example:
```php
/**
 * Create a new LLM container
 *
 * @param array $params Configuration parameters
 * @return string Success message or error
 */
function llmprovisioning_CreateAccount(array $params)
{
    try {
        // Implementation
        return 'success';
    } catch (Exception $e) {
        logModuleCall('llmprovisioning', __FUNCTION__, $params, $e->getMessage());
        return $e->getMessage();
    }
}
```

### Python Scripts

- Follow PEP 8 style guide
- Use type hints
- Add docstrings for functions
- Handle exceptions properly

Example:
```python
def setup_llm_proxy(service_id: int, domain: str, port: int) -> bool:
    """
    Set up reverse proxy for LLM container.
    
    Args:
        service_id: WHMCS service ID
        domain: Domain name for the service
        port: Container port number
        
    Returns:
        True if successful, False otherwise
    """
    try:
        # Implementation
        return True
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return False
```

### Docker and Configuration Files

- Use clear, descriptive names
- Add comments for complex configurations
- Follow best practices for security
- Keep configurations DRY (Don't Repeat Yourself)

### Documentation

- Update README.md for significant changes
- Add/update API documentation in docs/API.md
- Include deployment notes in docs/DEPLOYMENT.md
- Provide examples for new features

## Testing

### Manual Testing

Before submitting a PR:

1. Test container creation
2. Test suspension/unsuspension
3. Test termination
4. Verify monitoring works
5. Check logs for errors
6. Test with different configurations

### Testing Checklist

- [ ] WHMCS module functions work correctly
- [ ] Portainer integration works
- [ ] CyberPanel integration works (if enabled)
- [ ] Containers start and run properly
- [ ] GPU allocation works (if applicable)
- [ ] Monitoring displays correct data
- [ ] No errors in logs
- [ ] Documentation is updated

## Commit Message Guidelines

Use clear, descriptive commit messages:

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, etc.)
- **refactor**: Code refactoring
- **test**: Adding or updating tests
- **chore**: Maintenance tasks

Examples:
```
feat: Add support for Mistral 7B model
fix: Resolve container startup timeout issue
docs: Update API documentation for new endpoints
refactor: Improve error handling in Portainer client
```

## Release Process

1. Update version numbers
2. Update CHANGELOG.md
3. Tag release:
   ```bash
   git tag -a v1.0.0 -m "Release version 1.0.0"
   git push origin v1.0.0
   ```

## Questions?

- Open an issue for questions
- Check existing documentation
- Review closed issues for similar problems

Thank you for contributing!
