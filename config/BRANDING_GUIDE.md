# Branding Guide

Update client-specific branding in `config/branding.php`.

Keys you can change:
- `app.name`
- `assets.logo`
- `assets.favicon`
- `theme.font.family`
- `theme.font.google_url`
- `theme.colors.*`

Brand CSS system:
- Global brand overrides: `assets/css/brand.css`
- Page CSS files: `assets/css/pages/**`

Suggested per-client flow:
1. Copy project.
2. Update `config/branding.php` values.
3. Replace logo/favicons in `assets/images/`.
4. Hard refresh browser (`Ctrl+F5`).
