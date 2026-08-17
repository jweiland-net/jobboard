:navigation-title: Custom API model

..  include:: /Includes.rst.txt


..  _admin-api:

=================================
Add a custom job import API model
=================================

The scheduled import command (:bash:`jobboard:import:jobs:mhm`) does not talk
to one fixed API. Instead, it asks a small set of PHP classes which
endpoints exist and how their XML fields map to the columns of
:sql:`tx_jobboard_domain_model_job`. Adding a new job source therefore never
requires touching :php:class:`\JWeiland\Jobboard\Service\ImportService` -
you only add one new class.

This page shows, on a stripped-down example, how such a class is built and
registered. All values below (endpoint, XML tags, table/column names) are
placeholders - replace them with whatever your real job source uses.

Target group: **Administrators / Integrators**


..  _admin-api-concept:

How the import model system works
=================================

Every job source is represented by one PHP class implementing
:php:interface:`\JWeiland\Jobboard\ApiModel\ApiModelInterface`. Such a class
does not fetch or parse anything itself - it only describes:

*   **Where** to fetch the XML from
    (:php:method:`\JWeiland\Jobboard\ApiModel\ApiModelInterface::getApiEndpoint()`).
*   **How** each XML field maps to a column of
    :sql:`tx_jobboard_domain_model_job`
    (:php:method:`\JWeiland\Jobboard\ApiModel\ApiModelInterface::getMapping()`).

:php:class:`\JWeiland\Jobboard\Service\ImportService` receives all classes
implementing this interface as one collection (a Symfony *tagged
iterator*, see :ref:`admin-api-registering` below), downloads the XML for
each of them, and passes every single job entry - already wrapped as a
:php:class:`\JWeiland\Jobboard\ApiModel\JobModel` - together with the API
model to :php:class:`\JWeiland\Jobboard\Service\JobService`, which then
reads each mapped field via
:php:method:`\JWeiland\Jobboard\ApiModel\AbstractModel::getValueByPath()`
and writes the record through the TYPO3
:php:class:`\TYPO3\CMS\Core\DataHandling\DataHandler`.

In short: to support a new job source, you only ever write **one new
class** describing name, endpoint and field mapping - nothing else in the
extension needs to change.


..  _admin-api-example:

Example: A minimal API model
============================

The example below is intentionally generic. It assumes a fictional job
board that publishes its vacancies as XML under
:samp:`https://example.org/api/jobs.xml`, with one :xml:`<job>` element per
vacancy, each looking roughly like this:

..  code-block:: xml
    :caption: Example XML response of https://example.org/api/jobs.xml

    <jobs>
        <job>
            <vacancy_id>4711</vacancy_id>
            <title>
                <de>Fachkraft (m/w/d)</de>
            </title>
            <description>
                <de>Example description of the vacancy.</de>
            </description>
            <company>
                <de>Example Company GmbH</de>
            </company>
            <application_email>jobs@example.org</application_email>
            <valid_from>2024-01-09 00:00:00 +0100</valid_from>
            <valid_to>2024-06-30 00:00:00 +0100</valid_to>
            <publication_link>https://example.org/jobs/4711</publication_link>
        </job>
    </jobs>

A matching API model class looks like this:

..  code-block:: php
    :caption: Classes/ApiModel/ExampleJobBoardApiModel.php (example)

    <?php

    declare(strict_types=1);

    namespace JWeiland\Jobboard\ApiModel;

    use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

    /**
     * Example API model for a fictional job board reachable at example.org.
     * Copy this class, rename it and adjust NAME, API_ENDPOINT and MAPPING
     * to connect a new job source.
     */
    #[Autoconfigure(
        tags: ['api.model'],
        shared: false,
    )]
    class ExampleJobBoardApiModel implements ApiModelInterface
    {
        use ApiModelTrait;

        // Short, unique, lowercase identifier of this source. Used as a
        // prefix for "vacancy_id" to keep IDs unique across sources.
        private const NAME = 'example';

        private const API_ENDPOINT = 'https://example.org/api/jobs.xml';

        // Key = column of tx_jobboard_domain_model_job
        // Value = where to find it in the XML
        // (see AbstractModel::getValueByPath())
        private const MAPPING = [
            'title' => [
                'apiPath' => 'title/de',
                'isDate' => false,
                'default' => '',
            ],
            'description' => [
                'apiPath' => 'description/de',
                'isDate' => false,
                'default' => '',
            ],
            'employer' => [
                'apiPath' => 'company/de',
                'isDate' => false,
                'default' => '',
            ],
            'email' => [
                'apiPath' => 'application_email',
                'isDate' => false,
                'default' => '',
            ],
            'link' => [
                'apiPath' => 'publication_link',
                'isDate' => false,
                'default' => '',
            ],
            'start_date' => [
                'apiPath' => 'valid_from',
                'isDate' => true,
                'default' => '',
            ],
            'ending_date' => [
                'apiPath' => 'valid_to',
                'isDate' => true,
                'default' => '',
            ],
            'reference_number' => [
                'apiPath' => 'vacancy_id',
                'isDate' => false,
                'default' => '000000',
            ],
            'vacancy_id' => [
                'apiPath' => 'vacancy_id',
                'isDate' => false,
                'default' => 0,
                'prefix' => self::NAME,
            ],
        ];

        // Required by ApiModelTrait::getStoragePid()/withStoragePid().
        // The trait does not declare this property itself, every
        // implementing class must provide it.
        private int $storagePid = 0;
    }

