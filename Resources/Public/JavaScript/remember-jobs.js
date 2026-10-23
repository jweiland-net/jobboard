// Plain JavaScript without any framework. Remembers jobs selected with the
// checkbox "remember_job" in the TYPO3 session of the visitor.
// List and Search are cached and render the same markup for everybody, so
// the remembered jobs are requested from RememberJobMiddleware after loading
// the page (empty POST body, never creates a session). The answer checks the
// checkboxes, highlights the rows ("highlight-row") and fills the count badge
// of the link to the remembered jobs.
// On the uncached view of the remembered jobs (data-jobboard-remembered-view)
// unchecked rows are greyed out ("jobboard-removed") until the next page load
// instead.
(() => {
  'use strict';

  const HEADER_NAME = 'jobboard-remember-job';
  const HIGHLIGHT_CLASS = 'highlight-row';
  const REMOVED_CLASS = 'jobboard-removed';
  const TOAST_SHOW_CLASS = 'show';
  const INVISIBLE_CLASS = 'invisible';
  const CHECKBOX_SELECTOR = 'input[name="remember_job"]';
  const REMEMBER_URL_SELECTOR = '[data-jobboard-remember-url]';
  const REMEMBERED_VIEW_SELECTOR = '[data-jobboard-remembered-view]';
  const ERROR_MESSAGE_ATTRIBUTES = {
    limitReached: 'jobboardMessageLimitReached',
    unknownJob: 'jobboardMessageUnknownJob',
  };

  // Incremented with every checkbox change, so a slow initial read cannot
  // overwrite the answer of a toggle request sent in the meantime.
  let toggleCounter = 0;

  // The table (or the link to the remembered jobs) provides the URL of the
  // current page, which passes the frontend middleware stack (site and
  // language resolved) and is answered by RememberJobMiddleware. Overridden
  // templates without this attribute fall back to the path of the page.
  const getRememberUrl = (element) => {
    const container = element?.closest(REMEMBER_URL_SELECTOR)
      || document.querySelector(REMEMBER_URL_SELECTOR);

    return container?.dataset.jobboardRememberUrl || window.location.pathname;
  };

  const showRememberedRow = (element, isRemembered) => {
    if (element.closest(REMEMBERED_VIEW_SELECTOR)) {
      element.classList.toggle(REMOVED_CLASS, !isRemembered);
    } else {
      element.classList.toggle(HIGHLIGHT_CLASS, isRemembered);
    }
  };

  const showCount = (rememberedJobs) => {
    document.querySelectorAll('[data-jobboard-remembered-count]').forEach((badge) => {
      badge.textContent = String(rememberedJobs.length);
      badge.classList.toggle(INVISIBLE_CLASS, rememberedJobs.length === 0);
    });
  };

  const showRememberedJobs = (rememberedJobs) => {
    document.querySelectorAll('[data-jobboard-uid]').forEach((element) => {
      const isRemembered = rememberedJobs.includes(Number(element.dataset.jobboardUid));
      showRememberedRow(element, isRemembered);
      element.querySelectorAll(CHECKBOX_SELECTOR).forEach((checkbox) => {
        checkbox.checked = isRemembered;
      });
    });
    showCount(rememberedJobs);
  };

  // Screen readers announce the changed number of remembered jobs
  const announceCount = (rememberedJobs) => {
    const status = document.querySelector('[data-jobboard-remember-status]');
    if (!status) {
      return;
    }

    const text = status.dataset.jobboardStatusText || '%d';
    status.textContent = text.replace('%d', String(rememberedJobs.length));
  };

  const hideMessage = () => {
    document.querySelector('[data-jobboard-remember-toast]')?.classList.remove(TOAST_SHOW_CLASS);
  };

  const showMessage = (error) => {
    const toast = document.querySelector('[data-jobboard-remember-toast]');
    const message = toast?.querySelector('[data-jobboard-remember-toast-message]');
    if (!toast || !message) {
      return;
    }

    const attribute = ERROR_MESSAGE_ATTRIBUTES[error] || 'jobboardMessageFailed';
    toast.classList.add(TOAST_SHOW_CLASS);
    message.textContent = toast.dataset[attribute] || '';
  };

  // An empty body only reads the remembered jobs. The server answers errors
  // (unknown job, too many remembered jobs) with the current list as well,
  // which then wins over the checkbox state.
  const sendRememberRequest = async (url, body) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        [HEADER_NAME]: '1',
        Accept: 'application/json',
      },
      body,
    });
    const data = await response.json();

    if (!Array.isArray(data?.rememberedJobs)) {
      throw new Error('Invalid response');
    }

    return data;
  };

  const readRememberedJobs = async () => {
    const requestedAt = toggleCounter;

    try {
      const data = await sendRememberRequest(getRememberUrl(null));
      if (requestedAt === toggleCounter) {
        showRememberedJobs(data.rememberedJobs);
      }
    } catch (error) {
      // Keep the neutral (cached) state, the visitor can still toggle jobs
    }
  };

  const toggleRememberedJob = async (checkbox) => {
    const requestedState = checkbox.checked;
    const row = checkbox.closest('[data-jobboard-uid]');
    toggleCounter += 1;
    hideMessage();
    if (row) {
      showRememberedRow(row, requestedState);
    }

    try {
      const data = await sendRememberRequest(
        getRememberUrl(checkbox),
        new URLSearchParams({ job: checkbox.value, remember: requestedState ? '1' : '0' }),
      );
      showRememberedJobs(data.rememberedJobs);
      if (data.error) {
        showMessage(data.error);
      } else {
        announceCount(data.rememberedJobs);
      }
    } catch (error) {
      // Network error or unexpected answer: revert to the previous state
      checkbox.checked = !requestedState;
      if (row) {
        showRememberedRow(row, !requestedState);
      }
      showMessage('failed');
    }
  };

  const initialize = () => {
    const checkboxes = document.querySelectorAll(CHECKBOX_SELECTOR);
    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', () => toggleRememberedJob(checkbox));
    });
    document.querySelectorAll('[data-jobboard-remember-toast-close]').forEach((button) => {
      button.addEventListener('click', hideMessage);
    });

    if (checkboxes.length > 0 || document.querySelector('[data-jobboard-remembered-count]')) {
      readRememberedJobs();
    }
  };

  // Pages restored from the back/forward cache show the state of the last
  // visit, e.g. before jobs were removed on the view of the remembered jobs.
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      readRememberedJobs();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
