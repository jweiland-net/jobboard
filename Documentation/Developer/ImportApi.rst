:navigation-title: Import API

..  include:: /Includes.rst.txt
..  _developer-import-api:

==================================
Connect a job source to the import
==================================

The :ref:`job import <import>` reads jobs from XML sources. Each source is
a PHP class that implements
:php:`JWeiland\Jobboard\ApiModel\ApiModelInterface` and is tagged with
:yaml:`api.model`. The extension ships no source, add your classes to a
site package.

..  _developer-import-architecture:

Architecture
============

..  code-block:: text
    :caption: Flow of the console command jobboard:import:jobs:mhm

    ImportJobsMhm (console command, argument: storage page UID)
     └── ImportService::import()
          ├── for each service tagged "api.model"
          │    ├── XmlClient: GET request to getApiEndpoint()
          │    │    (failed request or invalid XML: source skipped,
          │    │     all its jobs are kept)
          │    ├── every child element of the XML root element = one job
          │    │    (jobs without vacancy_id are skipped)
          │    ├── TtAddressService: address of the primary location
          │    ├── JobService: job record built from getMapping(),
          │    │    job_areas/job_type resolved or created by title
          │    └── DataHandler: save address and job
          └── DataHandler: delete imported jobs that a successfully
                           fetched source no longer delivers

..  _developer-import-class:

Create a source class
=====================

..  literalinclude:: _snippets/ExampleJobBoardApiModel.php
    :language: php
    :caption: EXT:my_sitepackage/Classes/Import/ExampleJobBoardApiModel.php

The trait :php:`JWeiland\Jobboard\ApiModel\ApiModelTrait` implements all
methods of the interface. It needs the class constants :php:`NAME`,
:php:`API_ENDPOINT` and :php:`MAPPING` and the property
:php:`private int $storagePid = 0;`, which the trait does not declare.

:php:`NAME`
    Short, unique name of the source. It is used as prefix of the vacancy
    ID, for example :code:`example_4711`.

:php:`API_ENDPOINT`
    URL of the XML file. It is requested with the TYPO3
    :php:`RequestFactory`, so the HTTP settings of
    :php:`$GLOBALS['TYPO3_CONF_VARS']['HTTP']` (timeout, proxy) apply.

:php:`MAPPING`
    Maps XML values to columns of the table
    :sql:`tx_jobboard_domain_model_job`, see
    :ref:`developer-import-mapping`.

..  attention::

    Do not commit endpoints with credentials or internal URLs to public
    repositories.

..  _developer-import-register:

Register the class
==================

With the attribute :php:`#[Autoconfigure(tags: ['api.model'], shared:
false)]`, the class is registered automatically, if the
:file:`Configuration/Services.yaml` of your site package enables
:yaml:`autoconfigure` for this class. Without the attribute, tag the class
in :file:`Services.yaml`:

..  literalinclude:: _snippets/_Services.yaml
    :language: yaml
    :caption: EXT:my_sitepackage/Configuration/Services.yaml

Flush all caches after adding or removing a source, because the list of
sources is compiled into the dependency injection container:
:bash:`vendor/bin/typo3 cache:flush`.

..  _developer-import-mapping:

The mapping
===========

Each key of :php:`MAPPING` is a column of
:sql:`tx_jobboard_domain_model_job`. Each value is an array with these
keys:

:php:`apiPath` (required)
    XPath of the value, relative to the job element, for example
    :code:`title/de`.

:php:`isDate` (required)
    :php:`true` converts the value to a Unix timestamp. Supported formats
    are :code:`Y-m-d H:i:s O`, :code:`Y-m-d H:i:s` and :code:`Y-m-d`. An
    empty value or :code:`0` results in :php:`0`.

:php:`default` (required)
    Used if the XML element is missing, or if the value cannot be
    converted, for example a date in an unknown format. The default is
    stored as it is, without date conversion and without prefix. An XML
    element that exists but is empty stays empty.

:php:`prefix` (optional)
    Stored as :code:`<prefix>_<value>`. For :php:`vacancy_id`, the prefix
    must be the :php:`NAME` of the source, because the import finds
    existing jobs by :code:`<NAME>_<vacancy ID>` and recognizes the jobs of
    a source by this prefix.

These columns are set by the import itself and need no mapping:
:sql:`pid` (the storage page of the command), :sql:`is_import` and
:sql:`address`. :sql:`job_areas` and :sql:`job_type` are read from the
default paths below; map them only to read their titles from another
element. Their mapping is never written as it is, its titles are always
resolved to records.

..  _developer-import-xml:

Expected XML structure
======================

The services of the import expect some elements at fixed paths:

..  literalinclude:: _snippets/_jobs.xml
    :language: xml
    :caption: Example XML of a source

Root element
    Any name. Each child element is one job.

:code:`vacancy_id`
    ID of the job in the source, any string. Surrounding whitespace is
    removed. Required to update existing jobs instead of creating new ones;
    jobs without vacancy ID are skipped.

:code:`custom_select_4/de`
    Titles of the occupational fields, one per line.

:code:`custom_select_2/de`
    Title of the employment scope.

:code:`locations/location[@primary="true"]`
    The primary location, which becomes the workplace address. Its
    :code:`technical_name` is stored as import key of the address,
    :code:`de` as company, plus :code:`street`, :code:`zipcode` and
    :code:`city`. A job without primary location is skipped.

:code:`manager_name`, :code:`manager_email`
    Name and email of the address.

Occupational fields and employment scopes are found by their exact title
in any language, hidden records included, and the record of the default
language is related. A title without a matching record is created as new
record in the default language on the storage page of the command.

..  _developer-import-salary:

Salary information of imported jobs
===================================

Like all jobs, imported jobs are only shown with salary information, as
long as the site setting :confval:`jobboard-salary-required` is enabled
(the default). Map
:sql:`salary_mode` to an XML element with the value :code:`1` (free salary
entry) and map :sql:`salary_min` and :sql:`salary_max`. Required fields of
the backend form, like the reference number or the start date, are not
checked by the import.
