<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
<!--- END HEADER -->

## [0.5.1](https://github.com/kachnitel/FrdAdminBundle/compare/v0.5.0...v0.5.1) (2026-02-07)

### Features

* Enhance release script and documentation for Flex recipe integration, update configuration examples, and fix routing configuration ([60e40f](https://github.com/kachnitel/FrdAdminBundle/commit/60e40f8eaada42c0a2385918a11a38a7cebcbc82))


---

## [0.5.0](https://github.com/kachnitel/FrdAdminBundle/compare/v0.4.2...v0.5.0) (2026-02-06)

### Features

* Add per-column permissions; implement ColumnPermission attribute and related service ([0a8994](https://github.com/kachnitel/FrdAdminBundle/commit/0a89948588ef0e9ecba297e5ae612d02db037a46))
* Implement column visibility preferences and storage; add related services and UI components ([f94cba](https://github.com/kachnitel/FrdAdminBundle/commit/f94cba9028b1080e5359253a87fc930f01bd0042))

##### Tests

* Add functional tests for column permission enforcement and visibility features ([2cca7b](https://github.com/kachnitel/FrdAdminBundle/commit/2cca7b1075ccf67bc669dcbb7a589c873293b11b))

### Bug Fixes

* Add key to ColumnFilters to ensure re-render on column select ([d3e334](https://github.com/kachnitel/FrdAdminBundle/commit/d3e3349b92465963bb3ed96978cadfa66d95ceea))
* Update doctrine/orm and twig/twig dependencies; enhance test mocks for ClassMetadata ([de3941](https://github.com/kachnitel/FrdAdminBundle/commit/de394195d5cd5d66f2961989aa0304c3094cba39))

##### Tests

* Improve output buffering handling in bootstrap for test synchronization to fix lowest dependency test pass ([a296e7](https://github.com/kachnitel/FrdAdminBundle/commit/a296e77905acd0185e073ec01083d0e92f4900ce))

### Code Refactoring

* Create EntityListColumnService for column permission filtering and add unit tests ([52eb19](https://github.com/kachnitel/FrdAdminBundle/commit/52eb19a68e4b289ab80c6cc8e76b17203a66ed09))


---

## [0.4.2](https://github.com/kachnitel/FrdAdminBundle/compare/v0.4.1...v0.4.2) (2026-02-03)

### Features

* Add date_immutable field type preview ([203f87](https://github.com/kachnitel/FrdAdminBundle/commit/203f87930dd8ba7d685a2f9ba2f2ead4a8434921))
* Support all types listed in Doctrine\DBAL\Types\Types ([e52375](https://github.com/kachnitel/FrdAdminBundle/commit/e523750ce603a2c44007a784b61cd196f82b7907))


---

## [0.4.1](https://github.com/kachnitel/FrdAdminBundle/compare/v0.4.0...v0.4.1) (2026-01-30)

### Features

* Enhance admin routing with automatic collection and entity URL generation ([114d4c](https://github.com/kachnitel/FrdAdminBundle/commit/114d4c1aaf5c7f1f66eabcb121ba06508a51164d))

### Documentation

* Add custom row actions template example ([dc83d6](https://github.com/kachnitel/FrdAdminBundle/commit/dc83d63141f5b9deb0141267ceb81b2f4790f5d6))


---

## [0.4.0](https://github.com/kachnitel/FrdAdminBundle/compare/v0.3.1...v0.4.0) (2026-01-28)

### Features

* Add 'multiple' option to Enum column filter; add admin:debug:filters console command ([88efa2](https://github.com/kachnitel/FrdAdminBundle/commit/88efa2dc914d82f760ad49e3de5da33d655157df))
* Allow filtering by collection relationships (disabled by default) ([16c8cf](https://github.com/kachnitel/FrdAdminBundle/commit/16c8cf6babcd08d1c206226fadaa037fb65bec57))
* Auto-detect relation search fields based on display priority ([fa0631](https://github.com/kachnitel/FrdAdminBundle/commit/fa0631485b0b9c4269d45ad1a0b428bf1d21dfc4))
* Implement 'filterableColumns' attribute option ([dc6211](https://github.com/kachnitel/FrdAdminBundle/commit/dc6211ce174a171d111514053c468a892aaa9536))
* Test and document template overrides for dataSource ([f43536](https://github.com/kachnitel/FrdAdminBundle/commit/f4353671f06151c804f4667348635a7eb916b649)) *[*[*@see*](https://github.com/see)*]*
* Use component to render multi-select filter ([e005a7](https://github.com/kachnitel/FrdAdminBundle/commit/e005a706d1675ec7be51e070ed7f35e76df0702d))


---

## [0.3.1](https://github.com/kachnitel/FrdAdminBundle/compare/v0.3.0...v0.3.1) (2026-01-26)

### Features

* Add 'newButtonLabel' block ([567870](https://github.com/kachnitel/FrdAdminBundle/commit/56787092db7ba73661e5feb24c48dc3c0d9f8986))
* Use twig's HTML syntax for Admin components; clarify extending bundle's templates in docs ([c84455](https://github.com/kachnitel/FrdAdminBundle/commit/c84455fe88cf9e0ce6657d3fab82a5d83dc371db))


---

## [0.3.0](https://github.com/kachnitel/FrdAdminBundle/compare/v0.2.0...v0.3.0) (2026-01-19)

### Features

* Date range clear btn ([b16253](https://github.com/kachnitel/FrdAdminBundle/commit/b162535847b3c96f067136a5b2d9628f001619db))
* Remove hardcoded bootstrap classes and add "presets" for bootstrap 5 and tailwind ([47080d](https://github.com/kachnitel/FrdAdminBundle/commit/47080d5139931bfd7e6184bb50feb30543c9a3dd))

### Bug Fixes

* Make clear emit filter:updated ([464ab9](https://github.com/kachnitel/FrdAdminBundle/commit/464ab9e389896e4fa9b55f6919a0f4f0a3777656))

### Tests

* Improve coverage w/ low hanging fruit ([a8fdeb](https://github.com/kachnitel/FrdAdminBundle/commit/a8fdebf6e42b4602267838c226c815eafc9771aa))
* Increase test coverage ([8992e3](https://github.com/kachnitel/FrdAdminBundle/commit/8992e37cfa7e71238f07b4e8270c7d1a51baf66d))


---

## [0.2.0](https://github.com/kachnitel/FrdAdminBundle/compare/v0.1.0...v0.2.0) (2025-12-29)

### Features

* Add batch actions with multi-select support ([c5773c](https://github.com/kachnitel/FrdAdminBundle/commit/c5773c75632addd1ef69347456b0e5f97f6e436e))
* Add DataSource abstraction for non-Doctrine data sources ([f5a299](https://github.com/kachnitel/FrdAdminBundle/commit/f5a2991a722bfb25fca940678b984365a6b1eb72))
* Add debug.datasource command ([0dbaf3](https://github.com/kachnitel/FrdAdminBundle/commit/0dbaf321d8e3264d422adc3dffb973d16ff31c17))
* Date range filter ([25db0b](https://github.com/kachnitel/FrdAdminBundle/commit/25db0b453edeee30110d4421c22ed9ea5da7704f))
* Dispatch both event and input when daterange changes ([899efc](https://github.com/kachnitel/FrdAdminBundle/commit/899efc535dcaddc3c93411487598637aed5fed4e))
* Improve column filters ([ea769b](https://github.com/kachnitel/FrdAdminBundle/commit/ea769b28a245340f8a6f5bdce3ecc74cb5fb3d18))
* Make the DateRangeFilter a LiveComponent ([30a478](https://github.com/kachnitel/FrdAdminBundle/commit/30a478e6aec2f7dcdc42570e5be5edcd67f39bdc))
* More user friendly dateRangeFilter ([f84089](https://github.com/kachnitel/FrdAdminBundle/commit/f84089939870609ae503fed080c6d7bb396e1d6c))
* Sync "marked" test templates before running tests ([8e5a28](https://github.com/kachnitel/FrdAdminBundle/commit/8e5a28f6a052d468b5612604860ad6b6384e1506))

### Bug Fixes

* "Master checkbox" in batch select behavior ([31b735](https://github.com/kachnitel/FrdAdminBundle/commit/31b7358ec83dedb5d69f10fcbc4548cd04aeabaf))
* Add missing daterange-filter controller to package.json ([296475](https://github.com/kachnitel/FrdAdminBundle/commit/2964756035ea406b092dd8c406cebb0f01738c99))
* Add missing daterange-filter in package.json ([fb40f9](https://github.com/kachnitel/FrdAdminBundle/commit/fb40f98b2c13f83904d6892a88462eadd22d83f5))
* Batch delete ([e59e1a](https://github.com/kachnitel/FrdAdminBundle/commit/e59e1acd0a100a1994aa0a65cc26f4630a518bcb))
* Correct path to js controller ([a76398](https://github.com/kachnitel/FrdAdminBundle/commit/a763988df3611044feb1c7a7fb3dac4689278a91))
* Do not try filtering by computed properties ([57d1ca](https://github.com/kachnitel/FrdAdminBundle/commit/57d1cacceaae3727198abcbfa6f6651f1451669c))
* FilterMetadataProvider to validate searchFields in ensureRelationConfig ([30fb1a](https://github.com/kachnitel/FrdAdminBundle/commit/30fb1a67eca5a5cad1755ff99579361e24fd5af7))
* Initial date range value on load ([e5fa15](https://github.com/kachnitel/FrdAdminBundle/commit/e5fa155ca762320923afda0f2950ef698a0a0919))
* No json_decode, value should be decoded in controller ([b3a968](https://github.com/kachnitel/FrdAdminBundle/commit/b3a9684ee281640964513455d6de9d5e900e688b))
* Respect template setting in data source column options ([398f43](https://github.com/kachnitel/FrdAdminBundle/commit/398f43076b106eba189409cf6d691ce46e043aae))
* Shift+Click range selection for batch checkboxes ([db7d10](https://github.com/kachnitel/FrdAdminBundle/commit/db7d100391ed4c66b7338765a18ecf5527318b3b))
* Use string for DateRangeFilter ([8018d3](https://github.com/kachnitel/FrdAdminBundle/commit/8018d3f2602e2fe3412ec35b8b66bebdfb6e8e2e))

### Code Refactoring

* Break down SyncTestTemplates command's execute method ([cf3919](https://github.com/kachnitel/FrdAdminBundle/commit/cf39192202bf93b16a0627c197fb23f852d442ef))
* Extract pagination and permission services from EntityList ([7a2cfa](https://github.com/kachnitel/FrdAdminBundle/commit/7a2cfaa2943cc97847dc9d955c8cb8c0393bf5fd))
* Use AutowireIterator for DataSource discovery ([370ac5](https://github.com/kachnitel/FrdAdminBundle/commit/370ac5bb9b219e8bae864067f422de222984ffdd))
* Use DataSource for default Doctrine implementation, Reduce complexity of EntityList controller ([791275](https://github.com/kachnitel/FrdAdminBundle/commit/791275d2e22234fe46abd9a485692c31beb5d722))
* Use some partials to lighten up EntityList ([ace7eb](https://github.com/kachnitel/FrdAdminBundle/commit/ace7eb380bf5913e2d2adea91033bc80e26b2cd5))

### Tests

* Cover date range url deconstruction ([6254a5](https://github.com/kachnitel/FrdAdminBundle/commit/6254a523d63ed0846adf48dc515be61f973eb135))
* DateRangeFilterTest ([8fda82](https://github.com/kachnitel/FrdAdminBundle/commit/8fda829d49e9d20e0c82d00c8d56f6bf6ab84333))
* Fix searchFields related tests ([14d9f7](https://github.com/kachnitel/FrdAdminBundle/commit/14d9f7782e4b08762586949a637cd50ef48c1e56), [90a7ff](https://github.com/kachnitel/FrdAdminBundle/commit/90a7ffd0c729b35c7ffb9b89c6a1af4a2a5ac08d))

### Documentation

* Add DataSource abstraction documentation ([0ef98b](https://github.com/kachnitel/FrdAdminBundle/commit/0ef98bbd4e32f8e2835a78e271ad7a66067684cf))
* Date filters ([fc8fb6](https://github.com/kachnitel/FrdAdminBundle/commit/fc8fb610616d3270cd94e947ead96aaef98d061b))
* Improve readability and fix some examples ([b26d0a](https://github.com/kachnitel/FrdAdminBundle/commit/b26d0a0bd3b0ca30fe64f4ae36e2d6f9bbe7254c))


---

## [0.1.0](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.8...v0.1.0) (2025-12-12)


---

## [0.0.8](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.7...v0.0.8) (2025-12-12)

### Bug Fixes

* Remove https:// prefix from changelog URL formats to prevent double protocol ([e39b71](https://github.com/kachnitel/FrdAdminBundle/commit/e39b7127c42c30ba1606a5b9fb0a49901973a9d0))


---

## [0.0.7](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.6...v0.0.7) (2025-12-12)

### Bug Fixes

* Remove --amend flag from release script to preserve commit history ([0824aa](https://github.com/kachnitel/FrdAdminBundle/commit/0824aae7853a4afe210b5ad12b73212abe1f5e78))

### Code Refactoring

* Use DTO to clean up EntityList constructor ([f28c88](https://github.com/kachnitel/FrdAdminBundle/commit/f28c88b09c25d45044f248ec18f88c17437c9a6e))

### Documentation

* Update changelog config, clean up, add Filters documentation ([aae6af](https://github.com/kachnitel/FrdAdminBundle/commit/aae6af9fd5de595111b561b495932269ceaac21a))


---

## [0.0.6](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.5...v0.0.6) (2025-12-10)

### Features

* Add default Date template ([aacedc](https://github.com/kachnitel/FrdAdminBundle/commit/aacedc256406926d41b2838ade2eab106b6f7e9f))


---

## [0.0.5](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.4...v0.0.5) (2025-12-06)


---

## [0.0.4](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.3...v0.0.4) (2025-12-06)


---

## [0.0.3](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.2...v0.0.3) (2025-12-06)

### Bug Fixes


##### Docs

* PHP Tag in readme ([40bcf0](https://github.com/kachnitel/FrdAdminBundle/commit/40bcf0aaaa52e0762bdd539253e33d541877dab0))


---

## [0.0.2](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.1...v0.0.2) (2025-12-05)

### Features

* Allow no-role access if not configured ([d91238](https://github.com/kachnitel/FrdAdminBundle/commit/d9123815c23b35520cf8494494e9181dd291d044))
* Implement security into the component ([b9cc63](https://github.com/kachnitel/FrdAdminBundle/commit/b9cc63bf05d29c56f7a0cfc0f4be7f247742264e))

### Bug Fixes

* Pre-commit "set -e" ([89f906](https://github.com/kachnitel/FrdAdminBundle/commit/89f906a397b6f97dbd68c6f650165a533d3dbcba))

##### Docs

* Clarify incomplete config features ([f83f11](https://github.com/kachnitel/FrdAdminBundle/commit/f83f11ba522b4c255e998d862a7ecd600a7c424a))

### Code Refactoring

* Extract EntityListQueryService from EntityList ([1092f9](https://github.com/kachnitel/FrdAdminBundle/commit/1092f90a2f32ac9fd2174650bbc1fa0b2ca09108))

### Documentation

* Clean up README ([4c45a6](https://github.com/kachnitel/FrdAdminBundle/commit/4c45a665df7c401f97001c2a41b66fa60f993fe4))


---

## [0.0.1](https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.0...v0.0.1) (2025-12-04)

### ⚠ BREAKING CHANGES

* Complete namespace migration ([84cc0c](https://github.com/kachnitel/FrdAdminBundle/commit/84cc0c64354d35a5ed04cd0b18373b0af402edb3))
* Migrate namespace from frd/ to kachnitel/ ([84cc0c](https://github.com/kachnitel/FrdAdminBundle/commit/84cc0c64354d35a5ed04cd0b18373b0af402edb3)) *[*[*@FrdAdmin*](https://github.com/FrdAdmin), [*@KachnitelAdmin*](https://github.com/KachnitelAdmin)*]*

### Features

* Add automated metrics and badges system ([4267fe](https://github.com/kachnitel/FrdAdminBundle/commit/4267fedf49934e97b1e2d22a28a79c7e2022eafa))
* Add automated release workflow with conventional-changelog ([75426f](https://github.com/kachnitel/FrdAdminBundle/commit/75426f27c796864aab7580b8e5498ba18b950ea7))


---

## [0.0.0](https://github.com/kachnitel/FrdAdminBundle/compare/70d8895d0eb1c4cfe75d27f6b5648badffccff31...v0.0.0) (2025-12-04)

### Features

* Add generic admin controller with YAML configuration ([ec1b4d](https://github.com/kachnitel/FrdAdminBundle/commit/ec1b4de0a17b86e8ea7f0aa3009f40a3806e9060))
* Add LiveComponent entity list with filters and search ([c129ca](https://github.com/kachnitel/FrdAdminBundle/commit/c129ca7a4c9c0669097f319a96bd8f9167d2cd95))
* Add per-column filtering with automatic type detection ([b78aaa](https://github.com/kachnitel/FrdAdminBundle/commit/b78aaa63d9e6f4ba5016d5bd6c88f7a50170a57d))
* Add static analysis and fix type safety issues ([722999](https://github.com/kachnitel/FrdAdminBundle/commit/722999c339b9e9c7545160d0943cad7868d13e07))
* Configurable base template, clickable entity ID ([3fae3b](https://github.com/kachnitel/FrdAdminBundle/commit/3fae3b7463a8a8c36e19e2e8518dc8fc35a1dfb7))
* Recognize per entity columns setting ([911f8f](https://github.com/kachnitel/FrdAdminBundle/commit/911f8f6d85eb345de016161adad1ac0025d6cb96))
* Register LiveComponent namespace in bundle configuration ([46dd35](https://github.com/kachnitel/FrdAdminBundle/commit/46dd35c94e885f49a848da23ccd32b699c6c972f))
* Update URL on filter/sort/page changes ([4cb118](https://github.com/kachnitel/FrdAdminBundle/commit/4cb118ebafd91093719c6b4456e39d3aaac8447f))

##### Breaking

* Configure through #[Admin] attribute ([46c629](https://github.com/kachnitel/FrdAdminBundle/commit/46c6296d4d419b1bee6cead8a19a7f2c66ca8ede))

##### Wip

* Pagination ([d99418](https://github.com/kachnitel/FrdAdminBundle/commit/d99418727163012c52caec25bd108b6a624cae39))

### Bug Fixes

* Add configure() method for AbstractBundle configuration ([e488fa](https://github.com/kachnitel/FrdAdminBundle/commit/e488fa72cbd16ad97fab58525e24a66c4cdfb6e1))
* Add default empty array for entities config ([55af50](https://github.com/kachnitel/FrdAdminBundle/commit/55af50baa4110916dcbf23509bdc660fd7c27836))
* Allow dynamic columnFilters keys in EntityList LiveComponent ([71f637](https://github.com/kachnitel/FrdAdminBundle/commit/71f6379c4433db33b288cf02c9ce6de7d4553420))
* Another go at relationships in index ([b5316b](https://github.com/kachnitel/FrdAdminBundle/commit/b5316b1d7458ca492d90195d1a48c5737ad0eac8))
* Collection column data ([ae3af1](https://github.com/kachnitel/FrdAdminBundle/commit/ae3af1cd89e3e4b4922a9e0ae258168e2519a1ed))
* Ensure enumClass is always set for enum filters ([2fa093](https://github.com/kachnitel/FrdAdminBundle/commit/2fa093c1963be342c233f347288ab6e66739af48))
* Ensure relation filter metadata is always set ([2203ab](https://github.com/kachnitel/FrdAdminBundle/commit/2203abae7f89f5dc46c5e9b6f2c46053e1bff9bd))
* Extend layout.html.twig and use correct block names (headerTitle, headerButtons) ([d71180](https://github.com/kachnitel/FrdAdminBundle/commit/d71180b10f7ba98847af273bd356c5ca156083dd))
* Handle Doctrine proxies correctly in AdminRouteRuntime ([78de97](https://github.com/kachnitel/FrdAdminBundle/commit/78de97c6ccffa4997ae32643f8bac63d1e1e6792))
* Initialize $em in AbstractAdminController ([596a73](https://github.com/kachnitel/FrdAdminBundle/commit/596a73feb91483aba476e34507e7352d46115bd7))
* Inline property rendering in show template to access loop variable ([d6b77e](https://github.com/kachnitel/FrdAdminBundle/commit/d6b77ea1d437071998f4e2aea0b95d7bddb102b4))
* Inline table row and use include for filter blocks ([528702](https://github.com/kachnitel/FrdAdminBundle/commit/5287020c8178105058e4d100dca5df86a39af26b))
* Load services and Twig paths in bundle configuration ([cb3675](https://github.com/kachnitel/FrdAdminBundle/commit/cb36756e219d7b6677aef619f4bcf287eed519c6))
* Make bundle self-contained with admin_ prefixed Twig functions ([d249bc](https://github.com/kachnitel/FrdAdminBundle/commit/d249bc012d888be22638fcc78847ef460c9b6728))
* Object to string conversion in show.html.twig ([aefdc0](https://github.com/kachnitel/FrdAdminBundle/commit/aefdc0a078bbf8df9cd467522fd9354fae72a15a))
* Pass entity, value, and property variables to type templates ([fa497a](https://github.com/kachnitel/FrdAdminBundle/commit/fa497a857d893b18f8407be4742c214cfb1891b7))
* Pass required variables to type templates in EntityList ([657ea2](https://github.com/kachnitel/FrdAdminBundle/commit/657ea2245dcca969197b81883e18b7c821520cfc))
* Pass variables to blocks for nested component compatibility ([8ecc69](https://github.com/kachnitel/FrdAdminBundle/commit/8ecc695936c0a839a06f93621470519bb197567a))
* Process configuration parameters in AbstractBundle ([431ef8](https://github.com/kachnitel/FrdAdminBundle/commit/431ef8a85fe9f8e9f96fba773d08e9595ed14a42))
* Remove manual Twig path registration to allow template overrides ([490aa2](https://github.com/kachnitel/FrdAdminBundle/commit/490aa2d691be262859cc66d4dd5e6e08a314e7a7)) *[*[*@FrdAdmin*](https://github.com/FrdAdmin)*]*
* Remove unused table_row block and add functional tests ([0c16be](https://github.com/kachnitel/FrdAdminBundle/commit/0c16be4f122eefc47f185c4ce23e58108c441a51))
* Replace 'class' with 'entitySlug' in AbstractAdminController ([9b00af](https://github.com/kachnitel/FrdAdminBundle/commit/9b00af8949ae4400bd1211d0dfeca035dd90fc27))
* Resolve duplicate filters and collection memory issues ([bbe22b](https://github.com/kachnitel/FrdAdminBundle/commit/bbe22b8806896d52f9c6f276427863c12f12bf02))
* Resolve entity routing and relation rendering issues ([86e490](https://github.com/kachnitel/FrdAdminBundle/commit/86e4905c12b282c6253fa2f2c879d4f40e8e9eaf))
* Use app's existing Twig functions instead of bundle-specific ones ([7ac1b9](https://github.com/kachnitel/FrdAdminBundle/commit/7ac1b98b8c46f65e191fd889986268bcf60ce587))
* Use attribute() function to safely render Doctrine proxy objects ([81e1a4](https://github.com/kachnitel/FrdAdminBundle/commit/81e1a4035516b3d24568b562755cbfc18eb59742))
* Use bracket notation for LiveComponent array binding ([685603](https://github.com/kachnitel/FrdAdminBundle/commit/685603466dc73171f64149fd5944fa7171f99d21))
* Use lazy loading for filter metadata to avoid hydration issue ([3893a1](https://github.com/kachnitel/FrdAdminBundle/commit/3893a1e2ee6917c6cdcc18c3c3c877298bfc9af7))
* Use this.entityClass in LiveComponent template ([748ad3](https://github.com/kachnitel/FrdAdminBundle/commit/748ad3a04ff4fe4a6785a4ed31f2b9de72426eaa))
* Variable "frd_admin_base_layout" does not exist in index_live ([271c1d](https://github.com/kachnitel/FrdAdminBundle/commit/271c1d64e76ab6230ec32da8381ef9735cde8974))
* Working basic sorting ([b0a7c4](https://github.com/kachnitel/FrdAdminBundle/commit/b0a7c4f84bb9395b6d55949c4b9921356f99972f))
* Working ColumnFilter ([cfb338](https://github.com/kachnitel/FrdAdminBundle/commit/cfb3383c58d70383fdc2a07c5f9fd31d86376bbc))

### Tests

* Add comprehensive test suite for admin bundle ([4c58e9](https://github.com/kachnitel/FrdAdminBundle/commit/4c58e9507f94fcd3ff2e89241a16a4f39c90173e))
* Ensure standard Symfony behaviour of template loading works ([c9999f](https://github.com/kachnitel/FrdAdminBundle/commit/c9999fe0c0c06c85e48a38bacc0afa58e0d24c4b))


---

