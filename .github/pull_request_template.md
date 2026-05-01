## Description
<!-- What does this PR do? Link the issue it resolves if applicable. Closes #X -->

## Type of change
- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] Breaking change
- [ ] Refactor / tech-debt
- [ ] Documentation
- [ ] CI/CD / infrastructure

## Checklist
- [ ] Tests added or updated (unit / smoke / integration as applicable)
- [ ] `composer test:unit` passes locally
- [ ] `composer test:smoke` passes locally
- [ ] `composer analyse` (PHPStan) passes — no new errors
- [ ] `composer cs:check` passes — code style clean
- [ ] If a migration is included, a rollback script is included too
- [ ] If a new endpoint is added, rate limiting and logging are wired in
- [ ] `.env.example` updated for any new environment variable

## Security considerations
<!-- Did this change touch auth, file handling, SQL, or user input? If yes, describe the controls. -->

## How to test
<!-- Step-by-step repro for reviewers. -->
