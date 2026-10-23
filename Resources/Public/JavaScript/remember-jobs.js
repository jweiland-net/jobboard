// Plain JavaScript without any framework. Remembers jobs selected with the
// checkbox "remember_job" in the TYPO3 session of the visitor. The state
// after loading the page is rendered by the server (checked checkbox, class
// "highlight-row"), remembered jobs are listed first on the next page load.
(() => {
  'use strict';

  const HEADER_NAME = 'jobboard-remember-job';
  const HIGHLIGHT_CLASS = 'highlight-row';
  const CHECKBOX_SELECTOR = 'input[name="remember_job"]';

  // The table provides the URL of the current page, which passes the
  // frontend middleware stack (site and language resolved) and is answered
  // by RememberJobMiddleware. Overridden templates without this attribute
  // fall back to the path of the current page.
  const getRememberUrl = (checkbox) => {
    const container = checkbox.closest('[data-jobboard-remember-url]');

    return container?.dataset.jobboardRememberUrl || window.location.pathname;
  };

  const showRememberedJobs = (rememberedJobs) => {
    document.querySelectorAll('[data-jobboard-uid]').forEach((element) => {
      const isRemembered = rememberedJobs.includes(Number(element.dataset.jobboardUid));
      element.classList.toggle(HIGHLIGHT_CLASS, isRemembered);
      element.querySelectorAll(CHECKBOX_SELECTOR).forEach((checkbox) => {
        checkbox.checked = isRemembered;
      });
    });
  };

  // The server answers errors (unknown job, too many remembered jobs) with
  // the current list as well, which then wins over the checkbox state.
  const sendRememberRequest = async (checkbox) => {
    const response = await fetch(getRememberUrl(checkbox), {
      method: 'POST',
      headers: {
        [HEADER_NAME]: '1',
        Accept: 'application/json',
      },
      body: new URLSearchParams({ job: checkbox.value, remember: checkbox.checked ? '1' : '0' }),
    });
    const data = await response.json();

    if (!Array.isArray(data?.rememberedJobs)) {
      throw new Error('Invalid response');
    }

    return data.rememberedJobs;
  };

  const toggleRememberedJob = async (checkbox) => {
    const requestedState = checkbox.checked;
    const row = checkbox.closest('[data-jobboard-uid]');
    row?.classList.toggle(HIGHLIGHT_CLASS, requestedState);

    try {
      showRememberedJobs(await sendRememberRequest(checkbox));
    } catch (error) {
      // Network error or unexpected answer: revert to the previous state
      checkbox.checked = !requestedState;
      row?.classList.toggle(HIGHLIGHT_CLASS, !requestedState);
    }
  };

  const initialize = () => {
    document.querySelectorAll(CHECKBOX_SELECTOR).forEach((checkbox) => {
      checkbox.addEventListener('change', () => toggleRememberedJob(checkbox));
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
