(function () {
  const navLinks = document.querySelectorAll('nav a[href^="#"]');

  navLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      const targetId = link.getAttribute('href').slice(1);
      const target = document.getElementById(targetId);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  const yearSpan = document.querySelector('[data-current-year]');
  if (yearSpan) {
    yearSpan.textContent = new Date().getFullYear();
  }

  const form = document.querySelector('#contact-form');
  if (form) {
    form.addEventListener('submit', (event) => {
      event.preventDefault();

      const data = Object.fromEntries(new FormData(form));
      const summary = `Vielen Dank, ${data.name || 'liebe Besucherin, lieber Besucher'}!\n` +
        `Wir melden uns unter ${data.email || 'Ihrer angegebenen Adresse'} so schnell wie möglich.\n` +
        `Ihre Nachricht:\n${data.message || ''}`;

      alert(summary);
      form.reset();
    });
  }
})();
