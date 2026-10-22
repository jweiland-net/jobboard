# TYPO3 Extension `jobboard`

[![Latest Stable Version](https://poser.pugx.org/jweiland/jobboard/v/stable.svg)](https://packagist.org/packages/jweiland/jobboard)
[![TYPO3 13.4](https://img.shields.io/badge/TYPO3-13.4-green.svg)](https://get.typo3.org/version/13)
[![Total Downloads](https://poser.pugx.org/jweiland/jobboard/downloads.svg)](https://packagist.org/packages/jweiland/jobboard)
[![Monthly Downloads](https://poser.pugx.org/jweiland/jobboard/d/monthly)](https://packagist.org/packages/jweiland/jobboard)
![Build Status](https://github.com/jweiland-net/jobboard/actions/workflows/ci.yml/badge.svg)

Jobboard is a TYPO3 extension that lets you present a list of open job positions on your website - like a digital job board.

## What it does

- Displays a searchable, sortable list of jobs (title, location, job area, job type, application deadline).
- Rich job details: job role, contract type, tender type, benefits (with an optional color, description and
  icon/image each), and salary information - either one or more predefined salary grades or a free-text salary range.
- Multiple files can be attached to a job: an employer logo, a header image, tender documents, and PDF
  attachments.
- Optionally shows job locations on a map, if [EXT:maps2](https://github.com/jweiland-net/maps2) is installed.
- Provides a detail page per job, including an optional PDF export and links to manually related/similar jobs.
- Shows the job title as page title on the detail page (page title provider, configured by the site set).
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

Released under the GPL-2.0-or-later license. See [LICENSE](LICENSE.txt) for details.
