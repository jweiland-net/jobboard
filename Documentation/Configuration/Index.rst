:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

The job board is configured with site sets and site settings. TypoScript
is only needed for special cases. The content element "Job board" has a
few options of its own.

Quick start checklist
=====================

#.  Site set :yaml:`jweiland/jobboard` is added to the site, see
    :ref:`configuration-site-sets`.
#.  Site setting :confval:`jobboard-storagepid` points to the storage
    folder of the jobs, see :ref:`configuration-site-settings`.
#.  The route enhancer is imported, see :ref:`configuration-routing`.
#.  Optional: site setting :confval:`jobboard-pidofapplicationform` points
    to the page with your application form.

..  toctree::
    :maxdepth: 1
    :titlesonly:

    SiteSets
    SiteSettings
    TypoScript
    Plugin
    PageTsConfig
    Routing
    PageTitle
