// Plain JavaScript without any framework. Offers zip/city suggestions for the
// location field of the search form, answered by AddressSearchMiddleware.
(() => {
  'use strict';

  const MIN_LENGTH_ADDRESS_SEARCH = 3;
  const DELAY_ADDRESS_SEARCH = 250;

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
    document.querySelectorAll('input#address[type="text"]').forEach(initializeAddressSearch);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
