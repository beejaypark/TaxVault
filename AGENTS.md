# Repository instructions

- Before changing deployment configuration, read `.agents/skills/deployment-and-development/SKILL.md` and keep local development, staging, and production release environments separate.
- Before every `git commit` or `git push`, read `.agents/skills/deployment-and-development/SKILL.md` and complete its pre-commit/push checklist against the exact changes. This instruction does not authorize a commit or push by itself.
- After every successful push, show the concrete VPS commands appropriate to the pushed changes in the final response, and mark required and unnecessary VPS steps clearly. Explicitly say when no VPS action is needed. Use `docs/vps-deployment.md` as the command source. Do not deploy to a VPS unless the user separately requests deployment.
- Before upgrading the frontend or backend platform (such as Angular, Laravel, or PHP), ask the user whether to proceed.
