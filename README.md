# TYPO3 Extension `jobboard`

[![Packagist][packagist-logo-stable]][extension-packagist-url]
[![TYPO3 14.2][TYPO3-shield]][TYPO3-14-url]

Jobboard is a TYPO3 extension that lets you present a list of open job positions on your website - like a digital job board.

## What it does

- Displays a searchable, sortable list of jobs (title, location, job area, job type, application deadline).
- Rich job details: job role, contract type, tender type, benefits (with an optional color, description and
  icon/image each), and salary information - either a predefined salary grade or a free-text salary range.
- Multiple files can be attached to a job: an employer logo, a header image, tender documents, and PDF
  attachments.
- Shows job locations on a map ([EXT:maps2](https://github.com/jweiland-net/maps2)).
- Provides a detail page per job, including an optional PDF export and links to manually related/similar jobs.
- Stores job locations as regular addresses ([EXT:tt_address](https://github.com/FriendsOfTYPO3/tt_address)).
- Can automatically import job offers from an external XML API on a regular basis (via a scheduled CLI command), so job listings stay up to date without manual editing.

## Requirements

- TYPO3 13.4 LTS
- PHP 8.2 or higher
- Composer-based TYPO3 installation

## Installation

Install the extension via Composer:

```bash
composer require jweiland/jobboard
```

Afterward, activate the extension in the TYPO3 backend (Extension Manager / Admin Tools) and add the "Job board" content element to a page.

## License

Released under the GPL-2.0-or-later license. See [LICENSE](LICENSE) for details.

[extension-build-shield]: https://poser.pugx.org/jweiland/video-shariff/v/stable.svg?style=for-the-badge

[extension-ci-shield]: https://github.com/jweiland-net/jobboard/actions/workflows/ci.yml/badge.svg

[extension-downloads-badge]: https://poser.pugx.org/jweiland/jobboard/d/total.svg?style=for-the-badge

[extension-monthly-downloads]: https://poser.pugx.org/jweiland/jobboard/d/monthly?style=for-the-badge

[extension-ter-url]: https://extensions.typo3.org/extension/jobboard/

[extension-packagist-url]: https://packagist.org/packages/jweiland/jobboard/

[packagist-logo-stable]: https://img.shields.io/badge/--grey.svg?style=for-the-badge&logo=packagist&logoColor=white

[TYPO3-14-url]: https://get.typo3.org/version/14

[TYPO3-shield]: https://img.shields.io/badge/TYPO3-14.3-green.svg?style=for-the-badge&logo=typo3
