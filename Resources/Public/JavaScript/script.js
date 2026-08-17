(function () {
  'use strict';
  let dataTable = $('.dataTable').DataTable({
    'bLengthChange': false,
    'language': {
      'url': '/typo3conf/ext/jobboard/Resources/Public/JavaScript/dataTables/i18n/de_DE.json'
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
        currentJobs = jQuery.grep(currentJobs, function (value) {
          return value !== jobUid;
        });
        highlightJobInDatatable(jobUid, false);
      }
    }

    Cookies.set(cookieName, JSON.stringify(currentJobs));
  });

  $('#address').on('keyup', function () {
    let value = $(this).val();

    $('#address').autocomplete({
      source: function( request, response ) {
        $.ajax( {
          url: 'index.php',
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
}());
