# QCM bank generation prompt — W5D

Use this document to have an LLM (or a subject expert working with one) write **X questions for one service**, and later **X questions for one sub-service (speciality)**. Everything between the two `=====` lines is the prompt: paste it as-is, fill the `{{VARIABLES}}` at the top, and send it once per service (and per level, if you generate by level).

The demo bank in `data/qcm/` (130 services × 4 levels × 10 = 5,200 questions) is generated from the service files by `tools/gen/qcm_demo.py`. It proves the engine. The production bank (target `qcm.bank_target`, default **1,000 questions per service**, all levels together) must be written by people who know the subject, with this prompt as the production line.

## How to run it

1. Pick a service slug from `data/content/services/*.json` (130 of them). Open that file and copy its JSON into `{{SERVICE_JSON}}`. The macro work file in `data/content/macros/` is useful context too.
2. Decide `{{X}}` (how many questions in this run) and `{{LEVEL_SPLIT}}`. Recommended split for 1,000 questions: L1 250, L2 300, L3 250, L4 200.
3. Ask for **at most 100 questions per reply**. Models drift after that. Run the prompt several times with a different `{{START_INDEX}}` and a different `{{FOCUS_TAGS}}` each time, so the batches do not overlap.
4. Save each reply as a `.json` file. Validate it locally (see *Validation* below).
5. Load it: portal → **QCM bank** → choose the service → **Import** (mode *merge*). The import is all-or-nothing: if one question is invalid nothing is imported and the first errors are shown. A file over 2 MB or over 5,000 questions is refused, so import in parts.
6. Have a second person (or a second model run with the *review prompt* at the end) check a random 10 % of every batch before it goes live. Set `active: false` on anything doubtful rather than deleting it.

## Levels, in plain words

| Level | Name (config/certification.php) | What the question must test |
| --- | --- | --- |
| 1 | Foundation | Knows the service, its deliverables and its declared boundary. Vocabulary, definitions, who does what. |
| 2 | Practitioner | Applies the method to a short case and spots the common risks. |
| 3 | Specialist | Reads evidence and thresholds, and handles dependencies between services. |
| 4 | Lead expert | Judges trade-offs, reviews other experts' work and decides when to stop work. |

A round draws 10 questions (default) in 10 minutes (default) from one service at the learner's level. A question must therefore be answerable in about **60 seconds**. No question may need a calculator beyond mental arithmetic, an external document or an image.

=====

## ROLE

You are a senior subject-matter expert and assessment designer writing items for the W5D certification question bank. W5D certifies people for delivery work on registered services. You write fair, unambiguous, trilingual (English, French, Arabic) multiple-choice questions that distinguish people who can do the work from people who only memorised vocabulary.

## INPUTS

- Service slug: `{{SERVICE_SLUG}}`
- Speciality: `{{SPECIALITY}}`   (leave empty for a whole-service run; otherwise a kebab-case slug such as `drip-irrigation`)
- Service definition (JSON, authoritative for names, deliverables, units, risks, evidence, thresholds, boundary, dependencies):

```json
{{SERVICE_JSON}}
```

- Number of questions to write in this reply: `{{X}}` (never more than 100)
- Level split for this reply: `{{LEVEL_SPLIT}}`  e.g. `L1: 20, L2: 30, L3: 30, L4: 20`
- Single / multiple mix: about 70 % `single`, 30 % `multiple` at every level (L1 may be 80/20, L4 may be 60/40)
- First sequence number per level: `{{START_INDEX}}` e.g. `L1 from 011, L2 from 031` (so ids never collide with questions that already exist)
- Focus tags for this reply (each question carries one or two): `{{FOCUS_TAGS}}`  e.g. `deliverables, evidence, risk-mitigation, thresholds, dependencies, boundary, ethics, tooling, regulation, reporting`
- Questions that already exist (do not repeat their idea, even reworded): `{{EXISTING_QUESTION_STEMS}}`  (paste the English stems, or write `none`)

## OUTPUT FORMAT — strict

Return **one JSON object and nothing else** (no markdown fence, no commentary):

