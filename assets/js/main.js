document.documentElement.classList.add('js');

const navToggle = document.querySelector('.nav-toggle');
const siteNav = document.querySelector('.site-nav');
const navLinks = siteNav ? siteNav.querySelectorAll('a') : [];
const revealItems = document.querySelectorAll('.reveal');
const feedbackTarget = document.querySelector('[data-submission-feedback]');
const reportTarget = document.querySelector('.organizer-report');
const fileInput = document.querySelector('#media');
const fileFeedback = document.querySelector('[data-file-feedback]');
const enhancedForms = document.querySelectorAll('[data-enhanced-form]');
const reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

if (navToggle && siteNav) {
  const setNavState = (isOpen) => {
    siteNav.classList.toggle('is-open', isOpen);
    navToggle.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('nav-open', isOpen);
  };

  setNavState(false);

  navToggle.addEventListener('click', () => {
    setNavState(!siteNav.classList.contains('is-open'));
  });

  navLinks.forEach((link) => {
    link.addEventListener('click', () => {
      setNavState(false);
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && siteNav.classList.contains('is-open')) {
      setNavState(false);
      navToggle.focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (
      siteNav.classList.contains('is-open')
      && !siteNav.contains(event.target)
      && !navToggle.contains(event.target)
    ) {
      setNavState(false);
    }
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 980) {
      setNavState(false);
    }
  });
}

const showRevealItems = () => {
  revealItems.forEach((item) => {
    item.classList.add('is-visible');
  });
};

const revealHashTarget = () => {
  if (!window.location.hash) {
    return;
  }

  const target = document.querySelector(window.location.hash);

  if (!(target instanceof HTMLElement)) {
    return;
  }

  const nestedRevealItems = target.querySelectorAll('.reveal');
  nestedRevealItems.forEach((item) => {
    item.classList.add('is-visible');
  });
};

if (revealItems.length > 0) {
  if (reduceMotionQuery.matches || !('IntersectionObserver' in window)) {
    showRevealItems();
  } else {
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, {
      threshold: 0.18,
      rootMargin: '0px 0px -48px 0px',
    });

    revealItems.forEach((item) => {
      revealObserver.observe(item);
    });
  }
}

revealHashTarget();

window.addEventListener('hashchange', () => {
  revealHashTarget();
});

if (typeof reduceMotionQuery.addEventListener === 'function') {
  reduceMotionQuery.addEventListener('change', (event) => {
    if (event.matches) {
      showRevealItems();
    }
  });
}

if (feedbackTarget instanceof HTMLElement) {
  feedbackTarget.focus();
}

if (reportTarget instanceof HTMLElement) {
  reportTarget.scrollIntoView({
    behavior: reduceMotionQuery.matches ? 'auto' : 'smooth',
    block: 'start',
  });
}

if (fileInput instanceof HTMLInputElement && fileFeedback instanceof HTMLElement) {
  const updateFileFeedback = () => {
    const files = fileInput.files;

    if (!files || files.length === 0) {
      fileFeedback.textContent = 'No files selected yet.';
      return;
    }

    if (files.length === 1) {
      fileFeedback.textContent = `1 photo selected: ${files[0].name}`;
      return;
    }

    fileFeedback.textContent = `${files.length} photos selected`;
  };

  updateFileFeedback();
  fileInput.addEventListener('change', updateFileFeedback);
}

enhancedForms.forEach((form) => {
  form.addEventListener('submit', () => {
    const submitButton = form.querySelector('button[type="submit"]');

    if (!(submitButton instanceof HTMLButtonElement)) {
      return;
    }

    const loadingLabel = submitButton.dataset.loadingLabel;

    submitButton.disabled = true;

    if (loadingLabel) {
      submitButton.textContent = loadingLabel;
    }
  });
});
