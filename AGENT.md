# AGENT.md

## Purpose
Defines rules for automation and AI agents in this repository!

## Coding Rules
- Only PHP is allowed
- Follow WooCommerce coding standards
- No Python or Node.js scripts

## Build Process
- Create ZIP excluding:
  - .git
  - .ddev
  - .gitlab-ci.yml

## Deployment
- Deploy via Plesk Extension API
- Target server: sites.misbuero.ch

## CI/CD
- GitLab mirrors to GitHub on main branch
- Tags trigger releases

## Safety
- Never commit credentials
- Do not modify production configs automatically
