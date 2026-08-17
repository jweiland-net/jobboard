# TYPO3 Extension `jobfair2` (Jobboard)

Jobboard is a TYPO3 extension that lets you present a list of open job positions on your website - like a digital job board.

## What it does

- Displays a searchable, sortable list of jobs (title, location, job area, job type, application deadline).
- Shows job locations on a map ([EXT:maps2](https://github.com/jweiland-net/maps2)).
- Provides a detail page per job, including an optional PDF export.
- Stores job locations as regular addresses ([EXT:tt_address](https://github.com/FriendsOfTYPO3/tt_address)).
- Can automatically import job offers from an external XML API on a regular basis (via a scheduled CLI command), so job listings stay up to date without manual editing.

## Requirements

- TYPO3 13.4 LTS
- PHP 8.2 or higher
- Composer-based TYPO3 installation

## Installation

Install the extension via Composer:

```bash
composer require jweiland/jobfair2
```

Afterward, activate the extension in the TYPO3 backend (Extension Manager / Admin Tools) and add the "Job board" content element to a page.

## License

Released under the GPL-2.0-or-later license. See [LICENSE](LICENSE) for details.
