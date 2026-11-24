# Card image prompts

Every card element on the site has one image (or a small carousel). The files in `htdocs/assets/_base/images/cards/` are
indexed **placeholders** (`img-NNNN.jpg`, 640x360). To replace one, generate an image from its prompt and save it under the same
file name (1280x720 JPEG recommended). The index is stable: it never changes when other cards are added.

The prompts are split into small files by index range so that no single document is too long to open or to preview in full.
Regenerate everything (placeholders, manifest, these files) with `python3 tools/gen/card_images.py`.

Total: **362** images for **327** cards (IMG-0001 to IMG-0368).

| Range | Images | Pages covered | File |
|---|---|---|---|
| IMG-0001 to IMG-0050 | 50 | careers, contact, for-admin, for-customer, for-investor, for-leader, for-member, for-owner, for-user | [img-0001-0050.md](card-image-prompts/img-0001-0050.md) |
| IMG-0051 to IMG-0100 | 44 | for-user, home, how-it-works, investors, join, partners, team, macro-works | [img-0051-0100.md](card-image-prompts/img-0051-0100.md) |
| IMG-0101 to IMG-0150 | 50 | macro-works, projects | [img-0101-0150.md](card-image-prompts/img-0101-0150.md) |
| IMG-0151 to IMG-0200 | 50 | projects, services | [img-0151-0200.md](card-image-prompts/img-0151-0200.md) |
| IMG-0201 to IMG-0250 | 50 | services | [img-0201-0250.md](card-image-prompts/img-0201-0250.md) |
| IMG-0251 to IMG-0300 | 50 | services, quick-paths, affiliate, app-register, certification | [img-0251-0300.md](card-image-prompts/img-0251-0300.md) |
| IMG-0301 to IMG-0350 | 50 | certification, checkout-done, how-it-works, join-thanks, subscription-manage, trust-accessibility, trust-ads-policy, trust-cookies, trust-corrections, trust-data-rights, trust-editorial, trust-refunds, trust-sampling | [img-0301-0350.md](card-image-prompts/img-0301-0350.md) |
| IMG-0351 to IMG-0400 | 18 | trust-security, trust | [img-0351-0400.md](card-image-prompts/img-0351-0400.md) |

Retired indices (their cards were removed from the site, so they have no prompt): IMG-0082 to IMG-0087.
