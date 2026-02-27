# Changelog

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