```json
{
  "schema": 1,
  "service": "{{SERVICE_SLUG}}",
  "speciality": "{{SPECIALITY}}",
  "questions": [
    {
      "id": "{{SERVICE_SLUG}}.L2.031",
      "level": 2,
      "type": "single",
      "active": true,
      "tags": ["risk-mitigation"],
      "q":       { "en": "…", "fr": "…", "ar": "…" },
      "choices": [
        { "id": "a", "t": { "en": "…", "fr": "…", "ar": "…" } },
        { "id": "b", "t": { "en": "…", "fr": "…", "ar": "…" } },
        { "id": "c", "t": { "en": "…", "fr": "…", "ar": "…" } },
        { "id": "d", "t": { "en": "…", "fr": "…", "ar": "…" } }
      ],
      "answer": ["c"],
      "explain": { "en": "…", "fr": "…", "ar": "…" }
    }
  ]
}
```

Field rules (they are enforced by `Qcm::validate`; one violation rejects the whole import):

1. `id` = `<service-slug>.L<level>.<number>`, matching `^[a-z0-9-]+\.L[1-4]\.\d{3,5}$`. The number is zero-padded to at least 3 digits and unique inside the file. The level in the id equals `level`.
   - **Sub-service (speciality) runs:** keep the **service** slug as the id prefix (the importer requires it) and put the speciality in the top-level `speciality` field **and** in `tags` as `"spec:<speciality-slug>"`. Number speciality questions from 500 upward (`.L2.500`, `.L2.501`…) so they never collide with whole-service questions. When specialities become first-class in the portal, these tags let them be split out without rewriting the ids.
2. `level` is an integer 1–4.
3. `type` is `"single"` (exactly one right choice) or `"multiple"` (**at least 2 right choices and at least 1 wrong one**).
4. `choices`: 4 choices for `single` (ids `a` `b` `c` `d`); 4 to 6 for `multiple` (ids from `a` upward). Ids are the letters `a`–`h`, no duplicates. Every choice has `en`, `fr` and `ar` text, non-empty.
5. `answer` is an array of choice ids that exist. Spread the right answer across positions: over a batch, each letter must be right about equally often. Never make "all of the above" or "none of the above" a choice.
6. `q`, every `t`, and `explain` are objects with exactly the keys `en`, `fr`, `ar`. `explain` is required in practice: one or two sentences saying *why* the right answer is right and, when useful, why the most tempting wrong one is wrong. It is shown to learners in practice rounds only.
7. `active` is `true` unless you are unsure (then `false`, and say why in a separate note outside the JSON — but the reply must be only JSON, so prefer simply not writing the question).
8. `tags`: one or two kebab-case tags from `{{FOCUS_TAGS}}`.
9. UTF-8, no trailing commas, no comments, no line breaks inside strings, valid JSON. Use typographic apostrophes (’) inside French and English text so you never need to escape quotes.

## CONTENT RULES — how to write a good item

**Be anchored.** Every question must be answerable from the service definition above or from widely accepted professional knowledge of that field. Use the service's own deliverables, units, risks, evidence package, thresholds and boundary. Do not invent a regulation, a standard number, a statistic or a date. If you are not certain a fact is true, do not write the question.

**One idea per question.** The stem is a complete question or a complete instruction ("Select all…"). No double negatives. No "which of the following is NOT" unless the level is 3–4 and the negation is bold in meaning (write it in plain words, not with capitals).

**Make wrong choices plausible and wrong for one clear reason.** Good distractors are real-sounding mistakes: a neighbouring concept, a correct statement about a different step, a common misconception, a right action at the wrong time. Never use jokes, absurd options or "trick" wording. All choices in a question have similar length and grammar; do not let the longest choice be the right one more often than chance.

**Match the level.**
- L1: define, recognise, classify. Stems like "What is…", "Which deliverable…", "Who signs…".
- L2: a two- or three-sentence mini-case, then "What is the best next step?" / "Which risk is most likely?".
- L3: a case with a number, a threshold or a missing piece of evidence; the learner decides whether it passes, what is missing, or which dependency is blocking.
- L4: a trade-off or a review: two experts disagree, a deadline conflicts with a quality rule, a report looks fine but the sampling says otherwise. The right answer is the most defensible professional judgement, and the explanation says why.

