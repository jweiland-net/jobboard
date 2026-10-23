// Plain JavaScript without any framework. Remembers jobs in a cookie and
// offers zip/city suggestions for the location field of the search form.
(() => {
  'use strict';

  const COOKIE_NAME = 'jobboard_remembered_jobs';
  const MIN_LENGTH_ADDRESS_SEARCH = 3;
  const DELAY_ADDRESS_SEARCH = 250;

  const getRememberedJobs = () => {
    const cookieValue = Cookies.get(COOKIE_NAME);

    try {
      return cookieValue ? JSON.parse(cookieValue) : [];
    } catch (error) {
      return [];
    }
  };

  const highlightJob = (jobUid, highlight = true) => {
    document.querySelectorAll(`[data-jobboard-uid="${jobUid}"]`).forEach((element) => {
      element.classList.toggle('highlight-row', highlight);
      element.querySelectorAll('input[name="remember_job"]').forEach((checkbox) => {
        checkbox.checked = highlight;
      });
    });
  };

  const toggleRememberedJob = (checkbox) => {
    const jobUid = checkbox.value;
    let rememberedJobs = getRememberedJobs();

    if (checkbox.checked && !rememberedJobs.includes(jobUid)) {
      rememberedJobs.push(jobUid);
    } else if (!checkbox.checked) {
      rememberedJobs = rememberedJobs.filter((value) => value !== jobUid);
    }

    highlightJob(jobUid, checkbox.checked);
    Cookies.set(COOKIE_NAME, JSON.stringify(rememberedJobs));
  };

  // The search form provides the URL of the current page, which passes the
  // frontend middleware stack (site and language resolved) and is answered by
  // AddressSearchMiddleware. Overridden templates without this attribute fall
  // back to the path of the current page. A relative "index.php" must not be
  // used: it resolves below the current page path and ends up in a 404.
  const getAddressSearchUrl = (addressField) => {
    const form = addressField.closest('form');

    return form?.dataset.jobboardAddressSearchUrl || window.location.pathname;
  };

  const fetchAddressSuggestions = async (addressField) => {
    const response = await fetch(getAddressSearchUrl(addressField), {
      method: 'POST',
      headers: {
        'jobboard-address-search': '1',
      },
      body: new URLSearchParams({ zipCity: addressField.value }),
    });

    return response.ok ? response.json() : [];
  };

  const renderAddressSuggestions = (datalist, suggestions) => {
    datalist.replaceChildren(...suggestions.map((suggestion) => {
      const option = document.createElement('option');
      option.value = suggestion;

      return option;
    }));
  };

  // Suggestions are shown with a native <datalist>. Only text fields get
  // suggestions, the location can also be a <select> of job locations.
  const initializeAddressSearch = (addressField) => {
    const datalist = document.createElement('datalist');
    datalist.id = `${addressField.id}-suggestions`;
    addressField.after(datalist);
    addressField.setAttribute('list', datalist.id);
    addressField.setAttribute('autocomplete', 'off');

    let timeout;
    addressField.addEventListener('input', () => {
      clearTimeout(timeout);
      if (addressField.value.length < MIN_LENGTH_ADDRESS_SEARCH) {
        renderAddressSuggestions(datalist, []);
        return;
      }

      timeout = setTimeout(async () => {
        try {
          renderAddressSuggestions(datalist, await fetchAddressSuggestions(addressField));
        } catch (error) {
          renderAddressSuggestions(datalist, []);
        }
      }, DELAY_ADDRESS_SEARCH);
    });
  };

  const initialize = () => {
    getRememberedJobs().forEach((jobUid) => highlightJob(jobUid));

    document.querySelectorAll('input[name="remember_job"]').forEach((checkbox) => {
      checkbox.addEventListener('change', () => toggleRememberedJob(checkbox));
    });

    document.querySelectorAll('input#address[type="text"]').forEach(initializeAddressSearch);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
