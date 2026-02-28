# Changelog

## [1.6.0](https://github.com/kieranbrown/laraworker/compare/v1.5.4...v1.6.0) (2026-02-28)


### Features

* add continuous stress test for memory limits and OPcache monitoring ([6a581a3](https://github.com/kieranbrown/laraworker/commit/6a581a3b31f48e695b4f12847e76cf4fba6d57e1))
* add smoke test script for deployed Cloudflare Workers ([412bb10](https://github.com/kieranbrown/laraworker/commit/412bb10f6e09d573fce192c3aeeec76254599a92))


### Bug Fixes

* Cannot read properties of undefined ([9816fcd](https://github.com/kieranbrown/laraworker/commit/9816fcd58d8a14dfe04c1a64428b300d8859028a))
* disable ALLOW_TABLE_GROWTH and module recycling to prevent OOM on cold starts ([286e5b1](https://github.com/kieranbrown/laraworker/commit/286e5b189f77e60675a5e488f83446b4cd715982))
* guard PhpCgiBase.refresh() to prevent unnecessary OPcache destruction ([84b91fe](https://github.com/kieranbrown/laraworker/commit/84b91fe22c0fe28cb97a1feb61e6ddde1cf9edf5))
* increase WASM function table from 4908 to 8192 entries ([eb2d905](https://github.com/kieranbrown/laraworker/commit/eb2d9054578594667b8d7e90ff66afa9292f2270))
* merge epic e-ec3148 — OPcache 0% hit rate + Error 1102 on demo site ([46b119a](https://github.com/kieranbrown/laraworker/commit/46b119a5b566220b9565536150807dc2ebd23db7))
* reduce OPcache memory from 24MB to 16MB and interned strings from 4MB to 2MB ([17700e1](https://github.com/kieranbrown/laraworker/commit/17700e1d7d5d5b6d50da4a3251ea92d14c109640))
* vendorUncompressed and appUncompressed undefined in build-app.mjs ([d543016](https://github.com/kieranbrown/laraworker/commit/d5430166593a6c6561a7a1bf137fac20309a83ff))


### Performance Improvements

* **php-bridge:** static skip-headers Set and header memoization ([fab8310](https://github.com/kieranbrown/laraworker/commit/fab8310e7a61901f2dc320d9ad674d6596d645ef))
* reduce gzip compression from level 9 to 6 ([9d69088](https://github.com/kieranbrown/laraworker/commit/9d6908802ab3c6fb2ebcd324646d12c4be43a5ec))
* single-pass HTML entity decode in inertia-ssr.ts ([93f4f65](https://github.com/kieranbrown/laraworker/commit/93f4f657e422603ca6f972a7dadace8ef81bb3b0))
* single-pass TAR stats instead of triple creation in build pipeline ([71ff585](https://github.com/kieranbrown/laraworker/commit/71ff585a3464d791acd0d48bbb9e9386776524de))


### Miscellaneous Chores

* merge epic e-7205b8 — Performance & Memory Optimizations ([2e7c51a](https://github.com/kieranbrown/laraworker/commit/2e7c51a2e3222d37c20515f5c1a4aa1a0a8f3865))
* update reality index ([52a1ba0](https://github.com/kieranbrown/laraworker/commit/52a1ba07dcc995b45fa1a9d00df7ef6bca974700))


### Documentation

* add epic plan for memory regression fix (e-ec3148) ([3c2ac52](https://github.com/kieranbrown/laraworker/commit/3c2ac528caac4fbe32b6d15949b1acc8eaabc673))
* add epic plan for Performance & Memory Optimizations (e-7205b8) ([500727d](https://github.com/kieranbrown/laraworker/commit/500727de9e5cb65a2b9eb89aff52b42e1c295119))
* update performance plan with completed tasks [#4](https://github.com/kieranbrown/laraworker/issues/4) and [#7](https://github.com/kieranbrown/laraworker/issues/7) ([4cd971f](https://github.com/kieranbrown/laraworker/commit/4cd971f69e4d6540f773d234aa7fe5604b8d20fc))

## [1.5.4](https://github.com/kieranbrown/laraworker/compare/v1.5.3...v1.5.4) (2026-02-27)


### Bug Fixes

* pass ALLOW_TABLE_GROWTH via EXTRA_FLAGS instead of unused LDFLAGS ([41dc791](https://github.com/kieranbrown/laraworker/commit/41dc791411e4e7e8be3e862e550ae72bc19ec695))
* prevent WASM table index out of bounds crash on warm isolates ([4f9d11f](https://github.com/kieranbrown/laraworker/commit/4f9d11f719032c9aa1c1f89bd9bac8c675bca0ce))
* rebuild WASM binary and update helper modules from upstream sm-8.5 ([bbe63d9](https://github.com/kieranbrown/laraworker/commit/bbe63d92033061a6890ea8c0ca820a4a5a4854b0))

## [1.5.3](https://github.com/kieranbrown/laraworker/compare/v1.5.2...v1.5.3) (2026-02-27)


### Bug Fixes

* remove runtime instrumentation causing Worker resource limit errors ([28545ba](https://github.com/kieranbrown/laraworker/commit/28545ba30a6ce67492975feea5877081f04bf1e7))

## [1.5.2](https://github.com/kieranbrown/laraworker/compare/v1.5.1...v1.5.2) (2026-02-27)


### Bug Fixes

* move extensions config directly below its comment block ([ab3c802](https://github.com/kieranbrown/laraworker/commit/ab3c80224e01fa0c671754bfbbac85c057752e23))
* remove profiling instrumentation from php-stubs to fix WASM table crash ([33bc88e](https://github.com/kieranbrown/laraworker/commit/33bc88e1ea67c529eef75e769229acf37fb5a704))

## [1.5.1](https://github.com/kieranbrown/laraworker/compare/v1.5.0...v1.5.1) (2026-02-27)


### Bug Fixes

* forward incoming Cookie header to PHP and remove hardcoded env_overrides fallback ([7b3b064](https://github.com/kieranbrown/laraworker/commit/7b3b06468221f343c5fcafc5ed4ca65257cf1922))


### Continuous Integration

* only run tests on pull requests ([f6f3ab3](https://github.com/kieranbrown/laraworker/commit/f6f3ab3ca3cdca697c776e9f0fb12717fd7a1165))

## [1.5.0](https://github.com/kieranbrown/laraworker/compare/v1.4.0...v1.5.0) (2026-02-27)


### Features

* build optimisations ([#17](https://github.com/kieranbrown/laraworker/issues/17)) ([bc0eee9](https://github.com/kieranbrown/laraworker/commit/bc0eee98934c871d0fe0e95a6cc9352f8a282037))
* **demo:** update OPcache stats live when clicking Try It ([#15](https://github.com/kieranbrown/laraworker/issues/15)) ([5966058](https://github.com/kieranbrown/laraworker/commit/59660581388997e72e035f27eec73a0d017f6c73))

## [1.4.0](https://github.com/kieranbrown/laraworker/compare/v1.3.3...v1.4.0) (2026-02-27)


### Features

* restore demo showcase site in demo/ folder with deploy CI ([54c9e36](https://github.com/kieranbrown/laraworker/commit/54c9e36f866cc622eb58585bc3886d8b46cfcc2b))


### Bug Fixes

* stop excluding blade-icon SVG files from production tar ([0a5d280](https://github.com/kieranbrown/laraworker/commit/0a5d28021e6482eedf68b1f4af1a05b6d3feead2))

## [1.3.3](https://github.com/kieranbrown/laraworker/compare/v1.3.2...v1.3.3) (2026-02-27)


### Bug Fixes

* **test:** improve test isolation for Build/Deploy/Install tests ([d639ab2](https://github.com/kieranbrown/laraworker/commit/d639ab24f326810f7a6c93c69058f6baffbc613e))


### Miscellaneous Chores

* remove playground folder ([c37dc3b](https://github.com/kieranbrown/laraworker/commit/c37dc3bcb27552ca57dca3d3793de1d156379343))
* update reality index ([3c7a035](https://github.com/kieranbrown/laraworker/commit/3c7a03518ae4161e256edae52f4e20ff1faf9b7d))


### Code Refactoring

* **ci:** split CI into separate workflow files ([89e60d8](https://github.com/kieranbrown/laraworker/commit/89e60d85503b5d114c40400bee0281a8369d2761))

## [1.3.2](https://github.com/kieranbrown/laraworker/compare/v1.3.1...v1.3.2) (2026-02-27)


### Bug Fixes

* **ci:** re-trigger release workflow after auto-merging release PR ([61e0847](https://github.com/kieranbrown/laraworker/commit/61e0847bcbf3d9413f039cbe032c1548fa175042))

## [1.3.1](https://github.com/kieranbrown/laraworker/compare/v1.3.0...v1.3.1) (2026-02-27)


### Bug Fixes

* add missing playground resource files required for CI build ([bb7079e](https://github.com/kieranbrown/laraworker/commit/bb7079ecab1285277a995be1f9303dbbb15d42db))
* **ci:** change composer install to update for local package path resolution ([199319a](https://github.com/kieranbrown/laraworker/commit/199319a57565bf291f393902683c1e8b38937f89))
* **ci:** extract PR number from JSON in auto-merge command ([7941706](https://github.com/kieranbrown/laraworker/commit/7941706e269d8cfd395f88903dd4b058e695943d))
* escape dot in laraworker exclude regex ([367fa1f](https://github.com/kieranbrown/laraworker/commit/367fa1fe2c406564c0480721fa259224c58a44e0))


### Miscellaneous Chores

* merge epic e-422e63 WASM memory efficiency improvements ([bd4ade2](https://github.com/kieranbrown/laraworker/commit/bd4ade226ac568851655e6050290a9f2a6378904))
* remove --auto from pr merge ([829f45e](https://github.com/kieranbrown/laraworker/commit/829f45e17e6721fd4ef2dc6b01b2102f94d6822c))


### Documentation

* add CI pipeline verification results to epic plan ([1b878fb](https://github.com/kieranbrown/laraworker/commit/1b878fb413c5dafd2745ef184749d9e21f6ba981))


### Continuous Integration

* consolidate workflows into single CI pipeline ([e75bc3a](https://github.com/kieranbrown/laraworker/commit/e75bc3aef5c1143304f9c1878a489c1435308edf))
* remove SSR build step from Deploy Demo ([69c05c9](https://github.com/kieranbrown/laraworker/commit/69c05c9b4affc2ca088eae523bc15c9c770fe83e))

## [1.3.0](https://github.com/kieranbrown/laraworker/compare/v1.2.1...v1.3.0) (2026-02-27)


### Features

* add MEMFS budget configuration to laraworker config ([4a05be3](https://github.com/kieranbrown/laraworker/commit/4a05be3418273d68932cd2a79ac86c80a18d1afe))
* add uncompressed MEMFS size to build report with budget warning ([47e0e3f](https://github.com/kieranbrown/laraworker/commit/47e0e3f3545b3a8ba0c735a266d1a9ac6cf83dfe))


### Bug Fixes

* add file_exists guard before reading build-config.json ([c307505](https://github.com/kieranbrown/laraworker/commit/c3075055fa0b323563b78259d130537513160811))
* exclude laraworker package internals from app tar ([8c1f417](https://github.com/kieranbrown/laraworker/commit/8c1f417d76898ef628b5ca2a611f705b390c1be2))


### Documentation

* mark Task 5 as complete in epic plan ([bd8c4c5](https://github.com/kieranbrown/laraworker/commit/bd8c4c5c849380a58efaadf13d0cfbdbd78fb5f8))

## [1.2.1](https://github.com/kieranbrown/laraworker/compare/v1.2.0...v1.2.1) (2026-02-27)


### Bug Fixes

* correct stackSave WASM export mapping after rebuild ([f7ad710](https://github.com/kieranbrown/laraworker/commit/f7ad710026df279a74179b588dce702023647995))


### Build System

* increase WASM binary budget from 3MB to 4MB ([56199f0](https://github.com/kieranbrown/laraworker/commit/56199f0df351037357239164a0a8e32cd88e57a6))

## [1.2.0](https://github.com/kieranbrown/laraworker/compare/v1.1.2...v1.2.0) (2026-02-26)


### Features

* compile pdo-cfd1 extension into PHP WASM binary ([9464a4d](https://github.com/kieranbrown/laraworker/commit/9464a4d3258446f28fbeb55b1c17a84ff9905e54))

## [1.1.2](https://github.com/kieranbrown/laraworker/compare/v1.1.1...v1.1.2) (2026-02-26)


### Bug Fixes

* include exception renderer dist files in build ([cf37bb1](https://github.com/kieranbrown/laraworker/commit/cf37bb1b1828d14a306cc5dc137101bc5d6c59fa))


### Miscellaneous Chores

* reformat php-wasm-build modules ([2429995](https://github.com/kieranbrown/laraworker/commit/24299957a9ce8430548ccc4613447e9184554c26))


### Code Refactoring

* simplify playground to default Laravel with D1 database support ([e9a2e84](https://github.com/kieranbrown/laraworker/commit/e9a2e8437105294c2437b62d330b0f3af30d1702))

## [1.1.1](https://github.com/kieranbrown/laraworker/compare/v1.1.0...v1.1.1) (2026-02-26)


### Bug Fixes

* use fixed WASM memory instead of growable to prevent Worker exceeded resource limits ([1820772](https://github.com/kieranbrown/laraworker/commit/1820772fb92e4d8a6f4d7e28f5baf9d30d32476a))

## [1.1.0](https://github.com/kieranbrown/laraworker/compare/v1.0.3...v1.1.0) (2026-02-26)


### Features

* add migrations functionality for d1 ([05fd749](https://github.com/kieranbrown/laraworker/commit/05fd7490238efa3b46e8aecfeae2471aa0bdf39e))


### Bug Fixes

* ignore nested vendor dirs ([cc7a669](https://github.com/kieranbrown/laraworker/commit/cc7a669971c26a4e902a1f3f1e6da0beae614c34))
* rewrite namespace-scoped token_get_all to handle T_INLINE_HTML for runtime Blade compilation ([1739959](https://github.com/kieranbrown/laraworker/commit/173995959bfd4342b77b59bfcdd26d21122fd01c))
* update nested vendor exclude pattern to match any depth ([b497603](https://github.com/kieranbrown/laraworker/commit/b4976035f3d3f3406c30278f7ddbbdd299f077e5))

## [1.0.3](https://github.com/kieranbrown/laraworker/compare/v1.0.2...v1.0.3) (2026-02-26)


### Bug Fixes

* install failing due to missing php-wasm-build ([a28bef4](https://github.com/kieranbrown/laraworker/commit/a28bef4044f0b0f42cf63db2a0d557c006b16240))

## [1.0.2](https://github.com/kieranbrown/laraworker/compare/v1.0.1...v1.0.2) (2026-02-26)


### Bug Fixes

* installs failing due to missing stubs folder ([5b00b38](https://github.com/kieranbrown/laraworker/commit/5b00b38f07b0411b7bfd8ceaf94c4a5a0dd89cb8))

## [1.0.1](https://github.com/kieranbrown/laraworker/compare/v1.0.0...v1.0.1) (2026-02-26)


### Miscellaneous Chores

* added support for laravel 11 ([287208b](https://github.com/kieranbrown/laraworker/commit/287208b235f27babb65a1a63aee2daa32670ba0c))

## 1.0.0 (2026-02-26)


### Continuous Integration

* added pre-commit workflows ([a9e99e7](https://github.com/kieranbrown/laraworker/commit/a9e99e7effd7553c2d72a0a6eca55973202c7d11))
