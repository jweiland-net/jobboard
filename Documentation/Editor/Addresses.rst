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
:guilabel:`Maps2` with the map location of the address. The title of the
map location is taken from the company, or from first and last name if
the company is empty. Only jobs with an address that has a map location
appear on the map.

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
