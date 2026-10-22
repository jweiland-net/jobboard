:navigation-title: Contributing

..  include:: /Includes.rst.txt
..  _contributing:

============
Contributing
============

The extension is developed on GitHub:
https://github.com/jweiland-net/jobboard. Report bugs and feature
requests in the `issue tracker <https://github.com/jweiland-net/jobboard/issues>`__.
Pull requests target the branch :code:`main`.

..  _contributing-tests:

Run tests and checks
====================

All checks run in containers (Docker or Podman) with
:file:`Build/Scripts/runTests.sh`:

..  code-block:: bash
    :caption: Common commands

    Build/Scripts/runTests.sh -s composerUpdate
    Build/Scripts/runTests.sh -s lint
    Build/Scripts/runTests.sh -s rector
    Build/Scripts/runTests.sh -s cgl
    Build/Scripts/runTests.sh -s unit
    Build/Scripts/runTests.sh -s functional -d mariadb
    Build/Scripts/runTests.sh -s renderDocumentation
    Build/Scripts/runTests.sh -s testRenderDocumentation

*   :bash:`-p 8.2`, :bash:`-p 8.3` or :bash:`-p 8.4` selects the PHP
    version.
*   :bash:`-d` selects the database of the functional tests:
    :bash:`sqlite` (default), :bash:`mariadb`, :bash:`mysql` or
    :bash:`postgres`.
*   Run :bash:`-s rector` before :bash:`-s cgl`. Use :bash:`-s cgl -n`
    for a dry run.

The continuous integration on GitHub runs linting, Composer validation,
the coding guidelines check, unit tests and functional tests with MariaDB
and PostgreSQL for every pull request.

..  _contributing-documentation:

Documentation
=============

This documentation is written in reStructuredText and rendered with the
official TYPO3 documentation rendering:

..  code-block:: bash
    :caption: Render the documentation locally

    docker run --rm --pull always -v "$(pwd)":/project \
        ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

Open :file:`Documentation-GENERATED-temp/Index.html` in your browser. See
`How to document <https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/>`__
for the conventions.
