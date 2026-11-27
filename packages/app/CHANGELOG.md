# Changelog

`phillipsharring/handlr-app` is the full-stack app skeleton (the `composer create-project`
target). It co-versions in lockstep with the framework; this file records changes to the
**skeleton itself**. Full commit-level detail is in git history.

## 0.18.0

### Changed

- **Component overrides renamed for handlr-build 0.18:** every `<component file="...">`
  in the layouts and admin pages is now `<component hb-file="...">`. Apps made from an
  earlier skeleton need the same rename when they move to handlr-build 0.18; the old
  form fails the build with an error saying so.

## 0.16.0

Lockstep bump — framework pins raised to `^0.16.0`. No skeleton changes this release.

## 0.7.0 – 0.15.1

Skeleton established and stabilized as the `composer create-project` target:

- Wired `packages/app` as the `handlr-app` full-app skeleton (backend + frontend halves).
- Removed A/B testing from the core skeleton (it became the optional `handlr-module-ab`).
- Extracted the demo `examples` package out of the skeleton.
- Scaffold-freshness fixes: dev-command docs, framework/manifest pin corrections, a
  consumer-side `composer check`, and lockstep framework-pin bumps each release.

Otherwise version bumps only, co-versioned with the framework.
