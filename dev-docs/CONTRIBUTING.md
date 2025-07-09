# Contributing

Thank you for considering contributing to the Sapiensly OpenAI Agents package!

## Development Setup

1. Fork the repository
2. Clone your fork locally
3. Install dependencies:
   ```bash
   composer install
   ```
4. Copy the configuration:
   ```bash
   cp config/agents.php.example config/agents.php
   ```
5. Set up your environment variables in `.env`

## Testing

This package is designed to be tested within a Laravel application context. To test the package:

1. **Create a test Laravel application:**
   ```bash
   composer create-project laravel/laravel test-app
   cd test-app
   ```

2. **Install the package:**
   ```bash
   composer require sapiensly/openai-agents
   ```

3. **Publish the configuration:**
   ```bash
   # This publishes config/agents.php to your Laravel app's config directory
   php artisan vendor:publish --tag=config --provider="Sapiensly\\OpenaiAgents\\AgentServiceProvider"
   ```

4. **Test the functionality:**
   ```bash
   # Test basic functionality
   php artisan agent:test-level1 "Hello"
   
   # Test all levels
   php artisan agent:test-all-levels "What can you do?"
   
   # Test streaming
   php artisan agent:test-streaming
   ```

## Code Style

This package follows PSR-12 coding standards. Please ensure your code follows these standards before submitting a pull request.

## Pull Request Process

1. Create a feature branch from `main`
2. Make your changes
3. Test your changes within a Laravel application
4. Update documentation if needed
5. Submit a pull request

## Commit Messages

Please use conventional commit messages:

- `feat:` for new features
- `fix:` for bug fixes
- `docs:` for documentation changes
- `style:` for code style changes
- `refactor:` for code refactoring
- `test:` for adding or updating tests
- `chore:` for maintenance tasks

## Reporting Issues

When reporting issues, please include:

- Laravel version
- Package version
- PHP version
- Steps to reproduce
- Expected behavior
- Actual behavior

## License

By contributing, you agree that your contributions will be licensed under the MIT License. 