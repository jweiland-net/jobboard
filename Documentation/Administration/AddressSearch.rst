:navigation-title: Address search

..  include:: /Includes.rst.txt
..  _address-search:

==================================
Suggestions for the location field
==================================

If the content element uses the address search field "Search field with
suggestions" (see :confval:`plugin-addresssearchmode`), the search form
suggests ZIP codes and cities while the visitor types.

How it works
============

The JavaScript of the job board sends a POST request with the HTTP header
:code:`jobboard-address-search` and the form field :code:`zipCity` after
three characters and shows the answer as a native :html:`<datalist>` of
the location field. The request goes to the URL of the current page, which
the search form provides in the attribute
:html:`data-jobboard-address-search-url`. If the attribute is missing,
the path of the current page in the browser is used. The PSR-15 middleware
:php:`JWeiland\Jobboard\Middleware\AddressSearchMiddleware` (identifier
:code:`jweiland/jobboard-address-search`) answers every frontend request
with this header with a JSON list of up to five entries:

..  code-block:: bash
    :caption: Example request

    curl -X POST -H 'jobboard-address-search: 1' \
        -d 'zipCity=123' https://example.org/jobs

..  code-block:: json
    :caption: Example response

    ["12345 - Sample Town", "12349 - Sample Town-North"]

The middleware searches the fields ZIP code and city of all address
records (:sql:`tt_address`) in the pages of the site setting
:confval:`jobboard-storagepid`. It respects the usual frontend
restrictions (hidden, deleted, start and end time). Each combination of
ZIP code and city is returned once, sorted by city.

..  _address-search-override:

Own search form templates
=========================

The attribute is rendered in :file:`Partials/Job/Search.html`. If you
override this partial, add it to your :html:`<f:form>` as well:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Extensions/Jobboard/Partials/Job/Search.html

    <f:form action="search"
            data="{jobboard-address-search-url: '{f:uri.page()}'}">

Security
========

The address table is shared with other extensions and can contain
personal data. Therefore:

*   The pages to search are always taken from the site setting
    :confval:`jobboard-storagepid` of the current site, never from the
    request. The TypoScript setting
    :typoscript:`plugin.tx_jobboard.persistence.storagePid` is not used.
*   Only ZIP code and city are returned, no other fields.
*   An empty search term or an empty storage setting returns an empty list.

Keep only addresses that may be public in the folders listed in
:confval:`jobboard-storagepid`. Every address in these folders is
searchable, not only the addresses used by jobs.
