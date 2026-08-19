# Changelog

## [1.1.0](https://github.com/butburg/kleinanz-laravel-12/compare/v1.0.0...v1.1.0) (2026-08-19)


### Features

* show deployed release version in footer ([6d81847](https://github.com/butburg/kleinanz-laravel-12/commit/6d8184752bc517c64a135e695c7e92d1050c906c))


### Bug Fixes

* error page 500 and some exceptions ([10e485f](https://github.com/butburg/kleinanz-laravel-12/commit/10e485f3f0ab2dc1891ddffa2987d99d5fb12f6c))
* sidebar centering ([e7a5b30](https://github.com/butburg/kleinanz-laravel-12/commit/e7a5b308b5f12937b8d235f6ea83e17b1ae465c1))

## [1.0.1](https://github.com/butburg/kleinanz-laravel-12/compare/v1.0.0...v1.0.1) (2026-08-18)


### Bug Fixes

* sidebar centering ([e7a5b30](https://github.com/butburg/kleinanz-laravel-12/commit/e7a5b308b5f12937b8d235f6ea83e17b1ae465c1))

## 1.0.0 (2026-08-18)


### Features

* add API key validation endpoint ([4aa792c](https://github.com/butburg/kleinanz-laravel-12/commit/4aa792c1ac0e18c7bba874ebc0667ab639fd9836))
* add appendices table for platform-specific user content ([6db2a09](https://github.com/butburg/kleinanz-laravel-12/commit/6db2a092aff3ca4c6baf25db49ded0e9ae1625b0))
* add import commands for legacy data migration ([7c2b035](https://github.com/butburg/kleinanz-laravel-12/commit/7c2b035bfb87aee68bb32f71b4f900d1919f2e15))
* add test mode for ad generation without API costs ([48eb8f7](https://github.com/butburg/kleinanz-laravel-12/commit/48eb8f7db0b100704d4de628839238055af35a80))
* **ads:** add async auto-crop job with metadata schema and fixture-backed tests ([1466de2](https://github.com/butburg/kleinanz-laravel-12/commit/1466de2030c21e01e8cb400ce6ce1ae8ac17f4a6))
* **ads:** add crop preference toggle, safe downloads, and dispatch integration ([c4ddd2b](https://github.com/butburg/kleinanz-laravel-12/commit/c4ddd2b0b2cf5326317edf04be18ab3c89d5139c))
* **ads:** add platform-specific appendices to generated ads ([81cd3f3](https://github.com/butburg/kleinanz-laravel-12/commit/81cd3f3d449fbae4ae009ca1f47c7887312f0c06))
* **ads:** add previous and next navigation to edit form ([0cb4bcf](https://github.com/butburg/kleinanz-laravel-12/commit/0cb4bcfcd382a9be5fc5e7105995265e9f96a467))
* **ads:** enforce structured OpenAI ad responses ([22eecbd](https://github.com/butburg/kleinanz-laravel-12/commit/22eecbdca21740f435df52804f6a2086abffb64b))
* **ads:** move create flow to expandable card on index ([e49d674](https://github.com/butburg/kleinanz-laravel-12/commit/e49d6741c74ed253c0bce0a8404b8db88b2faf57))
* **help:** add authenticated in-app help page ([f7ca837](https://github.com/butburg/kleinanz-laravel-12/commit/f7ca837a61db8f17599fe2ecaf6beff0f7c1e7d8))
* implement UUID auto-generation and lifecycle hooks in Ad model ([585222c](https://github.com/butburg/kleinanz-laravel-12/commit/585222c02440e8f8175f4dda3d5e5578e957b631))
* **legacy:** add staged legacy ads import ([f6429bc](https://github.com/butburg/kleinanz-laravel-12/commit/f6429bc09cb3cfaa8a26207c9b118957043f8a32))
* **platforms:** let users manage reusable ad appendices ([ec9ff4b](https://github.com/butburg/kleinanz-laravel-12/commit/ec9ff4b32b28b8a017c3ec73567b0206dd199be5))
* **settings:** add user-managed OpenAI API key settings ([d5661ac](https://github.com/butburg/kleinanz-laravel-12/commit/d5661ac2c0cc9e2cd79a662794d1706b7cd1a706))
* **support:** add authenticated support contact form ([bab36f5](https://github.com/butburg/kleinanz-laravel-12/commit/bab36f5e644808a3f088b19d790a7a597a391b97))
* **ui:** add application footer with Ko-fi support link ([3e85896](https://github.com/butburg/kleinanz-laravel-12/commit/3e85896de6878099255294970ef52aa10a143323))


### Bug Fixes

* agent files cleared ([482f4b0](https://github.com/butburg/kleinanz-laravel-12/commit/482f4b03d0022e1f90d5482f1029730cf6599550))
* **auth:** submit password resets to the update route ([274369f](https://github.com/butburg/kleinanz-laravel-12/commit/274369f1dd1c855194318e37ad45c15d967df531))
* correct OpenAI Responses API payload schema ([6465e96](https://github.com/butburg/kleinanz-laravel-12/commit/6465e96802c6958c71adc4ecfe72b68bad613469))
* Generate Ad button validation with conditional nullable rules ([7049b28](https://github.com/butburg/kleinanz-laravel-12/commit/7049b2859ec24bba4835f17f4b459463a8e854e7))
* **ui:** correct dialog layering and component refs ([f774c20](https://github.com/butburg/kleinanz-laravel-12/commit/f774c20fbb94adb7d0247e7c083d48f21269ccd8))
* **ui:** keep sidebar header fixed at top ([6b13cde](https://github.com/butburg/kleinanz-laravel-12/commit/6b13cdec711cae3417f15f2841ae0900804938fa))
* **ui:** refine footer signature and edit navigation spacing ([a85572c](https://github.com/butburg/kleinanz-laravel-12/commit/a85572cc31c0ea9417fe4fd25f4c57b71f30fb0a))
* **ui:** restore made with love footer signature ([2f6bbce](https://github.com/butburg/kleinanz-laravel-12/commit/2f6bbce46d03f52cf0e62bd0b0b6eeb04a3bb563))
* **ui:** use bug icon for support link ([4fa870b](https://github.com/butburg/kleinanz-laravel-12/commit/4fa870ba5d27d8470f5fec100b8ef51bcf2bc41e))
