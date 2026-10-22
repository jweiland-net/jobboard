// Uses the jQuery shipped with the job board (see JobListAssets.html), which
// jQuery UI and DataTables were attached to. noConflict(true) restores the
// globals "jQuery" and "$" of a jQuery the site may have loaded before.
(function ($) {
  'use strict';

  // Resolve sibling resources relative to this script's own (possibly hashed/rewritten) URL,
  // since TYPO3 does not expose the extension's public path (typo3conf/ext vs. _assets) to plain JS.
  let scriptBaseUrl = document.currentScript.src.replace(/[^/]+$/, '');

  let dataTable = $('.dataTable').DataTable({
    'bLengthChange': false,
    'language': {
      'url': scriptBaseUrl + 'dataTables/i18n/de_DE.json'
    },
    'paging': false,
    'bInfo': false
  });

  let cookieName = 'jobboard_remembered_jobs';

  $.each(getRememberedJobs(), function (index, value) {
    highlightJobInDatatable(value);
  });

  dataTable.rows().invalidate();
  dataTable.order([dataTable.column('.dataTable .rememberJob').index(), 'desc']);

  function highlightJobInDatatable (jobUid, highlight = true) {
    $('[data-jobboard-uid=' + jobUid + ']').each(function () {
      if (highlight) {
        $(this).addClass('highlight-row');
      } else {
        $(this).removeClass('highlight-row');
      }
      dataTable.cells($(this).find('input[name=remember_job]').parent('td')).invalidate();
      $(this).find('input[name=remember_job]').prop('checked', highlight);
      $(this).find('input[name=remember_job]').parent('td').attr('data-sort', highlight ? 1 : 0);
    });
  }

  function getRememberedJobs () {
    let cookieValue = Cookies.get(cookieName);
    if (!cookieValue) {
      cookieValue = JSON.stringify([]);
    }

    return JSON.parse(cookieValue);
  }

  $('[name=remember_job]').on('click', function () {
    let jobUid = $(this).val();
    let currentJobs = getRememberedJobs();

    if ($(this).is(':checked')) {
      if (currentJobs.indexOf(jobUid) === -1) {
        currentJobs.push(jobUid);
        highlightJobInDatatable(jobUid);
      }
    } else {
      if (currentJobs.indexOf(jobUid) !== -1) {
        currentJobs = $.grep(currentJobs, function (value) {
          return value !== jobUid;
        });
        highlightJobInDatatable(jobUid, false);
      }
    }

    Cookies.set(cookieName, JSON.stringify(currentJobs));
  });

  // The search form provides the URL of the current page, which passes the
  // frontend middleware stack (site and language resolved) and is answered by
  // AddressSearchMiddleware. Overridden templates without this attribute fall
  // back to the path of the current page. A relative "index.php" must not be
  // used: it resolves below the current page path and ends up in a 404.
  function getAddressSearchUrl (addressField) {
    const url = $(addressField).closest('form').data('jobboard-address-search-url');

    return url ? url : window.location.pathname;
  }

  $('#address').on('keyup', function () {
    let value = $(this).val();
    const addressSearchUrl = getAddressSearchUrl(this);

    $('#address').autocomplete({
      source: function( request, response ) {
        $.ajax( {
          url: addressSearchUrl,
          type: 'POST',
          data: {
            zipCity: value
          },
          dataType: 'json',
          headers: {
            'jobboard-address-search': 1
          },
          success: function(data) {
            response(data);
          }
        });
      },
      minLength: 3
    });
  });
}(window.jQuery.noConflict(true)));
