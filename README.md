# VendaPOS

Multi-tenant SaaS point-of-sale platform for Kenya and East Africa.

## Structure

```
apps/platform/       Central Laravel app (API + Filament panels + workers)
apps/pos-terminal/   NativePHP Desktop POS terminal
packages/core/       Shared Composer package (DTOs, enums, value objects)
infra/               Docker, Helm charts, Terraform
```

## Prerequisites

- Docker and Docker Compose
- Git

## Getting started

```bash
cp .env.example .env
docker compose up -d
make platform-install
make platform-migrate
```

## Development commands

```bash
make platform-test       # Run Pest tests for the platform app
make platform-lint       # Run Pint code style check
make platform-analyse    # Run Larastan static analysis
make terminal-test       # Run Pest tests for the terminal app
make core-test           # Run Pest tests for packages/core
```

See `Makefile` for all available commands.
