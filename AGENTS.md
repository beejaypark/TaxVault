# Repository instructions

- Before changing deployment configuration, read `.agents/skills/deployment-and-development/SKILL.md` and keep local development, staging, and production release environments separate.
- Before every `git commit` or `git push`, read `.agents/skills/deployment-and-development/SKILL.md` and complete its pre-commit/push checklist against the exact changes. This instruction does not authorize a commit or push by itself.
- After every successful commit and push, report the concrete VPS follow-up steps in the final response, marking required and unnecessary steps clearly. Explicitly say when no VPS action is needed. Do not deploy to a VPS unless the user separately requests deployment.
- Before upgrading the frontend or backend platform (such as Angular, Laravel, or PHP), ask the user whether to proceed.
