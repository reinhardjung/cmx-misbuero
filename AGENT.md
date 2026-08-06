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
- Deploy via manage.misbuero.ch worker jobs
- Deploy single instances with `bin/deploy-all.sh --only <instance>` by default
- Deploying every instance requires the explicit `--all` flag
- Target server is configured via deployment environment variables


## CI/CD
- GitLab mirrors to GitHub on main branch
- Tags trigger releases


## Safety
- Never commit credentials
- Do not modify production configs automatically
