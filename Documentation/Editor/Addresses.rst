:navigation-title: Addresses

..  include:: /Includes.rst.txt
..  _editor-addresses:

==========================
Addresses of job locations
==========================

The workplace of a job and the address of the employer are address
records of EXT:tt_address. Create them in the storage folder with
:guilabel:`New Address` in the list module.

..  figure:: /Images/Editor/address.png
    :alt: Tab Address of an address record with the read-only field Import Key
    :class: with-border with-shadow
    :zoom: lightbox

    Tab "Address" of an address record

The job board uses these fields:

Company
    Shown as "Place of Employment" in the detail view.

Address, ZIP code, city
    Shown in the list and in the detail view. ZIP code and city are used by
    the location search, see :ref:`configuration-plugin-search-logic`.

Email, phone
    Shown with the workplace in the detail view and in the PDF version.

Import Key
    Read-only. Filled by the :ref:`job import <import>` only, to find
    imported addresses again.

..  _editor-addresses-map:

Map location
============

If EXT:maps2 is installed, address records have an additional tab
:guilabel:`Maps2` with the map location of the address. EXT:maps2 creates
the map location and determines its position from address, ZIP code, city
and country when you save the address in the backend. Save the address in
the default language.

The title of the map location is the title of the marker on the map.
EXT:maps2 takes it from the address on every save: the company, otherwise
first and last name, otherwise the city.

The map location is the same in all languages. In a translated address,
the field is read-only and shows the map location of the address in the
default language. Change it in the address of the default language.

Only jobs with an address that has a map location appear on the map. Jobs
at the same address share one marker. If no listed job has one, the
frontend shows the message "The map cannot be shown" instead of the map.
Save the addresses of these jobs in the backend, so that EXT:maps2
determines their positions.

A click on a marker opens an info window with the name, ZIP code and city
of the address and links to all open jobs at this location, sorted
alphabetically by job title, see :ref:`templates-info-window`.

..  _editor-addresses-select:

Selecting an address in a job
=============================

In the job record, the field :guilabel:`Workplace` (tab
:guilabel:`Job Details`) and the field :guilabel:`Address of the employer`
(tab :guilabel:`Employer`) refer to an address.

..  tip::

    The search field :guilabel:`Find records` above these fields only finds
    addresses in the same folder as the job. To select an address from
    another folder, use the button :guilabel:`Browse for records`.
