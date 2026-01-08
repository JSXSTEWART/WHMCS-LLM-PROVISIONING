# Contributing to WHMCS LLM Provisioning

Thank you for your interest in contributing! This document provides guidelines for participation.

## Code of Conduct

- Be respectful and inclusive
- Provide constructive feedback
- Report security issues privately
- Focus on technical merit

## Getting Started

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/WHMCS-LLM-PROVISIONING.git
cd WHMCS-LLM-PROVISIONING

# Create development environment
cp config.env.example config.env
# Edit config.env for development

# Run installation
bash scripts/install.sh

# Run health checks
bash scripts/health-check.sh
```

## Code Standards

### PHP Code

- Follow PSR-12 coding standards
- Use type hints for function parameters and returns
- Add PHPDoc comments for classes and methods
- Validate all inputs
- Use prepared statements for database queries
- Handle exceptions properly

### Python Code

- Follow PEP 8 style guide
- Use type hints (Python 3.7+)
- Add docstrings to functions and classes
- Implement proper error handling
- Write unit tests for new features

### Shell Scripts

- Use `set -euo pipefail` for safety
- Add help text/comments
- Validate inputs
- Handle errors gracefully
- Use consistent formatting

## Testing Requirements

Before submitting a PR, ensure:

1. **Functionality Tests**
   - Manual testing of changes
   - Verify no regressions
   - Test edge cases

2. **Code Quality**
   ```bash
   # PHP linting
   php -l modules/servers/llmprovisioning/llmprovisioning.php

   # Python linting
   python -m pylint scripts/cyberpanel_integration.py
   ```

3. **Health Checks**
   ```bash
   bash scripts/health-check.sh --detailed
   ```

## Commit Message Format

Follow conventional commits:

```
type(scope): description

[optional body]

[optional footer]
```

Types: feat, fix, docs, style, refactor, test, chore, security

Examples:
- `feat(portainer): add container restart functionality`
- `fix(validation): properly validate memory limits`
- `docs(readme): update installation instructions`
- `security(ssl): enforce certificate verification`

## Pull Request Process

1. Update documentation
2. Add relevant tests
3. Update CHANGELOG.md
4. Ensure all checks pass
5. Request review from maintainers
6. Address feedback promptly
7. Squash commits if requested

## Documentation

All features must include:

- PHPDoc/docstring comments
- README section if user-facing
- Configuration example if needed
- API endpoint documentation
- Troubleshooting tips

## Security

### Reporting Security Issues

**Do NOT open public issues for security vulnerabilities.**

Email: security@example.com with:
- Description of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (optional)

### Security Guidelines

- Always validate user input
- Use prepared statements for SQL
- Verify SSL certificates
- Never commit secrets
- Enable rate limiting
- Implement audit logging
- Follow OWASP guidelines

## Areas for Contribution

### High Priority

- Unit tests for PHP module
- GPU metrics collection
- Performance optimizations
- Security hardening
- Documentation improvements

### Good First Issues

- Documentation typos
- Code comment improvements
- Example configurations
- Testing improvements
- Minor bug fixes

## Development Workflow

### Creating a Feature

1. Create issue describing feature
2. Fork and create feature branch
3. Implement with tests
4. Submit PR with description
5. Respond to code review
6. Merge to main branch

### Fixing a Bug

1. Create issue with reproduction steps
2. Create bugfix branch
3. Add test demonstrating bug
4. Fix the bug
5. Verify test passes
6. Submit PR

### Improving Documentation

1. Identify improvement area
2. Create documentation branch
3. Update relevant files
4. Review for clarity
5. Submit PR

## Version Management

- **Semantic Versioning**: MAJOR.MINOR.PATCH
- Major: Breaking changes
- Minor: New features (backward compatible)
- Patch: Bug fixes

Example:
- 1.0.0: Initial release
- 1.1.0: Added GPU metrics
- 1.1.1: Fixed validation bug
- 2.0.0: Refactored architecture

## Release Process

1. Update version number
2. Update CHANGELOG.md
3. Update documentation
4. Create git tag
5. Build release artifacts
6. Publish release notes

## Code Review Checklist

Reviewers verify:

- ✓ Code follows style guidelines
- ✓ Tests are included and pass
- ✓ Documentation is updated
- ✓ No security vulnerabilities
- ✓ Backwards compatibility (if applicable)
- ✓ Performance impact considered
- ✓ Error handling is proper
- ✓ No hardcoded values
- ✓ Comments clarify complex logic
- ✓ Commit messages are clear

## Questions?

- **Issues**: Open a GitHub issue
- **Discussions**: Use GitHub Discussions
- **Email**: dev@example.com
- **Docs**: See documentation directory

## License

By contributing, you agree your code is licensed under the MIT License.

Thank you for making this project better! 🚀
