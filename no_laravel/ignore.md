You are working in this repository. Follow these instructions in order.

1. Read `.github/copilot-instructions.md` and follow all conventions.
2. Use a test-driven approach (TDD): write tests first whenever possible.
3. Use Pest for all tests, including browser tests, so Inertia + Svelte UI flows are validated in-browser.
4. we have started from this scaffold:
   `laravel new larasvelte --using=oseughu/svelte-starter-kit`
5. Prioritize the default app first. check if work end-to-end before adding new features:
   - configure the database
   - run migrations
   - create seeders
   - seed a default user for testing
   - verify default auth/pages with tests
6. Keep tests as the safety net while implementing features.
7. Set up and use Laravel AI based on the official documentation.
8. Use MCP tools/server (Laravel Boost) whenever useful.

Output expectations:
- Share a short plan before major changes.
- After each milestone, report:
  - what was implemented
  - which tests were added or updated
  - test results