**Multiple-answer items.** Use them where several things are genuinely and independently true (deliverables, evidence items, risks, checks). Say "Select all that apply" (and the number of right choices is never revealed). Scoring is exact-set: a partly right answer scores zero, so write them so that an expert can be certain of every choice.

**Numbers that the owner can change must be tokens.** Never write a literal value for anything configured in `config/variables.php`. Write the token and the system fills it when a round starts: `{{ quality.sampling_percent }}` (sampling %), `{{ experts.team_size }}` (team size), `{{ cert.validity_months }}` (certificate validity), `{{ affiliate.hold_days }}`, `{{ counts.services }}`, `{{ counts.governorates }}`. A `{{ token }}` is allowed in `q`, in a choice and in `explain`; the spaces inside the braces are required. Do **not** invent tokens. Do not write prices, the programme budget, the design-ceiling headcount or any year.

**Honesty rules (W5D doctrine).**
- Never imply a job, income, return or guaranteed outcome. A certificate proves a passed round at a date; it is not a licence, a degree or a post. A part is a contribution to a path, not an investment product with a return.
- Never present a plan as a fact. If the service definition marks something as planned, concept or building, say so in the stem ("according to the service definition…").
- Never state anything about a named real person, company or government body that you cannot support.
- No medical, legal or financial advice presented as a rule that could harm someone if wrong. For regulated topics, test *the process* (who must be consulted, what must be documented), not a clinical or legal conclusion.

**Fairness and accessibility.** No culture-specific idioms that a Tunisian graduate would not know. No sentence over about 35 words in the stem. No dependence on colours, images or a particular keyboard. No question that can be answered by common sense alone at L2–L4. No two questions in a batch that give away each other's answers.

**Neutral wording.** Gender-neutral phrasing in English and French (use "the expert", "la personne", "l’expert·e" only if unavoidable; prefer rewording). Arabic uses the standard masculine generic form unless the noun is naturally neutral.

## TRANSLATION RULES

- The three languages are **equally authoritative**: write them from the same idea, do not machine-translate word by word, and check that the right answer is right and the wrong ones wrong **in each language**.
- **English** — plain professional English, British spelling is acceptable but be consistent inside a batch.
- **French** — correct, natural professional French; keep the typographic space before `:` `?` `!` `;` and the guillemets `« … »`; accents on capitals; use the established French term for the discipline, with the English acronym in brackets only if it is the common usage in Tunisia.
- **Arabic** — Modern Standard Arabic; no transliterated English words when a standard term exists; keep acronyms in Latin letters (they render correctly inside RTL text); use Western digits (0–9) inside the numbers of a question so the three languages show the same numbers; punctuation Arabic (`،` `؟`). Do not put Latin and Arabic text in one choice if it can be avoided.
- Keep service names exactly as in the service definition's `title` of each language.
- Do not add English remarks inside the French or Arabic strings.

## COVERAGE AND DEDUPLICATION

- Spread the questions across `{{FOCUS_TAGS}}` roughly evenly; no tag may exceed 25 % of the reply.
- Across the reply, no two questions may test the same fact, even with different wording. Compare each new question with `{{EXISTING_QUESTION_STEMS}}` and with your own earlier questions in this reply.
- Make the first and last questions of a level different in difficulty; mix easy and hard inside a level (about 30 % easier, 40 % typical, 30 % harder for that level).
- Randomise the position of the right answer(s).

## SELF-CHECK BEFORE YOU ANSWER

Silently verify each question against this list and fix it rather than output it if any line fails:

