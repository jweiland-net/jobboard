// Plain JavaScript without any framework. Remembers jobs selected with the
// checkbox "remember_job" in a cookie and highlights them. Requires the
// global "Cookies" of js.cookie-2.2.1.min.js.
(() => {
  'use strict';

  const COOKIE_NAME = 'jobboard_remembered_jobs';

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

  const initialize = () => {
    getRememberedJobs().forEach((jobUid) => highlightJob(jobUid));

    document.querySelectorAll('input[name="remember_job"]').forEach((checkbox) => {
      checkbox.addEventListener('change', () => toggleRememberedJob(checkbox));
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();
