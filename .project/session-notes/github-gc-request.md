# GitHub Support Request — Garbage-collect unreferenced commits

**Where to send:** https://support.github.com/contact  (category: Repositories → "Remove sensitive/unreferenced data")

**Repository:** koushikch7/magento2

---

## Suggested message

> Subject: Request garbage collection to purge unreferenced (dangling) commits
>
> Hello,
>
> On my repository `koushikch7/magento2` there are several **dangling commits**
> that are no longer referenced by any branch, tag, or ref, but are still
> accessible directly by their commit SHA (e.g. via `/commit/<sha>` URLs and
> `git fetch <sha>`).
>
> These were internal notes I have since removed from all branches. No branch,
> tag, or pull request references them any longer. Could you please run garbage
> collection on the repository to purge these unreachable objects?
>
> Example SHAs (all unreferenced):
> - 75da1796da03e332ddc775d1ff34295ab66be334
> - 01562bd337c7b91cd4332d6bfdead915a92204fc
> - b993afbb23de7fe948e964aa0594a2a67a466d06
> - cbb7051e09140518ae00affa4d8a1d9a0851d989
> - c13a0609457f86980972a5cb44acdc28b190fd9e
> - 834278bde90327a12c10e7327a792c898c779374
> - 1ee71ce7f140bad3583429c97bc448985b51b78a
> - 967ba039c9f557f988957b3f68c7747ed2e4a566
>
> Thank you.

---

## Notes
- All 8 commits touch only `chk-doc/` (internal session notes) — verified no secrets/credentials.
- None are part of any pull request to magento/magento2 (PR branches were always clean).
- Prerequisite already satisfied: no ref points to them (deleted the only holding branch + local gc done 2026-07).
