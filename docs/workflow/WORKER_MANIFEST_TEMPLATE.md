# Parallel Worker Manifest Template

Copy this template for each parallel workstream. Keep it short and concrete.

```text
WORKSTREAM: <number/name>
BRANCH: <branch>
PARALLEL_BASE_SHA: <exact 40-character SHA>

RESPONSIBILITY
- <what this worker owns>

OWNED PATHS
- <path/**>

READ-ONLY / INTEGRATION-ONLY PATHS
- <path>

FORBIDDEN PATHS
- <another worker's path>

CONTRACTS CONSUMED
- <contract/version>

DEPENDENCIES
- <module/service/runtime dependency>

IMPLEMENTATION RULES
- Search FANOOS and relevant read-only legacy references before creating duplicate behavior.
- Reuse/adapt proven behavior when safe.
- Do not change shared contracts silently.
- Do not edit main, merge, deploy or force-push.
- Do not commit secrets/runtime state.
- Do not perform unrelated refactors.

TEST SCOPE
- <commands/CI checks/tests>

REQUIRED HANDOFF
- commits/diff summary
- tests run/results
- contract assumptions
- integration wiring requested
- known risks/deferred runtime checks

DEFINITION OF DONE
- scope complete
- own tests complete
- deterministic CI/checks green where available
- no secret/runtime-state leak
- no unrelated changes
- branch pushed and ready for integration
- NO MERGE
- NO DEPLOY
```

Integration Chat should reject a worker manifest with overlapping write ownership unless the overlap is explicitly serialized or reassigned.