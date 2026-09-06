# Prompt 6 reuse and adaptation report

## Evidence boundary

Prompt 6 used the completed Prompt 1–5 artifacts and the behavior already traced in the read-only legacy audit. No legacy repository, server, database, content file or secret was modified or copied during this implementation.

The Prompt 1 audit found no standalone DentNote repository or canonical DentNote schema. It did find reusable behavior in the legacy notes catalog, exam API/browser flow, generated question artifacts, Voice pipeline outputs, protected PDF delivery, and upload/storage scripts. Those findings were treated as behavioral evidence, not runtime dependencies.

## Reuse decisions

| Source capability/evidence | FANOOS decision | Concrete adaptation |
| --- | --- | --- |
| Prompt 3 resource/object/version/binding/publication schema | Reuse | Kept as the single aggregate; added metadata, review, derivation and import tables |
| Prompt 4 upload inspection, immutable filesystem addressing and signed download token | Reuse | `ContentUploadService` composes the existing classes; no second object store |
| Prompt 5 scoped RBAC, entitlement and protected authorization | Reuse | Every create/review/publish/view/delivery/exam path uses the same authorities |
| Legacy notes CRUD/catalog behavior | Extract and adapt | Generic library, academic metadata, ordering/filtering and version workflow |
| Legacy access-before-question-hydration and server scoring | Extract and adapt | `ExamService` hides answers until submission and scores immutable versions server-side |
| Legacy browser navigation/save/review behavior | Extract and adapt | Revisioned server attempts with safe question projection, save, submit and review |
| Legacy question bank/generated artifacts | Import only | Manifest importer accepts reviewed canonical sources; generated/cache files are not runtime code |
| Legacy protected PDF issuance/fingerprint behavior | Contract-preserving adaptation | Issuance-bound watermark identity, HMAC forensic ID, reauthorization and forward-protection requirement |
| Voice/slides/reference processing evidence | Adapter boundary | Structured/generated versions and derivation lineage accept outputs; no duplicate transcription/parser was written |
| Legacy cohort/course/year constants and route forks | Replace | Workspace/course/term/session IDs are data and protected by composite tenant foreign keys |
| Legacy JSON/files as mutable authority | Replace | MySQL transactions, immutable versions, import ledger and audit events |
| Legacy public anonymous notes behavior | Do not carry by default | FANOOS requires canonical identity and explicit policy; anonymous publication needs a later product decision |
| Legacy private web reader | Retire candidate | Not recreated; protected access uses the common delivery contract |

## What was deliberately not copied

- legacy branding, cohort URLs and Dentistry-specific conditional logic;
- embedded/generated PHP question banks or cache artifacts;
- raw legacy identifiers, user data, documents or production content;
- bot SQLite state, bot credentials, chat IDs or platform file IDs;
- legacy download-host fallbacks and direct public filesystem URLs;
- visual templates mixed with business/generation logic.

## DentNote treatment

DentNote is preserved as a product format, not a core module fork. A `discipline_note` resource may use `format_key=dentnote` and a structured JSON schema chosen by the content team. Another discipline can register another format value without code changes. Rendering/branding belongs in a replaceable template adapter; review, publication, storage, search, access and delivery remain common.

## Practical limitations and follow-ups

- Byte-level PDF rasterization/visible watermark rendering still needs the isolated Prompt 7 delivery worker and its verified PDF toolchain. Prompt 6 produces signed issuance/watermark material and a protected download token but does not claim to transform PDFs on the shared web host.
- There is no external summary/question generator adapter yet. Generated outputs can enter the review pipeline with lineage; credentials should be requested only after a provider is selected.
- Audio/slides/reference ingestion has a common uploaded/generated resource path, but no FANOOS worker server currently exists for transcription or rendering.
- Public anonymous content, HTML sandbox hosting, content favorites, issue reporting and advanced analytics remain explicit product choices rather than hidden compatibility behavior.

These limitations do not create calls back to legacy systems. They are bounded extensions on the new FANOOS contracts.
