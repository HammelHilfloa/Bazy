(() => {
    const navToggle = document.querySelector('.site-nav__toggle');
    const navList = document.querySelector('.site-nav__list');
    const navLinks = document.querySelectorAll('[data-scroll]');
    const sections = document.querySelectorAll('main .section');
    const contactForm = document.querySelector('.contact-form');
    const activeClass = 'is-active';

    function closeMenu() {
        if (!navList) return;
        navList.classList.remove('is-open');
        navToggle?.setAttribute('aria-expanded', 'false');
    }

    navToggle?.addEventListener('click', () => {
        const expanded = navToggle.getAttribute('aria-expanded') === 'true';
        navToggle.setAttribute('aria-expanded', String(!expanded));
        navList?.classList.toggle('is-open');
    });

    navLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const targetId = link.getAttribute('href')?.replace('#', '');
            if (!targetId) return;
            const target = document.getElementById(targetId);
            if (target) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                closeMenu();
            }
        });
    });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    const id = entry.target.getAttribute('id');
                    if (!id) return;
                    const link = document.querySelector(`.site-nav__list a[href="#${id}"]`);
                    if (!link) return;
                    if (entry.isIntersecting) {
                        document
                            .querySelectorAll('.site-nav__list a')
                            .forEach((navLink) => navLink.classList.remove(activeClass));
                        link.classList.add(activeClass);
                    }
                });
            },
            { rootMargin: '-40% 0px -40% 0px', threshold: 0 }
        );

        sections.forEach((section) => observer.observe(section));
    }

    contactForm?.querySelectorAll('[required]').forEach((field) => {
        field.addEventListener('input', () => {
            if (
                field instanceof HTMLInputElement ||
                field instanceof HTMLTextAreaElement ||
                field instanceof HTMLSelectElement
            ) {
                if (field.value.trim()) {
                    field.classList.remove('has-error');
                    field.removeAttribute('aria-invalid');
                }
            }
        });
    });

    contactForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) return;
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach((field) => {
            if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('has-error');
                    field.setAttribute('aria-invalid', 'true');
                } else {
                    field.classList.remove('has-error');
                    field.removeAttribute('aria-invalid');
                }
            }
        });

        if (!isValid) {
            const firstError = form.querySelector('.has-error');
            firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const dialog = document.createElement('div');
        dialog.className = 'form-feedback';
        dialog.innerHTML = `
            <div class="form-feedback__card" role="status" aria-live="polite">
                <h3>Vielen Dank!</h3>
                <p>Ihre Nachricht wurde vorbereitet. Ergänzen Sie hier Ihr eigenes Versand-Skript oder eine E-Mail-Integration.</p>
                <button type="button" class="form-feedback__close">Schließen</button>
            </div>
        `;
        document.body.appendChild(dialog);
        const closeDialog = () => dialog.remove();
        dialog.querySelector('button')?.addEventListener('click', closeDialog);
        dialog.addEventListener('click', (evt) => {
            if (evt.target === dialog) {
                closeDialog();
            }
        });
        document.addEventListener(
            'keydown',
            function handleKey(evt) {
                if (evt.key === 'Escape') {
                    closeDialog();
                    document.removeEventListener('keydown', handleKey);
                }
            },
            { once: true }
        );

        form.reset();
    });
})();
