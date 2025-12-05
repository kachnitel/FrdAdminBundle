<!--- BEGIN HEADER -->
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
<!--- END HEADER -->

## [0.0.2](https://https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.1...v0.0.2) (2025-12-05)

### Features

* Allow no-role access if not configured ([d91238](https://https://github.com/kachnitel/FrdAdminBundle/commit/d9123815c23b35520cf8494494e9181dd291d044))
* Implement security into the component ([b9cc63](https://https://github.com/kachnitel/FrdAdminBundle/commit/b9cc63bf05d29c56f7a0cfc0f4be7f247742264e))

### Bug Fixes

* Pre-commit "set -e" ([89f906](https://https://github.com/kachnitel/FrdAdminBundle/commit/89f906a397b6f97dbd68c6f650165a533d3dbcba))

##### Docs

* Clarify incomplete config features ([f83f11](https://https://github.com/kachnitel/FrdAdminBundle/commit/f83f11ba522b4c255e998d862a7ecd600a7c424a))

##### Test

* Restore exception handlers in tearDown to avoid Risky warning ([2960e9](https://https://github.com/kachnitel/FrdAdminBundle/commit/2960e9fba92ee933e2f51cac701c7035f54848a2))

### Code Refactoring

* Extract EntityListQueryService from EntityList ([1092f9](https://https://github.com/kachnitel/FrdAdminBundle/commit/1092f90a2f32ac9fd2174650bbc1fa0b2ca09108))

### Documentation

* Clean up README ([4c45a6](https://https://github.com/kachnitel/FrdAdminBundle/commit/4c45a665df7c401f97001c2a41b66fa60f993fe4))


---

## [0.0.1](https://https://github.com/kachnitel/FrdAdminBundle/compare/v0.0.0...v0.0.1) (2025-12-04)

### ⚠ BREAKING CHANGES

* Complete namespace migration ([84cc0c](https://https://github.com/kachnitel/FrdAdminBundle/commit/84cc0c64354d35a5ed04cd0b18373b0af402edb3))
* Migrate namespace from frd/ to kachnitel/ ([84cc0c](https://https://github.com/kachnitel/FrdAdminBundle/commit/84cc0c64354d35a5ed04cd0b18373b0af402edb3)) *[*[*@FrdAdmin*](https://github.com/FrdAdmin), [*@KachnitelAdmin*](https://github.com/KachnitelAdmin)*]*

### Features

* Add automated metrics and badges system ([4267fe](https://https://github.com/kachnitel/FrdAdminBundle/commit/4267fedf49934e97b1e2d22a28a79c7e2022eafa))
* Add automated release workflow with conventional-changelog ([75426f](https://https://github.com/kachnitel/FrdAdminBundle/commit/75426f27c796864aab7580b8e5498ba18b950ea7))


---

## [0.0.0](https://https://github.com/kachnitel/FrdAdminBundle/compare/70d8895d0eb1c4cfe75d27f6b5648badffccff31...v0.0.0) (2025-12-04)

### Features

* Add generic admin controller with YAML configuration ([ec1b4d](https://https://github.com/kachnitel/FrdAdminBundle/commit/ec1b4de0a17b86e8ea7f0aa3009f40a3806e9060))
* Add LiveComponent entity list with filters and search ([c129ca](https://https://github.com/kachnitel/FrdAdminBundle/commit/c129ca7a4c9c0669097f319a96bd8f9167d2cd95))
* Add per-column filtering with automatic type detection ([b78aaa](https://https://github.com/kachnitel/FrdAdminBundle/commit/b78aaa63d9e6f4ba5016d5bd6c88f7a50170a57d))
* Add static analysis and fix type safety issues ([722999](https://https://github.com/kachnitel/FrdAdminBundle/commit/722999c339b9e9c7545160d0943cad7868d13e07))
* Configurable base template, clickable entity ID ([3fae3b](https://https://github.com/kachnitel/FrdAdminBundle/commit/3fae3b7463a8a8c36e19e2e8518dc8fc35a1dfb7))
* Recognize per entity columns setting ([911f8f](https://https://github.com/kachnitel/FrdAdminBundle/commit/911f8f6d85eb345de016161adad1ac0025d6cb96))
* Register LiveComponent namespace in bundle configuration ([46dd35](https://https://github.com/kachnitel/FrdAdminBundle/commit/46dd35c94e885f49a848da23ccd32b699c6c972f))
* Update URL on filter/sort/page changes ([4cb118](https://https://github.com/kachnitel/FrdAdminBundle/commit/4cb118ebafd91093719c6b4456e39d3aaac8447f))

##### Breaking

* Configure through #[Admin] attribute ([46c629](https://https://github.com/kachnitel/FrdAdminBundle/commit/46c6296d4d419b1bee6cead8a19a7f2c66ca8ede))

##### Wip

* Pagination ([d99418](https://https://github.com/kachnitel/FrdAdminBundle/commit/d99418727163012c52caec25bd108b6a624cae39))

### Bug Fixes

* Add configure() method for AbstractBundle configuration ([e488fa](https://https://github.com/kachnitel/FrdAdminBundle/commit/e488fa72cbd16ad97fab58525e24a66c4cdfb6e1))
* Add default empty array for entities config ([55af50](https://https://github.com/kachnitel/FrdAdminBundle/commit/55af50baa4110916dcbf23509bdc660fd7c27836))
* Allow dynamic columnFilters keys in EntityList LiveComponent ([71f637](https://https://github.com/kachnitel/FrdAdminBundle/commit/71f6379c4433db33b288cf02c9ce6de7d4553420))
* Another go at relationships in index ([b5316b](https://https://github.com/kachnitel/FrdAdminBundle/commit/b5316b1d7458ca492d90195d1a48c5737ad0eac8))
* Collection column data ([ae3af1](https://https://github.com/kachnitel/FrdAdminBundle/commit/ae3af1cd89e3e4b4922a9e0ae258168e2519a1ed))
* Ensure enumClass is always set for enum filters ([2fa093](https://https://github.com/kachnitel/FrdAdminBundle/commit/2fa093c1963be342c233f347288ab6e66739af48))
* Ensure relation filter metadata is always set ([2203ab](https://https://github.com/kachnitel/FrdAdminBundle/commit/2203abae7f89f5dc46c5e9b6f2c46053e1bff9bd))
* Extend layout.html.twig and use correct block names (headerTitle, headerButtons) ([d71180](https://https://github.com/kachnitel/FrdAdminBundle/commit/d71180b10f7ba98847af273bd356c5ca156083dd))
* Handle Doctrine proxies correctly in AdminRouteRuntime ([78de97](https://https://github.com/kachnitel/FrdAdminBundle/commit/78de97c6ccffa4997ae32643f8bac63d1e1e6792))
* Initialize $em in AbstractAdminController ([596a73](https://https://github.com/kachnitel/FrdAdminBundle/commit/596a73feb91483aba476e34507e7352d46115bd7))
* Inline property rendering in show template to access loop variable ([d6b77e](https://https://github.com/kachnitel/FrdAdminBundle/commit/d6b77ea1d437071998f4e2aea0b95d7bddb102b4))
* Inline table row and use include for filter blocks ([528702](https://https://github.com/kachnitel/FrdAdminBundle/commit/5287020c8178105058e4d100dca5df86a39af26b))
* Load services and Twig paths in bundle configuration ([cb3675](https://https://github.com/kachnitel/FrdAdminBundle/commit/cb36756e219d7b6677aef619f4bcf287eed519c6))
* Make bundle self-contained with admin_ prefixed Twig functions ([d249bc](https://https://github.com/kachnitel/FrdAdminBundle/commit/d249bc012d888be22638fcc78847ef460c9b6728))
* Object to string conversion in show.html.twig ([aefdc0](https://https://github.com/kachnitel/FrdAdminBundle/commit/aefdc0a078bbf8df9cd467522fd9354fae72a15a))
* Pass entity, value, and property variables to type templates ([fa497a](https://https://github.com/kachnitel/FrdAdminBundle/commit/fa497a857d893b18f8407be4742c214cfb1891b7))
* Pass required variables to type templates in EntityList ([657ea2](https://https://github.com/kachnitel/FrdAdminBundle/commit/657ea2245dcca969197b81883e18b7c821520cfc))
* Pass variables to blocks for nested component compatibility ([8ecc69](https://https://github.com/kachnitel/FrdAdminBundle/commit/8ecc695936c0a839a06f93621470519bb197567a))
* Process configuration parameters in AbstractBundle ([431ef8](https://https://github.com/kachnitel/FrdAdminBundle/commit/431ef8a85fe9f8e9f96fba773d08e9595ed14a42))
* Remove manual Twig path registration to allow template overrides ([490aa2](https://https://github.com/kachnitel/FrdAdminBundle/commit/490aa2d691be262859cc66d4dd5e6e08a314e7a7)) *[*[*@FrdAdmin*](https://github.com/FrdAdmin)*]*
* Remove unused table_row block and add functional tests ([0c16be](https://https://github.com/kachnitel/FrdAdminBundle/commit/0c16be4f122eefc47f185c4ce23e58108c441a51))
* Replace 'class' with 'entitySlug' in AbstractAdminController ([9b00af](https://https://github.com/kachnitel/FrdAdminBundle/commit/9b00af8949ae4400bd1211d0dfeca035dd90fc27))
* Resolve duplicate filters and collection memory issues ([bbe22b](https://https://github.com/kachnitel/FrdAdminBundle/commit/bbe22b8806896d52f9c6f276427863c12f12bf02))
* Resolve entity routing and relation rendering issues ([86e490](https://https://github.com/kachnitel/FrdAdminBundle/commit/86e4905c12b282c6253fa2f2c879d4f40e8e9eaf))
* Use app's existing Twig functions instead of bundle-specific ones ([7ac1b9](https://https://github.com/kachnitel/FrdAdminBundle/commit/7ac1b98b8c46f65e191fd889986268bcf60ce587))
* Use attribute() function to safely render Doctrine proxy objects ([81e1a4](https://https://github.com/kachnitel/FrdAdminBundle/commit/81e1a4035516b3d24568b562755cbfc18eb59742))
* Use bracket notation for LiveComponent array binding ([685603](https://https://github.com/kachnitel/FrdAdminBundle/commit/685603466dc73171f64149fd5944fa7171f99d21))
* Use lazy loading for filter metadata to avoid hydration issue ([3893a1](https://https://github.com/kachnitel/FrdAdminBundle/commit/3893a1e2ee6917c6cdcc18c3c3c877298bfc9af7))
* Use this.entityClass in LiveComponent template ([748ad3](https://https://github.com/kachnitel/FrdAdminBundle/commit/748ad3a04ff4fe4a6785a4ed31f2b9de72426eaa))
* Variable "frd_admin_base_layout" does not exist in index_live ([271c1d](https://https://github.com/kachnitel/FrdAdminBundle/commit/271c1d64e76ab6230ec32da8381ef9735cde8974))
* Working basic sorting ([b0a7c4](https://https://github.com/kachnitel/FrdAdminBundle/commit/b0a7c4f84bb9395b6d55949c4b9921356f99972f))
* Working ColumnFilter ([cfb338](https://https://github.com/kachnitel/FrdAdminBundle/commit/cfb3383c58d70383fdc2a07c5f9fd31d86376bbc))

### Tests

* Add comprehensive test suite for admin bundle ([4c58e9](https://https://github.com/kachnitel/FrdAdminBundle/commit/4c58e9507f94fcd3ff2e89241a16a4f39c90173e))
* Ensure standard Symfony behaviour of template loading works ([c9999f](https://https://github.com/kachnitel/FrdAdminBundle/commit/c9999fe0c0c06c85e48a38bacc0afa58e0d24c4b))


---