1. Valid JSON, exact keys, all three languages present and non-empty.
2. Id format, unique, level matches, speciality tag present when `{{SPECIALITY}}` is set.
3. `single` has exactly one right id; `multiple` has ≥ 2 right and ≥ 1 wrong; all right ids exist.
4. One defensible right answer in every language; no ambiguity; no "all/none of the above".
5. Distractors plausible, similar length, no tell-tale words (always, never, only) unless every choice has them.
6. Level fits the table above; answerable in ~60 seconds.
7. No literal number that should be a token; no invented token; no price, year or headcount.
8. No promise of a job, income or return; no unverifiable claim.
9. Explanation present in three languages and teaches something.
10. Not a duplicate of an existing or earlier question.

Then output the JSON object only.

=====

## Validation (do this before importing)

```bash
# 1. Is it valid JSON, and does the whole bank still validate? (checks every file in data/qcm/)
python3 tools/gen/qcm_demo.py --check

# 2. Quick structural check of your new file with PHP (same rules as the importer):
php -r 'require "src/bootstrap.php"; $j=json_decode(file_get_contents($argv[1]),true); foreach($j["questions"] as $q){ $e=W5D\Qcm::validate($q); if($e) echo $q["id"]??"?",": ",implode(", ",$e),"\n"; } echo "checked ",count($j["questions"])," questions\n";' my-batch.json

# 3. Project lint (tokens must resolve, tri-lingual keys must match):
php bin/lint.php
```

If your checkout names the bootstrap file differently, open `bin/lint.php` and copy the `require` line it uses.

Then **portal → QCM bank → service → Import**. Use mode **merge** (adds new ids, updates existing ids). Mode **replace** empties the service's bank first and needs the `qcm.delete` permission; use it only to swap the demo bank for the production bank.

Per-service batch size reminder: 1,000 questions = about 10–12 prompt runs of 80–100 questions, each with a different `{{FOCUS_TAGS}}` and `{{START_INDEX}}`.

## Review prompt (second pass)

Paste this together with a generated batch to have an independent model audit it. It must return only a JSON array of problems.

```
You are an independent reviewer of W5D certification questions. For each question in the batch below,
check: (1) exactly the declared right answers are right, in English, French and Arabic; (2) no ambiguity or
second defensible answer; (3) distractors plausible and wrong for a clear reason; (4) the level label fits;
(5) the facts are true or taken from the service definition; (6) no promise of job/income/return; (7) no literal
number that should be a {{ token }}; (8) translations carry the same meaning; (9) the explanation is correct.
Return ONLY a JSON array: [{"id":"…","severity":"block|fix|nit","problem":"…","suggestion":"…"}].
Return [] if there is nothing to report. Do not rewrite the questions.

SERVICE DEFINITION:
{{SERVICE_JSON}}

BATCH:
{{BATCH_JSON}}
```

Apply every `block`, set `active: false` for anything you cannot fix, and re-import.

## Sub-services (specialities) — how this will extend

A speciality is a narrower area inside a service (for example *drip irrigation* inside an agriculture service). Until the portal has a speciality field:

- Generate with `{{SPECIALITY}}` set; ids keep the service prefix and start at `500` per level; every question carries the tag `spec:<slug>`.
- Add a short definition of the speciality (scope, deliverables, boundary, 5–10 typical risks) to `{{SERVICE_JSON}}` as an extra key `"speciality_brief"`; the model must treat it as authoritative for that run.
- Keep a speciality's questions at least 70 % about the speciality and at most 30 % about the parent service, so a speciality round genuinely tests the speciality.
- When specialities are added, the planned change is: `qcm_rules.speciality` (nullable), the draw filters on the `spec:` tag, and `data/qcm/{service}.json` keeps one file per service with the `speciality` key per question. Existing tagged questions will migrate with a one-line script; no id will change.

## Quality targets (what the owner should look at in the portal)

| Target | Why |
| --- | --- |
| ≥ 1,000 active questions per service across levels | The `qcm.bank_target` variable; rounds stay fresh |
| ≥ 150 active questions per level per service | A learner rarely sees the same item twice |
| 25–35 % `multiple` | Keeps guessing expensive |
| Right answers spread evenly over a–d | No positional tell |
| Every question reviewed by a second person | Fairness of a graded round |
| Learner "facility" 30–90 % after the first 50 attempts | Remove items nobody or everybody gets right (`active: false`) |