..  rst-class:: dl-parameters

:php:`apiPath`
    :sep:`|` :aspect:`Type:` string
    :sep:`|`

    XPath-like path into one :xml:`<job>` entry, resolved via
    :php:method:`\JWeiland\Jobboard\ApiModel\AbstractModel::getValueByPath()`.
    Nested elements are separated with :php:`/`, e.g. :php:`'title/de'` for
    :xml:`<title><de>...</de></title>`.

:php:`isDate`
    :sep:`|` :aspect:`Type:` bool
    :sep:`|`

    Set to :php:`true` if the source field is a date/time string. The
    value is then converted to a Unix timestamp before it is stored
    (supported formats: :php:`'Y-m-d H:i:s O'`, :php:`'Y-m-d H:i:s'`,
    :php:`'Y-m-d'`).

:php:`default`
    :sep:`|` :aspect:`Type:` string|int
    :sep:`|`

    Fallback value used when the XPath lookup fails or throws
    (missing/empty XML node).

:php:`prefix`
    :sep:`|` :aspect:`Type:` string
    :sep:`|` :aspect:`Optional`
    :sep:`|`

    Prepended as :php:`'<prefix>_<value>'`. Used for :sql:`vacancy_id` so
    IDs from different sources cannot collide with each other in the same
    storage folder.

..  attention::
    Never commit real, project-specific API endpoints, credentials or
    field values into this public repository. If your job source is
    confidential, keep the concrete API model class (endpoint URL,
    mapping, credentials) in a private, project-specific extension instead
    and only rely on :ext:`jobfair2` for the generic import machinery.


..  _admin-api-registering:

Registering the model
=====================

The interface alone
(:php:interface:`\JWeiland\Jobboard\ApiModel\ApiModelInterface`) is **not
enough** to activate a new source - it must also be tagged as
:php:`api.model`, because
:php:class:`\JWeiland\Jobboard\Service\ImportService` receives all sources
as one tagged service collection:

..  code-block:: yaml
    :caption: EXT:jobboard/Configuration/Services.yaml

    JWeiland\Jobboard\Service\ImportService:
        arguments:
            $apiModels: !tagged api.model

There are two ways to add that tag; both are equivalent, but the PHP
attribute is the recommended one:

#.  **PHP attribute (recommended):** add
    :php:`#[Autoconfigure(tags: ['api.model'], shared: false)]` directly on
    the class, as shown in the example above. This works out of the box
    because :file:`Configuration/Services.yaml` already loads every class
    below :file:`Classes/` with :yaml:`autoconfigure: true`, so TYPO3's DI
    container reads the attribute automatically - no further YAML changes
    are needed for a new class placed in :file:`Classes/ApiModel/`.

#.  **Manual YAML tagging:** alternatively, tag the service explicitly in
    :file:`Configuration/Services.yaml`:

    ..  code-block:: yaml
        :caption: EXT:jobboard/Configuration/Services.yaml

        JWeiland\Jobboard\ApiModel\ExampleJobBoardApiModel:
            shared: false
            tags:
                - name: api.model

Use option 2 only if you cannot modify the class itself (e.g. it comes from
another package).


..  _admin-api-cache:

Clearing caches after adding a new model
========================================

The list of tagged :php:`api.model` services is resolved once, when TYPO3
compiles the Dependency Injection container - the result is cached. After
adding, renaming or removing an API model class, this cache must be
rebuilt, otherwise :php:class:`\JWeiland\Jobboard\Service\ImportService`
keeps using the previous list of sources.

Flush the caches via the backend (:guilabel:`Admin Tools > Maintenance >
Flush TYPO3 and PHP Cache Caches`) or via the command line:

..  code-block:: bash

    vendor/bin/typo3 cache:flush

Do this every time after adding a new class under :file:`Classes/ApiModel/`
- a simple frontend/backend cache clear is **not** sufficient, the DI
container cache specifically needs to be rebuilt.


..  _admin-api-running:

Running the import
==================

Once the cache has been flushed, the new source is picked up automatically
the next time the import command runs - no further wiring is required:

..  code-block:: bash

    vendor/bin/typo3 jobboard:import:jobs:mhm <storagePid>

:samp:`<storagePid>` is the page ID (folder) where imported jobs and
addresses are stored.
