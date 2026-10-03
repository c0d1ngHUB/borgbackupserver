# Contributing to Borg Backup Server

Thanks for your interest in contributing! Here's how to get started.

## Reporting Bugs

- Use the [Bug Report](https://github.com/marcpope/borgbackupserver/issues/new?template=bug_report.yml) issue template
- Include your BBS version, installation type (Docker or bare metal), and steps to reproduce
- Paste relevant log output if available

## Suggesting Features

- Use the [Feature Request](https://github.com/marcpope/borgbackupserver/issues/new?template=feature_request.yml) issue template
- Describe the use case and why it would be useful

## Pull Requests

1. Fork the repository and create a branch from `main`
2. Make your changes — keep them focused on a single issue or feature
3. Test on both Docker and bare metal installs if possible
4. Submit a PR against `main` with a clear description of what changed and why

### Guidelines

- **PHP 8.1+** — no framework, vanilla PHP with Bootstrap 5 frontend
- **MySQL syntax** — this project uses MySQL/MariaDB, not SQLite
- **No direct sudo in PHP** — all privileged operations go through `bin/bbs-ssh-helper`
- **Agent changes** — logic must live in `bbs-agent.py` (the `.py` file served from the server), never in the launcher exe. The exe cannot be auto-updated.

### Development Setup

**Docker (quickest):**
```bash
cp .env.example .env
# Edit APP_URL; enable any optional environment entries in docker-compose.yml.
docker compose config --quiet
```

The default service pulls the upstream image and does not exercise local changes.
To test this checkout, enable `build: .` in `docker-compose.yml`, then run
`docker compose up -d --build` in an isolated environment. The image includes
MariaDB and ClickHouse; `/var/bbs` persists their data, Borg repositories, and SSH
keys. Keep that volume for restart/update testing.

**Bare metal:**
- Ubuntu 22.04+ with Apache, PHP 8.1+, MariaDB/MySQL 8.0
- Run `bin/bbs-install` for initial setup

### Repository checks

Install PHP 8.1+ and Composer, then run these checks from the repository root
before runtime testing:

```bash
composer validate --no-check-publish
composer install
git ls-files -z '*.php' | xargs -0 -n1 php -l
bash -n entrypoint.sh
git diff --check
```

There is no Composer test script in this snapshot. These checks validate metadata
and syntax; backup, restore, SSH authorization, and migrations still require a
disposable server/client installation.

## Security Vulnerabilities

Please do **not** open public issues for security vulnerabilities. See [SECURITY.md](SECURITY.md) for responsible disclosure instructions.
