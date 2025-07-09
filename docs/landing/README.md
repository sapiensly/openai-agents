# OpenAI Agents for Laravel - GitHub Pages

This directory contains the GitHub Pages website for the OpenAI Agents for Laravel package.

## Structure

- `index.md` - The main content of the GitHub Pages site
- `_config.yml` - Configuration for GitHub Pages
- `assets/css/style.scss` - Custom CSS styles for the site

## How to Update

### Content Updates

To update the content of the GitHub Pages site, edit the `index.md` file. This file uses Markdown syntax with some HTML for more complex layouts.

### Style Updates

To update the styling of the GitHub Pages site, edit the `assets/css/style.scss` file. This file uses SCSS syntax and extends the default Jekyll theme.

### Configuration Updates

To update the configuration of the GitHub Pages site, edit the `_config.yml` file. This file uses YAML syntax and configures various aspects of the Jekyll site.

## Local Development

To test the GitHub Pages site locally:

1. Install Jekyll and Bundler:
   ```bash
   gem install jekyll bundler
   ```

2. Create a Gemfile in this directory:
   ```ruby
   source 'https://rubygems.org'
   gem 'github-pages', group: :jekyll_plugins
   ```

3. Install dependencies:
   ```bash
   bundle install
   ```

4. Run the local server:
   ```bash
   bundle exec jekyll serve
   ```

5. Open your browser to `http://localhost:4000`

## Publishing

The GitHub Pages site is automatically published when changes are pushed to the repository. The site is available at:

https://sapiensly.github.io/openai-agents/

## Best Practices

- Keep the content up-to-date with the latest features and changes in the package
- Use clear, concise language that is easy to understand
- Include code examples for common use cases
- Organize content in a logical, hierarchical structure
- Use consistent formatting and styling throughout the site
- Test all links and code examples before publishing
