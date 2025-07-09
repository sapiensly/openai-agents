# OpenAI Agents GitHub Pages Landing Site

This directory contains the assets and configuration for the GitHub Pages landing site for the sapiensly/openai-agents Laravel package.

## Structure

```
landing/
├── styles.css          # Custom CSS with enhanced styles and animations
├── script.js           # JavaScript for interactivity and features
├── favicon.svg         # Custom SVG favicon
├── _config.yml         # Jekyll configuration for GitHub Pages
└── README.md           # This file
```

## Features

### Enhanced Styling (`styles.css`)
- Custom animations and transitions
- Enhanced gradient backgrounds
- Improved button and card styles
- Dark mode support
- Responsive design enhancements
- Print-friendly styles

### Interactive Features (`script.js`)
- Scroll progress indicator
- Intersection Observer animations
- Enhanced smooth scrolling
- Code block copy functionality
- Animated stats counters
- Search functionality for documentation
- Theme toggle (light/dark mode)
- Mobile menu functionality
- Performance monitoring

### Custom Favicon (`favicon.svg`)
- Modern SVG design
- Represents AI/neural networks and Laravel
- Gradient colors matching the brand
- Scalable and lightweight

### Jekyll Configuration (`_config.yml`)
- SEO optimization
- Social media integration
- Analytics support
- Proper permalink structure
- Plugin configuration

## Customization

### Colors
The primary brand colors are defined in the CSS:
- Primary: `#3b82f6` (blue)
- Secondary: `#1d4ed8` (dark blue)
- Accent: `#f59e0b` (orange)

### Adding New Sections
1. Add HTML structure to `index.html`
2. Add corresponding styles to `styles.css`
3. Add any JavaScript functionality to `script.js`

### Modifying Animations
Animations are defined in `styles.css` using CSS keyframes:
- `fadeInUp`: Elements fade in from bottom
- `slideInLeft`: Elements slide in from left
- `slideInRight`: Elements slide in from right
- `pulse`: Subtle pulsing effect

### Adding Analytics
1. Uncomment and configure Google Analytics in `_config.yml`
2. Add tracking code to `index.html` if needed
3. Use the `trackEvent()` function in `script.js` for custom events

## Deployment

The site is automatically deployed when changes are pushed to the `main` branch. GitHub Pages will:

1. Use the `docs/` directory as the source
2. Process the `index.html` file as the homepage
3. Apply the Jekyll configuration from `landing/_config.yml`
4. Serve static assets from the `landing/` directory

## Local Development

To test the site locally:

1. Install Jekyll: `gem install jekyll bundler`
2. Navigate to the `docs/` directory
3. Run: `jekyll serve`
4. Visit `http://localhost:4000`

## Browser Support

The site is designed to work on:
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Performance

The site is optimized for:
- Fast loading times (< 3 seconds)
- Mobile responsiveness
- SEO best practices
- Accessibility standards

## Contributing

When modifying the landing site:

1. Test changes locally first
2. Ensure mobile responsiveness
3. Validate HTML and CSS
4. Test in multiple browsers
5. Update this README if needed

## License

This landing site follows the same MIT license as the main package. 