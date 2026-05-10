# Contributing to Töö Värk

Thank you for considering a contribution! This document explains how to get involved.

## Core Principles

are written in readme ans spec files. 

## How to Contribute

1. **Fork** the repository and create a feature branch from `main`.
2. **Make your changes** in the `src/` files (never edit `index_release.php` directly).
3. **Test locally** — (`php -S localhost:8000` )
4. **Recompile** — run `compile.php`  to ensure the single-file build still works.
5. **Open a Pull Request** with a clear description of what you changed and why.

## Code Style

- Use **tabs** for indentation (matching the existing codebase).
- Keep functions short and focused.
- Add comments for any new functions and non self-explaining parts.
- Escape all user output with `htmlspecialchars()`.
- Use prepared statements for every database query — never concatenate user input into SQL!

## Reporting Bugs

Open an issue with the following details:
- PHP version (`php -v`)
- Browser and version
- Steps to reproduce
- Expected vs. actual behaviour

## Security

If you discover a security vulnerability, please **do not** open a public issue. Instead, contact the maintainer directly so the fix can be coordinated responsibly.
