# Maids.ng — Project Context for Claude Code / OpenClaude

Read `AGENTS.md` for the full codebase map (directory structure, data model, controllers, services, conventions). It exists to avoid re-reading the entire codebase each session.

## Key Rules

- **Schema authority:** `database/database.sql` — trust it over migrations
- **Services contain business logic** — controllers should be thin
- **Roles:** `admin`, `maid`, `employer` (Spatie Permission)
- **Inertia SSR** for web pages, **JSON** for API routes
- **Tailwind CSS** + shadcn-style ui components
- **Don't touch** `legacy-v1/` or `maids-ng-v2/` — root is the active v2 codebase
- **Debug scripts** (`fix-*.php`, `diagnose-*.php`, `check.php`) are one-off tools, not app code

## graphify

This project has a graphify knowledge graph at graphify-out/.

Rules:
- Before answering architecture or codebase questions, read graphify-out/GRAPH_REPORT.md for god nodes and community structure
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- For cross-module "how does X relate to Y" questions, prefer `graphify query "<question>"`, `graphify path "<A>" "<B>"`, or `graphify explain "<concept>"` over grep — these traverse the graph's EXTRACTED + INFERRED edges instead of scanning files
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)
